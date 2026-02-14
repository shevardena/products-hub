<?php

namespace Database\Seeders;

use App\Models\MerchantApi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MerchantApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MerchantApi::create([
            'merchant_id' => 1,
            'name' => 'Products API',
            'base_url' => 'http://158.220.112.169/test.php',
            'entity_type' => 'products',
            'contains_category_data' => true,
            'category_data_path' => 'category_id',
            'image_data_path' => 'images',
            'field_mappings' => [
                [
                    'database_column' => 'name',
                    'response_field' => 'product'
                ],
                [
                    'database_column' => 'description',
                    'response_field' => 'description'
                ],
                [
                    'database_column' => 'sku',
                    'response_field' => 'code'
                ],
                [
                    'database_column' => 'price',
                    'response_field' => 'price'
                ],
                [
                    'database_column' => 'quantity',
                    'response_field' => 'Quantity'
                ]
            ],
            'auth_type' => 'none',
            'auth_payload' => null,
            'sync_interval_minutes' => 60,
            'is_active' => true
        ]);
    }
}
