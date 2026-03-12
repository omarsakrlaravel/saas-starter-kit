<?php

namespace App\Filament\Resources\FeatureDefinitions;

use App\Enums\FeatureFlagType;
use App\Filament\Resources\FeatureDefinitions\Pages\ListFeatureDefinitions;
use App\Filament\Resources\FeatureDefinitions\Pages\ViewFeatureDefinition;
use App\Models\FeatureDefinition;
use App\Models\Organization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Laravel\Pennant\Feature;
use Wave\ActivityLog;

class FeatureDefinitionResource extends Resource
{
    protected static ?string $model = FeatureDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-flag-duotone';

    protected static ?string $navigationLabel = 'Feature Flags';

    protected static ?string $modelLabel = 'Feature Flag';

    protected static ?string $pluralModelLabel = 'Feature Flags';

    protected static ?string $slug = 'feature-flags';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Feature Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn (FeatureFlagType $state): string => $state->label())
                            ->color(fn (FeatureFlagType $state): string => $state->color()),
                        TextEntry::make('description')
                            ->columnSpanFull(),
                        TextEntry::make('is_active')
                            ->label('Active')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('rollout_percentage')
                            ->label('Rollout %')
                            ->suffix('%')
                            ->placeholder('N/A')
                            ->visible(fn (FeatureDefinition $record): bool => $record->type === FeatureFlagType::Rollout),
                        TextEntry::make('changedBy.name')
                            ->label('Last Changed By')
                            ->placeholder('--'),
                        TextEntry::make('updated_at')
                            ->since(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                FeatureDefinition::query()
                    ->where('type', '!=', FeatureFlagType::KillSwitch),
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (FeatureFlagType $state): string => $state->label())
                    ->color(fn (FeatureFlagType $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('description')
                    ->wrap()
                    ->limit(80),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->afterStateUpdated(function (FeatureDefinition $record, bool $state): void {
                        $record->update([
                            'last_changed_by' => auth()->id(),
                        ]);

                        if ($state) {
                            Feature::purge($record->name);
                        } else {
                            Feature::deactivateForEveryone($record->name);
                        }

                        ActivityLog::log(
                            'feature_flag_toggled',
                            "Feature '{$record->name}' ".($state ? 'activated' : 'deactivated'),
                        );
                    }),
                TextColumn::make('rollout_percentage')
                    ->label('Rollout %')
                    ->suffix('%')
                    ->placeholder('--')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('changedBy.name')
                    ->label('Last Changed By')
                    ->placeholder('--'),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(
                        collect(FeatureFlagType::cases())
                            ->reject(fn (FeatureFlagType $type): bool => $type === FeatureFlagType::KillSwitch)
                            ->mapWithKeys(fn (FeatureFlagType $type): array => [$type->value => $type->label()])
                            ->all(),
                    ),
            ])
            ->recordActions([
                Action::make('override_for_org')
                    ->label('Override for Org')
                    ->icon('heroicon-o-building-office')
                    ->color('warning')
                    ->schema([
                        Select::make('organization_id')
                            ->label('Organization')
                            ->searchable()
                            ->options(fn (): array => Organization::query()
                                ->pluck('name', 'id')
                                ->all())
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->action(function (FeatureDefinition $record, array $data): void {
                        $organization = Organization::findOrFail($data['organization_id']);
                        $active = $data['is_active'];

                        if ($active) {
                            Feature::for($organization)->activate($record->name);
                        } else {
                            Feature::for($organization)->deactivate($record->name);
                        }

                        $record->update([
                            'last_changed_by' => auth()->id(),
                        ]);

                        ActivityLog::log(
                            'feature_flag_override',
                            "Feature '{$record->name}' ".($active ? 'activated' : 'deactivated')." for org '{$organization->name}'",
                        );

                        Notification::make()
                            ->success()
                            ->title("Feature '{$record->name}' ".($active ? 'activated' : 'deactivated')." for '{$organization->name}'")
                            ->send();
                    }),
            ])
            ->recordUrl(fn (FeatureDefinition $record): string => static::getUrl('view', ['record' => $record]))
            ->searchable();
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\KillSwitchesWidget::class,
            \App\Filament\Widgets\OrganizationOverridesWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatureDefinitions::route('/'),
            'view' => ViewFeatureDefinition::route('/{record}'),
        ];
    }
}
