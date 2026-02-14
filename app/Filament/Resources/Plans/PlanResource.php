<?php

namespace App\Filament\Resources\Plans;

use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Wave\Plan;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static BackedEnum|string|null $navigationIcon = 'phosphor-credit-card-duotone';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Plan Details')
                            ->description('Below are the basic details for each plan including name, description, and features')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(191)
                                    ->columnSpan(2),
                                Textarea::make('description')
                                    ->columnSpan([
                                        'default' => 2,
                                        'lg' => 1,
                                    ]),
                                TagsInput::make('features')
                                    ->reorderable()
                                    ->separator(',')
                                    ->placeholder('New feature')
                                    ->columnSpan([
                                        'default' => 2,
                                        'lg' => 1,
                                    ]),
                            ])
                            ->columns(2),
                        Section::make('Plan Pricing')
                            ->description('Add the pricing details for your plans below')
                            ->schema([
                                TextInput::make('monthly_price_id')
                                    ->label('Monthly Price ID')
                                    ->hint('Stripe Price ID')
                                    ->maxLength(191),
                                TextInput::make('monthly_price')
                                    ->maxLength(191),
                                TextInput::make('yearly_price_id')
                                    ->label('Yearly Price ID')
                                    ->hint('Stripe Price ID')
                                    ->maxLength(191),
                                TextInput::make('yearly_price')
                                    ->maxLength(191),
                                TextInput::make('onetime_price_id')
                                    ->label('One-time Price ID')
                                    ->hint('Stripe Price ID')
                                    ->maxLength(191),
                                TextInput::make('onetime_price')
                                    ->maxLength(191),
                            ])
                            ->columns(2),
                        Section::make('Trials and Coupons')
                            ->description('Configure default trial and discount behavior for this plan.')
                            ->schema([
                                TextInput::make('trial_days')
                                    ->numeric()
                                    ->minValue(0)
                                    ->label('Trial Days')
                                    ->helperText('Leave empty or 0 to disable free trial.'),
                                TextInput::make('stripe_coupon_id')
                                    ->maxLength(191)
                                    ->label('Stripe Coupon ID')
                                    ->helperText('Optional default coupon to apply when no promotion code is entered.'),
                                TextInput::make('stripe_promotion_code')
                                    ->maxLength(191)
                                    ->label('Stripe Promotion Code ID')
                                    ->helperText('Optional default promotion code ID (API ID, not customer-facing code).'),
                            ])
                            ->columns(2),
                        Section::make('Feature Limits')
                            ->description('Set usage limits for this plan. Leave empty for unlimited. Use -1 for explicitly unlimited, 0 to disable.')
                            ->schema([
                                KeyValue::make('limits')
                                    ->keyLabel('Feature')
                                    ->valueLabel('Limit')
                                    ->keyPlaceholder('e.g., api_keys')
                                    ->valuePlaceholder('e.g., 10')
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpan(2),
                Group::make()
                    ->schema([
                        Section::make('Plan Status')
                            ->description('Status and sort order')
                            ->schema([
                                Toggle::make('active')
                                    ->required(),
                                Toggle::make('default')
                                    ->required(),
                                TextInput::make('sort_order')
                                    ->integer()
                                    ->default(0)
                                    ->minValue(0)
                                    ->required(),
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
                    ->searchable(),
                TextColumn::make('trial_days')
                    ->label('Trial')
                    ->formatStateUsing(fn (?int $state): string => $state && $state > 0 ? $state.' days' : 'None'),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                BooleanColumn::make('active')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
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
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
