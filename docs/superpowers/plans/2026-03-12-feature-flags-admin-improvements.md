# Feature Flags Admin Improvements Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the flat Feature Flags page with a proper Filament Resource (list + view pages) and add an Organization features tab, enabling admins to manage feature flags from both feature-first and org-first perspectives.

**Architecture:** Convert FeatureDefinition into a Filament Resource with a List page (kill switch widget + feature table) and a View page (infolist + org overrides widget). Add a features widget to the Organization Edit page. Make rollout percentage admin-controllable via a new DB column.

**Tech Stack:** Filament v4 Resource, Infolist, Widgets with InteractsWithTable, Laravel Pennant, Pest

**Spec:** `docs/superpowers/specs/2026-03-12-feature-flags-admin-improvements-design.md`

---

## Chunk 1: Foundation (Tasks 1-2)

### Task 1: Add rollout_percentage to feature_definitions

**Files:**
- Create: `database/migrations/xxxx_add_rollout_percentage_to_feature_definitions_table.php`
- Modify: `app/Models/FeatureDefinition.php`
- Modify: `database/seeders/FeatureDefinitionSeeder.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration add_rollout_percentage_to_feature_definitions_table --no-interaction
```

Migration content:
```php
public function up(): void
{
    Schema::table('feature_definitions', function (Blueprint $table) {
        $table->unsignedSmallInteger('rollout_percentage')->nullable()->after('is_active');
    });
}

public function down(): void
{
    Schema::table('feature_definitions', function (Blueprint $table) {
        $table->dropColumn('rollout_percentage');
    });
}
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 3: Update FeatureDefinition model casts**

In `app/Models/FeatureDefinition.php`, add `'rollout_percentage' => 'integer'` to casts:

```php
protected function casts(): array
{
    return [
        'type' => FeatureFlagType::class,
        'is_active' => 'boolean',
        'rollout_percentage' => 'integer',
    ];
}
```

- [ ] **Step 4: Update seeder**

In `database/seeders/FeatureDefinitionSeeder.php`, add `rollout_percentage` to the `new-editor` definition:

```php
FeatureDefinition::firstOrCreate(
    ['name' => 'new-editor'],
    [
        'type' => FeatureFlagType::Rollout,
        'description' => 'New rich text editor. Rolling out to 10% of organizations.',
        'is_active' => true,
        'rollout_percentage' => 10,
    ],
);
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*add_rollout_percentage* app/Models/FeatureDefinition.php database/seeders/FeatureDefinitionSeeder.php
git commit -m "feat(05): add rollout_percentage column to feature_definitions"
```

### Task 2: Make NewEditor read rollout percentage from DB

**Files:**
- Modify: `app/Features/NewEditor.php`

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/FeatureFlagsTest.php`:

```php
test('rollout feature uses configurable percentage from database', function () {
    $definition = FeatureDefinition::create([
        'name' => 'new-editor',
        'type' => FeatureFlagType::Rollout,
        'is_active' => true,
        'rollout_percentage' => 100,
    ]);

    $org = Organization::create([
        'name' => 'Rollout Test Org',
        'slug' => 'rollout-test-org',
    ]);

    Feature::flushCache();
    Feature::purge('new-editor');

    // With 100% rollout, feature should always be active
    expect(Feature::for($org)->active('new-editor'))->toBeTrue();
});

test('rollout feature defaults to 0 when no definition exists', function () {
    $org = Organization::create([
        'name' => 'No Def Org',
        'slug' => 'no-def-org',
    ]);

    Feature::flushCache();
    Feature::purge('new-editor');

    // No definition means 0% rollout (safe default)
    expect(Feature::for($org)->active('new-editor'))->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="rollout feature uses configurable|rollout feature defaults"
```

- [ ] **Step 3: Update NewEditor feature class**

Replace `app/Features/NewEditor.php`:

```php
<?php

namespace App\Features;

use App\Models\FeatureDefinition;
use Illuminate\Support\Lottery;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Feature;

#[\Laravel\Pennant\Attributes\Name('new-editor')]
class NewEditor
{
    public function resolve(mixed $scope): bool
    {
        $definition = FeatureDefinition::where('name', 'new-editor')->first();

        $percentage = $definition?->rollout_percentage ?? 0;

        if ($percentage <= 0) {
            return false;
        }

        if ($percentage >= 100) {
            return true;
        }

        return Lottery::odds($percentage, 100)->choose();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --compact --filter="rollout feature uses configurable|rollout feature defaults"
```

- [ ] **Step 5: Run all feature flag tests**

```bash
php artisan test --compact --filter=FeatureFlags
```

- [ ] **Step 6: Commit**

```bash
git add app/Features/NewEditor.php tests/Feature/FeatureFlagsTest.php
git commit -m "feat(05): make rollout percentage configurable from database"
```

---

## Chunk 2: FeatureDefinition Resource (Tasks 3-4)

### Task 3: Create FeatureDefinitionResource with List page

**Files:**
- Create: `app/Filament/Resources/FeatureDefinitions/FeatureDefinitionResource.php`
- Create: `app/Filament/Resources/FeatureDefinitions/Pages/ListFeatureDefinitions.php`
- Create: `app/Filament/Widgets/KillSwitchesWidget.php`
- Create: `resources/views/filament/widgets/kill-switches.blade.php`

- [ ] **Step 1: Create resource directory structure**

```bash
mkdir -p app/Filament/Resources/FeatureDefinitions/Pages
```

- [ ] **Step 2: Create FeatureDefinitionResource**

Create `app/Filament/Resources/FeatureDefinitions/FeatureDefinitionResource.php`:

```php
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
                            "Feature '{$record->name}' " . ($state ? 'activated' : 'deactivated'),
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
                            "Feature '{$record->name}' " . ($active ? 'activated' : 'deactivated') . " for org '{$organization->name}'",
                        );

                        Notification::make()
                            ->success()
                            ->title("Feature '{$record->name}' " . ($active ? 'activated' : 'deactivated') . " for '{$organization->name}'")
                            ->send();
                    }),
            ])
            ->recordUrl(fn (FeatureDefinition $record): string => static::getUrl('view', ['record' => $record]))
            ->searchable();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatureDefinitions::route('/'),
            'view' => ViewFeatureDefinition::route('/{record}'),
        ];
    }
}
```

- [ ] **Step 3: Create ListFeatureDefinitions page**

Create `app/Filament/Resources/FeatureDefinitions/Pages/ListFeatureDefinitions.php`:

```php
<?php

namespace App\Filament\Resources\FeatureDefinitions\Pages;

use App\Filament\Resources\FeatureDefinitions\FeatureDefinitionResource;
use App\Filament\Widgets\KillSwitchesWidget;
use Filament\Resources\Pages\ListRecords;

class ListFeatureDefinitions extends ListRecords
{
    protected static string $resource = FeatureDefinitionResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            KillSwitchesWidget::class,
        ];
    }
}
```

- [ ] **Step 4: Create KillSwitchesWidget**

Create `app/Filament/Widgets/KillSwitchesWidget.php`:

Move the kill switch logic from the old FeatureFlagsPage into a reusable widget. This widget uses `InteractsWithActions` for the Filament confirmation modal.

```php
<?php

namespace App\Filament\Widgets;

use App\Enums\FeatureFlagType;
use App\Models\FeatureDefinition;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Laravel\Pennant\Feature;
use Wave\ActivityLog;

class KillSwitchesWidget extends Widget implements HasActions
{
    use InteractsWithActions;

    protected static string $view = 'filament.widgets.kill-switches';

    protected int|string|array $columnSpan = 'full';

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
                    "Kill switch '{$definition->name}' " . ($newState ? 'activated' : 'deactivated'),
                );

                Notification::make()
                    ->success()
                    ->title("Kill switch '{$definition->name}' " . ($newState ? 'activated' : 'deactivated'))
                    ->send();
            });
    }
}
```

- [ ] **Step 5: Create KillSwitches widget Blade view**

Create `resources/views/filament/widgets/kill-switches.blade.php`:

Copy the kill switch section from the existing feature-flags blade view (the `<x-filament::section>` block).

```blade
<x-filament-widgets::widget>
    @php
        $killSwitches = $this->getKillSwitches();
    @endphp

    @if ($killSwitches->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-shield-exclamation"
                        class="h-5 w-5 text-danger-500"
                    />
                    Kill Switches
                </div>
            </x-slot>

            <x-slot name="description">
                Kill switches immediately affect all users. Activating a kill switch blocks access to the feature globally.
            </x-slot>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($killSwitches as $switch)
                    @php
                        $isActive = $this->isKillSwitchActive($switch);
                    @endphp
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $switch->name }}
                                </span>
                                @if ($isActive)
                                    <x-filament::badge color="danger" size="sm">
                                        ENGAGED
                                    </x-filament::badge>
                                @else
                                    <x-filament::badge color="success" size="sm">
                                        OFF
                                    </x-filament::badge>
                                @endif
                            </div>
                            @if ($switch->description)
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $switch->description }}
                                </p>
                            @endif
                            @if ($switch->changedBy)
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                    Last changed by {{ $switch->changedBy->name }} &middot; {{ $switch->updated_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                        <div class="shrink-0">
                            {{ ($this->toggleKillSwitchAction)(['id' => $switch->id, 'active' => $isActive, 'name' => $switch->name]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament-actions::modals />
    @endif
</x-filament-widgets::widget>
```

- [ ] **Step 6: Verify list page loads in browser**

Navigate to `/admin/feature-flags` and verify:
- Kill switches widget shows at top
- Feature table shows below
- Row click navigates to view page (will 404 until Task 4)

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/FeatureDefinitions/ app/Filament/Widgets/KillSwitchesWidget.php resources/views/filament/widgets/kill-switches.blade.php
git commit -m "feat(05): create FeatureDefinitionResource with list page and kill switch widget"
```

### Task 4: Create View page with infolist and org overrides widget

**Files:**
- Create: `app/Filament/Resources/FeatureDefinitions/Pages/ViewFeatureDefinition.php`
- Create: `app/Filament/Widgets/OrganizationOverridesWidget.php`
- Create: `resources/views/filament/widgets/organization-overrides.blade.php`
- Modify: `app/Filament/Resources/FeatureDefinitions/FeatureDefinitionResource.php` (add edit rollout action)

- [ ] **Step 1: Create ViewFeatureDefinition page**

Create `app/Filament/Resources/FeatureDefinitions/Pages/ViewFeatureDefinition.php`:

```php
<?php

namespace App\Filament\Resources\FeatureDefinitions\Pages;

use App\Filament\Resources\FeatureDefinitions\FeatureDefinitionResource;
use App\Filament\Widgets\OrganizationOverridesWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewFeatureDefinition extends ViewRecord
{
    protected static string $resource = FeatureDefinitionResource::class;

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
```

- [ ] **Step 2: Create OrganizationOverridesWidget**

Create `app/Filament/Widgets/OrganizationOverridesWidget.php`:

This widget queries Pennant's `features` table for all Organization-scoped overrides for the current feature definition. It uses `InteractsWithTable` to display them and provides toggle/remove actions.

```php
<?php

namespace App\Filament\Widgets;

use App\Models\FeatureDefinition;
use App\Models\Organization;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Wave\ActivityLog;

class OrganizationOverridesWidget extends Widget implements HasActions, HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;

    protected static string $view = 'filament.widgets.organization-overrides';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function table(Table $table): Table
    {
        $featureName = $this->record?->name;

        return $table
            ->query(
                DB::table('features')
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
                            "Feature '{$featureName}' " . ($currentlyActive ? 'deactivated' : 'activated') . " for org '{$org->name}'",
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
            ->description('Organizations with explicit feature state overrides. Organizations not listed resolve using default rules.')
            ->emptyStateHeading('No overrides')
            ->emptyStateDescription('All organizations use the default feature resolution.')
            ->paginated(false);
    }

    public function addOverrideAction(): Action
    {
        return Action::make('addOverride')
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
                    "Feature '{$featureName}' " . ($active ? 'activated' : 'deactivated') . " for org '{$organization->name}'",
                );

                Notification::make()
                    ->success()
                    ->title("Override added for '{$organization->name}'")
                    ->send();
            });
    }
}
```

- [ ] **Step 3: Create organization-overrides widget blade view**

Create `resources/views/filament/widgets/organization-overrides.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Organization Overrides
        </x-slot>

        <x-slot name="headerEnd">
            {{ $this->addOverrideAction }}
        </x-slot>

        <x-slot name="description">
            Organizations with explicit feature state overrides. Others use default resolution.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
```

- [ ] **Step 4: Add "Edit Rollout %" header action to View page**

In `ViewFeatureDefinition.php`, add a header action for editing rollout percentage:

```php
use App\Enums\FeatureFlagType;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Laravel\Pennant\Feature;

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
```

- [ ] **Step 5: Verify view page in browser**

Navigate to `/admin/feature-flags/{id}` and verify:
- Infolist shows feature metadata
- Organization Overrides widget shows below
- "Add Override" action works
- For rollout features, "Edit Rollout %" header action is visible

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/FeatureDefinitions/Pages/ViewFeatureDefinition.php app/Filament/Widgets/OrganizationOverridesWidget.php resources/views/filament/widgets/organization-overrides.blade.php
git commit -m "feat(05): add view page with org overrides widget and rollout editing"
```

---

## Chunk 3: Organization Features + Cleanup (Tasks 5-7)

### Task 5: Add Features widget to Organization Edit page

**Files:**
- Create: `app/Filament/Widgets/OrganizationFeaturesWidget.php`
- Create: `resources/views/filament/widgets/organization-features.blade.php`
- Modify: `app/Filament/Resources/Organizations/Pages/EditOrganization.php`

- [ ] **Step 1: Create OrganizationFeaturesWidget**

Create `app/Filament/Widgets/OrganizationFeaturesWidget.php`:

This widget queries Pennant's `features` table for all feature overrides scoped to the current organization.

```php
<?php

namespace App\Filament\Widgets;

use App\Models\FeatureDefinition;
use App\Models\Organization;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Wave\ActivityLog;

class OrganizationFeaturesWidget extends Widget implements HasActions, HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;

    protected static string $view = 'filament.widgets.organization-features';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function table(Table $table): Table
    {
        $scope = 'App\\Models\\Organization|' . $this->record?->id;

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
                    ->action(function ($record): void {
                        $org = $this->record;
                        $currentlyActive = $record->value === 'true';

                        if ($currentlyActive) {
                            Feature::for($org)->deactivate($record->name);
                        } else {
                            Feature::for($org)->activate($record->name);
                        }

                        Notification::make()
                            ->success()
                            ->title("Feature '{$record->name}' " . ($currentlyActive ? 'deactivated' : 'activated'))
                            ->send();
                    }),
                \Filament\Actions\Action::make('remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This feature will revert to default resolution for this organization.')
                    ->action(function ($record): void {
                        $org = $this->record;
                        Feature::for($org)->forget($record->name);

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
                    "Feature '{$featureName}' " . ($active ? 'activated' : 'deactivated') . " for org '{$org->name}'",
                );

                Notification::make()
                    ->success()
                    ->title("Feature '{$featureName}' " . ($active ? 'activated' : 'deactivated'))
                    ->send();
            });
    }
}
```

- [ ] **Step 2: Create organization-features widget blade view**

Create `resources/views/filament/widgets/organization-features.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Feature Overrides
        </x-slot>

        <x-slot name="headerEnd">
            {{ $this->setFeatureAction }}
        </x-slot>

        <x-slot name="description">
            Explicit feature flag overrides. Features not listed use default resolution.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
```

- [ ] **Step 3: Add widget to Organization Edit page**

In `app/Filament/Resources/Organizations/Pages/EditOrganization.php`, add:

```php
use App\Filament\Widgets\OrganizationFeaturesWidget;

protected function getFooterWidgets(): array
{
    return [
        OrganizationFeaturesWidget::class,
    ];
}

public function getFooterWidgetsColumns(): int|string|array
{
    return 1;
}
```

- [ ] **Step 4: Verify in browser**

Navigate to `/admin/organizations/{id}/edit` and verify:
- Feature Overrides widget shows at bottom
- "Set Feature" action works
- Toggle and remove actions work

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets/OrganizationFeaturesWidget.php resources/views/filament/widgets/organization-features.blade.php app/Filament/Resources/Organizations/Pages/EditOrganization.php
git commit -m "feat(05): add feature overrides widget to organization edit page"
```

### Task 6: Delete old FeatureFlagsPage and clean up

**Files:**
- Delete: `app/Filament/Pages/FeatureFlagsPage.php`
- Delete: `resources/views/filament/pages/feature-flags.blade.php`

- [ ] **Step 1: Delete old files**

```bash
rm app/Filament/Pages/FeatureFlagsPage.php
rm resources/views/filament/pages/feature-flags.blade.php
```

- [ ] **Step 2: Verify no broken references**

```bash
grep -r "FeatureFlagsPage" --include="*.php" app/ tests/ resources/
```

If test files reference `FeatureFlagsPage`, they need updating (Task 7).

- [ ] **Step 3: Commit**

```bash
git add -u app/Filament/Pages/FeatureFlagsPage.php resources/views/filament/pages/feature-flags.blade.php
git commit -m "chore(05): delete old FeatureFlagsPage replaced by FeatureDefinitionResource"
```

### Task 7: Update tests

**Files:**
- Modify: `tests/Feature/FeatureFlagsPageTest.php` (rewrite for new Resource)

- [ ] **Step 1: Rewrite FeatureFlagsPageTest for new Resource**

Update `tests/Feature/FeatureFlagsPageTest.php` to test the new Resource list and view pages:

Key test cases:
- List page renders with kill switch widget
- Kill switch toggle action works (via widget)
- Feature table excludes kill switches
- Feature table has correct columns and filter
- Override for org action works on list page
- View page renders with infolist
- View page shows org overrides
- Edit rollout % action works on view page
- Add override action works on view page

Replace all `livewire(FeatureFlagsPage::class)` with the appropriate new page class:
- `livewire(ListFeatureDefinitions::class)` for list page tests
- `livewire(ViewFeatureDefinition::class, ['record' => $id])` for view page tests
- `livewire(KillSwitchesWidget::class)` for kill switch tests

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=FeatureFlags
```

- [ ] **Step 3: Run NoNativeConfirmDialogs test**

```bash
php artisan test --compact --filter=NoNativeConfirmDialogs
```

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/FeatureFlagsPageTest.php
git commit -m "test(05): update feature flags tests for new resource and widgets"
```

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Final commit if Pint changed anything**

```bash
git add -u && git commit -m "style(05): apply pint formatting"
```
