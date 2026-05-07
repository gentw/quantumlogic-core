<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PackagesTableSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $packages = [
            [
                'name' => 'Starter',
                'description' => 'Basic package for testing and small projects.',
                'price_monthly' => 19.99,
                'price_yearly' => 199.99,
                'features' => json_encode([
                    'max_origins' => 1,
                    'load_balancing' => false,
                    'AI_logs' => false,
                    'TLS_mode' => 'auto',
                ]),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Professional',
                'description' => 'For growing projects, includes load balancing and AI logs.',
                'price_monthly' => 59.99,
                'price_yearly' => 599.99,
                'features' => json_encode([
                    'max_origins' => 3,
                    'load_balancing' => true,
                    'AI_logs' => true,
                    'TLS_mode' => 'manual',
                ]),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Full-featured package for large projects and full control.',
                'price_monthly' => 149.99,
                'price_yearly' => 1499.99,
                'features' => json_encode([
                    'max_origins' => 10,
                    'load_balancing' => true,
                    'AI_logs' => true,
                    'TLS_mode' => 'manual',
                ]),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('packages')->insert($packages);
    }
}
