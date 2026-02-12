<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlansTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        DB::table('plans')->delete();

        $now = now();

        DB::table('plans')->insert([
            [
                'id' => 1,
                'name' => 'Basic',
                'description' => 'Everything you need to get started.',
                'features' => json_encode(['5 Projects', '1 Team Member', '5GB Storage', 'Email Support']),
                'limits' => json_encode([
                    'projects' => 5,
                    'team_members' => 1,
                    'storage_gb' => 5,
                    'api_keys' => 2,
                ]),
                'role_id' => 3,
                'default' => 1,
                'sort_order' => 1,
                'active' => 1,
                'monthly_price_id' => 'price_basic_monthly',
                'yearly_price_id' => 'price_basic_yearly',
                'monthly_price' => '9',
                'yearly_price' => '90',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Premium',
                'description' => 'For growing teams that need more power.',
                'features' => json_encode(['25 Projects', '5 Team Members', '50GB Storage', 'Priority Support', 'API Access']),
                'limits' => json_encode([
                    'projects' => 25,
                    'team_members' => 5,
                    'storage_gb' => 50,
                    'api_keys' => 10,
                ]),
                'role_id' => 4,
                'default' => 0,
                'sort_order' => 2,
                'active' => 1,
                'monthly_price_id' => 'price_premium_monthly',
                'yearly_price_id' => 'price_premium_yearly',
                'monthly_price' => '29',
                'yearly_price' => '290',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Pro',
                'description' => 'Unlimited access for scaling businesses.',
                'features' => json_encode(['Unlimited Projects', 'Unlimited Team Members', '500GB Storage', 'Priority Support', 'API Access', 'Custom Integrations']),
                'limits' => json_encode([
                    'projects' => -1,
                    'team_members' => -1,
                    'storage_gb' => 500,
                    'api_keys' => -1,
                ]),
                'role_id' => 5,
                'default' => 0,
                'sort_order' => 3,
                'active' => 1,
                'monthly_price_id' => 'price_pro_monthly',
                'yearly_price_id' => 'price_pro_yearly',
                'monthly_price' => '49',
                'yearly_price' => '490',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

    }
}
