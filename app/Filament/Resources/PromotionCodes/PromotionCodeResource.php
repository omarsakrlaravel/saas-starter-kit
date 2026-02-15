<?php

namespace App\Filament\Resources\PromotionCodes;

use App\Filament\Resources\PromotionCodes\Pages\CreatePromotionCode;
use App\Filament\Resources\PromotionCodes\Pages\EditPromotionCode;
use App\Filament\Resources\PromotionCodes\Pages\ListPromotionCodes;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Wave\PromotionCode;

class PromotionCodeResource extends Resource
{
    protected static ?string $model = PromotionCode::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-barcode-duotone';

    protected static ?int $navigationSort = 9;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $modelLabel = 'Promotion Code';

    protected static ?string $pluralModelLabel = 'Promotion Codes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Promotion Code Details')
                            ->schema([
                                Select::make('coupon_id')
                                    ->relationship('coupon', 'name')
                                    ->required()
                                    ->preload()
                                    ->searchable(),
                                TextInput::make('code')
                                    ->required()
                                    ->maxLength(191),
                                TextInput::make('stripe_id')
                                    ->readOnly(fn (string $operation): bool => $operation === 'edit')
                                    ->maxLength(191),
                                TextInput::make('max_redemptions')
                                    ->numeric()
                                    ->nullable(),
                                TextInput::make('minimum_amount')
                                    ->numeric()
                                    ->nullable()
                                    ->helperText('In cents'),
                                TextInput::make('minimum_amount_currency')
                                    ->maxLength(3)
                                    ->nullable(),
                                DateTimePicker::make('expires_at')
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
                                Toggle::make('first_time_transaction')
                                    ->default(false)
                                    ->label('First-time customers only'),
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
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('coupon.name')
                    ->label('Coupon')
                    ->placeholder(fn (Model $record): string => $record->coupon?->stripe_id ?? '—'),
                BooleanColumn::make('active'),
                TextColumn::make('times_redeemed')
                    ->description(fn (Model $record): string => $record->times_redeemed.' / '.($record->max_redemptions ?? 'unlimited')),
                BooleanColumn::make('first_time_transaction')
                    ->label('First-time only'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('active'),
                TernaryFilter::make('first_time_transaction'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->icon('phosphor-prohibit')
                    ->requiresConfirmation()
                    ->visible(fn (Model $record): bool => $record->active)
                    ->action(fn (Model $record) => $record->update(['active' => false])),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotionCodes::route('/'),
            'create' => CreatePromotionCode::route('/create'),
            'edit' => EditPromotionCode::route('/{record}/edit'),
        ];
    }
}
