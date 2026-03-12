<?php

namespace App\Filament\Resources\FeatureDefinitions\Pages;

use App\Enums\FeatureFlagType;
use App\Filament\Resources\FeatureDefinitions\FeatureDefinitionResource;
use App\Filament\Widgets\OrganizationOverridesWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Laravel\Pennant\Feature;

class ViewFeatureDefinition extends ViewRecord
{
    protected static string $resource = FeatureDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editRolloutPercentage')
                ->label('Edit Rollout %')
                ->icon('heroicon-o-adjustments-horizontal')
                ->visible(fn (): bool => $this->record->type === FeatureFlagType::Rollout)
                ->schema([
                    TextInput::make('rollout_percentage')
                        ->label('Rollout Percentage')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->default(fn () => $this->record->rollout_percentage)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'rollout_percentage' => $data['rollout_percentage'],
                        'last_changed_by' => auth()->id(),
                    ]);

                    // Purge cached values so new percentage takes effect
                    Feature::purge($this->record->name);

                    Notification::make()
                        ->success()
                        ->title("Rollout updated to {$data['rollout_percentage']}%")
                        ->send();
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            OrganizationOverridesWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
