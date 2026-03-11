<?php

namespace Database\Seeders;

use App\Enums\FeatureFlagType;
use App\Models\FeatureDefinition;
use Illuminate\Database\Seeder;

class FeatureDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FeatureDefinition::updateOrCreate(
            ['name' => 'ai-reports'],
            [
                'type' => FeatureFlagType::PlanGated,
                'description' => 'AI-powered report generation. Available on Pro and Enterprise plans.',
            ],
        );

        FeatureDefinition::updateOrCreate(
            ['name' => 'new-editor'],
            [
                'type' => FeatureFlagType::Rollout,
                'description' => 'New rich text editor. Rolling out to 10% of organizations.',
            ],
        );

        FeatureDefinition::updateOrCreate(
            ['name' => 'maintenance-mode'],
            [
                'type' => FeatureFlagType::KillSwitch,
                'description' => 'Emergency kill switch for maintenance. Disables all non-essential features when activated.',
                'is_active' => false,
            ],
        );
    }
}
