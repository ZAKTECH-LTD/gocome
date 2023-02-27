<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OnboardingsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('onboardings')->delete();
        
        \DB::table('onboardings')->insert(array (
            0 => 
            array (
                'id' => 1,
                'in_order' => 3,
                'title' => 'Browse through different vendors',
                'description' => 'Get your favorite meal/food/items from varieties of vendor',
                'type' => 'customer',
                'is_active' => 1,
                'created_at' => '2022-08-15 20:30:25',
                'updated_at' => '2022-08-15 20:31:32',
            ),
            1 => 
            array (
                'id' => 2,
                'in_order' => 1,
                'title' => 'Chat with vendor/delivery boy',
                'description' => 'Call/Chat with vendor/delivery boy for update about your order and more',
                'type' => 'customer',
                'is_active' => 1,
                'created_at' => '2022-08-15 20:30:52',
                'updated_at' => '2022-08-15 20:30:52',
            ),
            2 => 
            array (
                'id' => 3,
                'in_order' => 2,
                'title' => 'Delivery made easy',
                'description' => 'Get your ordered food/item or parcel delivered at a very fast, cheap and reliable way',
                'type' => 'customer',
                'is_active' => 1,
                'created_at' => '2022-08-15 20:31:11',
                'updated_at' => '2022-08-15 20:31:32',
            ),
            3 => 
            array (
                'id' => 4,
                'in_order' => 1,
                'title' => 'Delivery made easy',
                'description' => 'Get notified as soon as an order is available for delivery',
                'type' => 'driver',
                'is_active' => 1,
                'created_at' => '2022-08-15 20:35:14',
                'updated_at' => '2022-08-15 20:35:14',
            ),
            4 => 
            array (
                'id' => 5,
                'in_order' => 1,
                'title' => 'Chat with vendor/customer',
                'description' => 'Call/Chat with vendor/delivery boy for update about your order and more',
                'type' => 'driver',
                'is_active' => 1,
                'created_at' => '2022-08-15 20:35:35',
                'updated_at' => '2022-08-15 20:35:35',
            ),
            5 => 
            array (
                'id' => 6,
                'in_order' => 1,
                'title' => 'Earning',
                'description' => 'You get commissions from every delivery made',
                'type' => 'driver',
                'is_active' => 1,
                'created_at' => '2022-08-15 20:35:58',
                'updated_at' => '2022-08-15 20:35:58',
            ),
            6 => 
            array (
                'id' => 7,
                'in_order' => 1,
                'title' => 'Take Orders',
                'description' => 'Get notified as soon as an order is place',
                'type' => 'vendor',
                'is_active' => 1,
                'created_at' => '2022-08-15 20:36:53',
                'updated_at' => '2022-08-15 20:36:53',
            ),
            7 => 
            array (
                'id' => 8,
                'in_order' => 1,
                'title' => 'Chat with driver/customer',
                'description' => 'Call/Chat with driver/delivery boy for update about your order and more',
                'type' => 'vendor',
                'is_active' => 1,
                'created_at' => '2022-08-15 20:37:12',
                'updated_at' => '2022-08-15 20:37:12',
            ),
            8 => 
            array (
                'id' => 9,
                'in_order' => 1,
                'title' => 'Earning',
                'description' => 'See your earning increase with demand',
                'type' => 'vendor',
                'is_active' => 1,
                'created_at' => '2022-08-15 20:37:27',
                'updated_at' => '2022-08-15 20:37:27',
            ),
        ));
        
        
    }
}