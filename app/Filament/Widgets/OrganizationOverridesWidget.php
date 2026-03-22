<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\PennantFeature;
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
use Laravel\Pennant\Feature;

class OrganizationOverridesWidget extends Widget implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.widgets.organization-overrides';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function table(Table $table): Table
    {
        $featureName = $this->record?->name;

        return $table
            ->query(
                PennantFeature::query()
                    ->where('name', $featureName)
                    ->where('scope', 'like', 'App\\\\Models\\\\Organization|%')
                    ->orderBy('updated_at', 'desc'),
            )
            ->columns([
                TextColumn::make('scope')
                    ->label('Organization')
                    ->formatStateUsing(function (string $state): string {
                        $orgId = str($state)->after('|')->toString();

                        return Organization::find($orgId)?->name ?? "Org #{$orgId}";
                    })
                    ->searchable(),
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
                    ->modalHeading('Toggle Override')
                    ->modalDescription('This will flip the feature state for this organization.')
                    ->action(function ($record) use ($featureName): void {
                        $orgId = str($record->scope)->after('|')->toString();
                        $org = Organization::findOrFail($orgId);
                        $currentlyActive = $record->value === 'true';

                        if ($currentlyActive) {
                            Feature::for($org)->deactivate($featureName);
                        } else {
                            Feature::for($org)->activate($featureName);
                        }

                        $this->record->update(['last_changed_by' => auth()->id()]);

                        ActivityLog::log(
                            'feature_flag_override',
                            "Feature '{$featureName}' ".($currentlyActive ? 'deactivated' : 'activated')." for org '{$org->name}'",
                        );

                        Notification::make()
                            ->success()
                            ->title("Override toggled for '{$org->name}'")
                            ->send();
                    }),
                \Filament\Actions\Action::make('remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Override')
                    ->modalDescription('This organization will revert to the default feature resolution.')
                    ->action(function ($record) use ($featureName): void {
                        $orgId = str($record->scope)->after('|')->toString();
                        $org = Organization::findOrFail($orgId);

                        Feature::for($org)->forget($featureName);

                        $this->record->update(['last_changed_by' => auth()->id()]);

                        ActivityLog::log(
                            'feature_flag_override_removed',
                            "Override for '{$featureName}' removed from org '{$org->name}'",
                        );

                        Notification::make()
                            ->success()
                            ->title("Override removed for '{$org->name}'")
                            ->send();
                    }),
            ])
            ->heading('Organization Overrides')
            ->description('Organizations with explicit feature state overrides. Others use default resolution.')
            ->headerActions([
                Action::make('addOverride')
                    ->label('Add Override')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
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
                    ->action(function (array $data): void {
                        $organization = Organization::findOrFail($data['organization_id']);
                        $featureName = $this->record->name;
                        $active = $data['is_active'];

                        if ($active) {
                            Feature::for($organization)->activate($featureName);
                        } else {
                            Feature::for($organization)->deactivate($featureName);
                        }

                        $this->record->update(['last_changed_by' => auth()->id()]);

                        ActivityLog::log(
                            'feature_flag_override',
                            "Feature '{$featureName}' ".($active ? 'activated' : 'deactivated')." for org '{$organization->name}'",
                        );

                        Notification::make()
                            ->success()
                            ->title("Override added for '{$organization->name}'")
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No overrides')
            ->emptyStateDescription('All organizations use the default feature resolution.')
            ->paginated(false);
    }
}
