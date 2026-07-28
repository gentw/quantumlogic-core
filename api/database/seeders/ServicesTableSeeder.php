<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicesTableSeeder extends Seeder
{
    /**
     * The real QuantumLogic catalogue. Prices are net (EUR); VAT is resolved
     * per line at invoice time, 20% AT standard rate as the default.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $services = [
            [
                'name' => 'Website Development',
                'slug' => 'website-development',
                'description' => 'Design and build of a marketing or brochure website, including CMS setup and launch.',
                'category' => 'development',
                'billing_type' => 'milestone',
                'default_price_net' => 4800.00,
                'default_billing_interval' => null,
                'vat_rate' => 20.00,
                'supports_deposit' => true,
                'default_deposit_percent' => 50.00,
                'is_publicly_orderable' => true,
                'active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Web Application',
                'slug' => 'web-application',
                'description' => 'Custom web application development, scoped and quoted per project.',
                'category' => 'development',
                'billing_type' => 'milestone',
                'default_price_net' => 12000.00,
                'default_billing_interval' => null,
                'vat_rate' => 20.00,
                'supports_deposit' => true,
                'default_deposit_percent' => 50.00,
                'is_publicly_orderable' => false,
                'active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'SEO Retainer',
                'slug' => 'seo-retainer',
                'description' => 'Ongoing search optimisation: audits, content plan, link building, monthly reporting.',
                'category' => 'marketing',
                'billing_type' => 'recurring',
                'default_price_net' => 590.00,
                'default_billing_interval' => 'monthly',
                'vat_rate' => 20.00,
                'supports_deposit' => false,
                'default_deposit_percent' => null,
                'is_publicly_orderable' => true,
                'active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Hosting',
                'slug' => 'hosting',
                'description' => 'Managed hosting: server, TLS, backups, monitoring and updates.',
                'category' => 'operations',
                'billing_type' => 'recurring',
                'default_price_net' => 39.00,
                'default_billing_interval' => 'monthly',
                'vat_rate' => 20.00,
                'supports_deposit' => false,
                'default_deposit_percent' => null,
                'is_publicly_orderable' => true,
                'active' => true,
                'sort_order' => 40,
            ],
            [
                'name' => 'Maintenance & Support',
                'slug' => 'maintenance-support',
                'description' => 'Retained maintenance: updates, small changes, priority support with a monthly hour budget.',
                'category' => 'operations',
                'billing_type' => 'recurring',
                'default_price_net' => 190.00,
                'default_billing_interval' => 'monthly',
                'vat_rate' => 20.00,
                'supports_deposit' => false,
                'default_deposit_percent' => null,
                'is_publicly_orderable' => true,
                'active' => true,
                'sort_order' => 50,
            ],
            [
                'name' => 'Consulting',
                'slug' => 'consulting',
                'description' => 'Technical and product consulting, billed per engagement.',
                'category' => 'consulting',
                'billing_type' => 'one_off',
                'default_price_net' => 140.00,
                'default_billing_interval' => null,
                'vat_rate' => 20.00,
                'supports_deposit' => false,
                'default_deposit_percent' => null,
                'is_publicly_orderable' => false,
                'active' => true,
                'sort_order' => 60,
            ],
        ];

        foreach ($services as $service) {
            DB::table('services')->updateOrInsert(
                ['slug' => $service['slug']],
                $service + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
