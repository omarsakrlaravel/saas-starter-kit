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
                'name' => 'Basic',
                'stripe_product_id' => 'prod_TyJPoGuYkQuCii',
                'description' => 'Everything you need to get started.',
                'features' => json_encode(['5 Projects', '1 Team Member', '5GB Storage', 'Email Support']),
                'limits' => json_encode([
                    'projects' => 5,
                    'team_members' => 1,
                    'storage_gb' => 5,
                    'api_keys' => 2,
                ]),
                'default' => 1,
                'sort_order' => 1,
                'active' => 1,
                'monthly_price_id' => 'price_1T0Mn7P9lS19VIQQsm4gXt42',
                'yearly_price_id' => 'price_1T0Mn9P9lS19VIQQNgGXAJtT',
                'monthly_price' => '9',
                'yearly_price' => '90',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Premium',
                'stripe_product_id' => 'prod_TyJPG1DsinOeri',
                'description' => 'For growing teams that need more power.',
                'features' => json_encode(['25 Projects', '5 Team Members', '50GB Storage', 'Priority Support', 'API Access']),
                'limits' => json_encode([
                    'projects' => 25,
                    'team_members' => 5,
                    'storage_gb' => 50,
                    'api_keys' => 10,
                ]),
                'default' => 0,
                'sort_order' => 2,
                'active' => 1,
                'monthly_price_id' => 'price_1T0MnBP9lS19VIQQRlC6oCwR',
                'yearly_price_id' => 'price_1T0MnCP9lS19VIQQtXQlwiCT',
                'monthly_price' => '29',
                'yearly_price' => '290',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Pro',
                'stripe_product_id' => 'prod_TyJP13Rtgpsuh3',
                'description' => 'Unlimited access for scaling businesses.',
                'features' => json_encode(['Unlimited Projects', 'Unlimited Team Members', '500GB Storage', 'Priority Support', 'API Access', 'Custom Integrations']),
                'limits' => json_encode([
                    'projects' => -1,
                    'team_members' => -1,
                    'storage_gb' => 500,
                    'api_keys' => -1,
                ]),
                'default' => 0,
                'sort_order' => 3,
                'active' => 1,
                'monthly_price_id' => 'price_1T0MnEP9lS19VIQQvtxHhfPX',
                'yearly_price_id' => 'price_1T0MnGP9lS19VIQQM46WPeFY',
                'monthly_price' => '49',
                'yearly_price' => '490',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

    }
}
