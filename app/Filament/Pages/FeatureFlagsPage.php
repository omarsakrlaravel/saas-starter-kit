<?php

namespace App\Filament\Pages;

use App\Enums\FeatureFlagType;
use App\Models\FeatureDefinition;
use App\Models\Organization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Laravel\Pennant\Feature;
use Wave\ActivityLog;

class FeatureFlagsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'phosphor-flag-duotone';

    protected static ?string $navigationLabel = 'Feature Flags';

    protected static ?string $title = 'Feature Flags';

    protected string $view = 'filament.pages.feature-flags';

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, FeatureDefinition>
     */
    public function getKillSwitches(): \Illuminate\Database\Eloquent\Collection
    {
        return FeatureDefinition::query()
            ->where('type', FeatureFlagType::KillSwitch)
            ->with('changedBy')
            ->get();
    }

    public function isKillSwitchActive(FeatureDefinition $definition): bool
    {
        return Feature::for(null)->active($definition->name);
    }

    public function toggleKillSwitchAction(): Action
    {
        return Action::make('toggleKillSwitch')
            ->requiresConfirmation()
            ->color(fn (array $arguments): string => ($arguments['active'] ?? false) ? 'success' : 'danger')
            ->icon(fn (array $arguments): string => ($arguments['active'] ?? false) ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
            ->label(fn (array $arguments): string => ($arguments['active'] ?? false) ? 'Deactivate' : 'Activate')
            ->modalIcon(fn (array $arguments): string => ($arguments['active'] ?? false) ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
            ->modalIconColor(fn (array $arguments): string => ($arguments['active'] ?? false) ? 'success' : 'danger')
            ->modalHeading(fn (array $arguments): string => ($arguments['active'] ?? false)
                ? "Deactivate '{$arguments['name']}'"
                : "Activate '{$arguments['name']}'")
            ->modalDescription(fn (array $arguments): string => ($arguments['active'] ?? false)
                ? 'The feature will become available to users again.'
                : 'This will immediately block access to this feature for ALL users.')
            ->modalSubmitActionLabel(fn (array $arguments): string => ($arguments['active'] ?? false) ? 'Yes, deactivate' : 'Yes, activate')
            ->action(function (array $arguments): void {
                $definition = FeatureDefinition::findOrFail($arguments['id']);
                $currentlyActive = Feature::for(null)->active($definition->name);

                if ($currentlyActive) {
                    Feature::for(null)->deactivate($definition->name);
                    $newState = false;
                } else {
                    Feature::for(null)->activate($definition->name);
                    $newState = true;
                }

                $definition->update([
                    'is_active' => $newState,
                    'last_changed_by' => auth()->id(),
                ]);

                ActivityLog::log(
                    'kill_switch_toggled',
                    "Kill switch '{$definition->name}' ".($newState ? 'activated' : 'deactivated'),
                );

                Notification::make()
                    ->success()
                    ->title("Kill switch '{$definition->name}' ".($newState ? 'activated' : 'deactivated'))
                    ->send();
            });
    }

    public function table(Table $table): Table
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
                \Filament\Actions\Action::make('override_for_org')
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
            ->searchable();
    }
}
