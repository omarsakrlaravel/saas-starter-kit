<?php

namespace App\Filament\Widgets;

use App\Enums\FeatureFlagType;
use App\Models\FeatureDefinition;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Pennant\Feature;
use Wave\ActivityLog;

class KillSwitchesWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.kill-switches';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Collection<int, FeatureDefinition>
     */
    public function getKillSwitches(): Collection
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
}
