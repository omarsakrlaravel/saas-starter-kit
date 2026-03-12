<?php

namespace App\Filament\Widgets;

use App\Models\FeatureDefinition;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Wave\ActivityLog;

class OrganizationFeaturesWidget extends Widget implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.widgets.organization-features';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function table(Table $table): Table
    {
        $scope = 'App\\Models\\Organization|'.$this->record?->id;

        return $table
            ->query(
                DB::table('features')
                    ->where('scope', $scope)
                    ->orderBy('name'),
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Feature')
                    ->searchable(),
                TextColumn::make('type_display')
                    ->label('Type')
                    ->badge()
                    ->state(function ($record): string {
                        $definition = FeatureDefinition::where('name', $record->name)->first();

                        return $definition?->type?->label() ?? 'Unknown';
                    })
                    ->color(function ($record): string {
                        $definition = FeatureDefinition::where('name', $record->name)->first();

                        return $definition?->type?->color() ?? 'gray';
                    }),
                TextColumn::make('value')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'true' ? 'Active' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'true' ? 'success' : 'danger'),
                TextColumn::make('updated_at')
                    ->label('Set At')
                    ->since(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('toggle')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Toggle Feature')
                    ->modalDescription('This will flip the feature state for this organization.')
                    ->action(function ($record): void {
                        $org = $this->record;
                        $currentlyActive = $record->value === 'true';

                        if ($currentlyActive) {
                            Feature::for($org)->deactivate($record->name);
                        } else {
                            Feature::for($org)->activate($record->name);
                        }

                        ActivityLog::log(
                            'feature_flag_override',
                            "Feature '{$record->name}' ".($currentlyActive ? 'deactivated' : 'activated')." for org '{$org->name}'",
                        );

                        Notification::make()
                            ->success()
                            ->title("Feature '{$record->name}' ".($currentlyActive ? 'deactivated' : 'activated'))
                            ->send();
                    }),
                \Filament\Actions\Action::make('remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Override')
                    ->modalDescription('This feature will revert to default resolution for this organization.')
                    ->action(function ($record): void {
                        $org = $this->record;

                        Feature::for($org)->forget($record->name);

                        ActivityLog::log(
                            'feature_flag_override_removed',
                            "Override for '{$record->name}' removed from org '{$org->name}'",
                        );

                        Notification::make()
                            ->success()
                            ->title("Override for '{$record->name}' removed")
                            ->send();
                    }),
            ])
            ->heading('Feature Overrides')
            ->description('Explicit feature flag overrides for this organization. Features not listed resolve using default rules.')
            ->emptyStateHeading('No feature overrides')
            ->emptyStateDescription('This organization uses default feature resolution for all features.')
            ->paginated(false);
    }

    public function setFeatureAction(): Action
    {
        return Action::make('setFeature')
            ->label('Set Feature')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->schema([
                Select::make('feature_name')
                    ->label('Feature')
                    ->searchable()
                    ->options(fn (): array => FeatureDefinition::query()
                        ->pluck('name', 'name')
                        ->all())
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                $org = $this->record;
                $featureName = $data['feature_name'];
                $active = $data['is_active'];

                if ($active) {
                    Feature::for($org)->activate($featureName);
                } else {
                    Feature::for($org)->deactivate($featureName);
                }

                ActivityLog::log(
                    'feature_flag_override',
                    "Feature '{$featureName}' ".($active ? 'activated' : 'deactivated')." for org '{$org->name}'",
                );

                Notification::make()
                    ->success()
                    ->title("Feature '{$featureName}' ".($active ? 'activated' : 'deactivated'))
                    ->send();
            });
    }
}
