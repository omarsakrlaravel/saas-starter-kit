<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Filament\Resources\Coupons\RelationManagers\PromotionCodesRelationManager;
use App\Filament\Resources\Coupons\RelationManagers\RedemptionsRelationManager;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-ticket-duotone';

    protected static ?int $navigationSort = 8;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $modelLabel = 'Coupon';

    protected static ?string $pluralModelLabel = 'Coupons';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Coupon Details')
                            ->schema([
                                TextInput::make('name')
                                    ->maxLength(191),
                                TextInput::make('stripe_id')
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->readOnly(fn (string $operation): bool => $operation === 'edit')
                                    ->maxLength(191),
                                TextInput::make('currency')
                                    ->maxLength(3),
                                TextInput::make('amount_off')
                                    ->numeric()
                                    ->nullable()
                                    ->helperText('In cents')
                                    ->visible(fn (callable $get): bool => empty($get('percent_off'))),
                                TextInput::make('percent_off')
                                    ->numeric()
                                    ->nullable()
                                    ->helperText('0-100')
                                    ->visible(fn (callable $get): bool => empty($get('amount_off'))),
                                Select::make('duration')
                                    ->options([
                                        'forever' => 'Forever',
                                        'once' => 'Once',
                                        'repeating' => 'Repeating',
                                    ])
                                    ->required(),
                                TextInput::make('duration_in_months')
                                    ->numeric()
                                    ->nullable()
                                    ->visible(fn (callable $get): bool => $get('duration') === 'repeating'),
                                TextInput::make('max_redemptions')
                                    ->numeric()
                                    ->nullable(),
                                DateTimePicker::make('redeem_by')
                                    ->nullable(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(2),
                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Toggle::make('active')
                                    ->default(true),
                                Toggle::make('valid')
                                    ->default(true),
                                TextInput::make('times_redeemed')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0),
                            ]),
                    ])
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->placeholder(fn (Model $record): string => $record->stripe_id),
                TextColumn::make('stripe_id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('percent_off')
                    ->label('Discount')
                    ->formatStateUsing(function ($state, Model $record): string {
                        if ($record->percent_off) {
                            return rtrim(rtrim(number_format($record->percent_off, 2), '0'), '.').'%';
                        }

                        if ($record->amount_off) {
                            return currencySymbol($record->currency).number_format($record->amount_off / 100, 2).' off';
                        }

                        return 'No discount';
                    }),
                TextColumn::make('duration')
                    ->badge(),
                TextColumn::make('times_redeemed')
                    ->description(fn (Model $record): string => $record->times_redeemed.' / '.($record->max_redemptions ?? 'unlimited')),
                BooleanColumn::make('active'),
                TextColumn::make('redeem_by')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('duration')
                    ->options([
                        'forever' => 'Forever',
                        'once' => 'Once',
                        'repeating' => 'Repeating',
                    ]),
                TernaryFilter::make('active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->icon('phosphor-prohibit')
                    ->requiresConfirmation()
                    ->visible(fn (Model $record): bool => $record->active)
                    ->action(function (Model $record): void {
                        if ($record->stripe_id) {
                            try {
                                $stripe = new StripeClient(config('services.stripe.secret'));
                                $stripe->coupons->update($record->stripe_id, ['metadata' => ['deactivated_by_admin' => 'true']]);
                            } catch (ApiErrorException) {
                                // Stripe deactivation is best-effort for coupons
                            }
                        }

                        $record->update(['active' => false]);

                        Notification::make()
                            ->title('Coupon deactivated.')
                            ->success()
                            ->send();
                    }),
                Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (Model $record): void {
                        $newCoupon = $record->replicate(['stripe_id', 'times_redeemed']);
                        $newCoupon->stripe_id = $record->stripe_id.'_copy_'.now()->timestamp;
                        $newCoupon->times_redeemed = 0;
                        $newCoupon->active = false;
                        $newCoupon->save();

                        Notification::make()
                            ->title('Coupon duplicated. Edit the copy to set a new Stripe ID and activate it.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PromotionCodesRelationManager::class,
            RedemptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
