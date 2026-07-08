<?php

namespace Database\Seeders;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\RestaurantTable;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\CompositeItemService;
use Illuminate\Database\Seeder;

/**
 * Demo dataset for the restaurant POS: a tenant with a store, two
 * terminals, three kitchen stations, fine-unit ingredients, two
 * made-to-order composites, twelve tables and sample reservations.
 *
 * Env-gated: never registered in DatabaseSeeder; refuses to run in
 * production. Invoke explicitly with
 *   php artisan db:seed --class=RestaurantDemoSeeder
 */
class RestaurantDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('RestaurantDemoSeeder is disabled in production.');

            return;
        }

        $role = Role::factory()->admin()->create(['name' => 'Demo Owner']);
        $owner = User::factory()->create([
            'name' => 'Demo Owner',
            'email' => 'demo-owner@apexresto.test',
            'role_id' => $role->id,
        ]);
        $owner->update(['user_id' => $owner->id]);
        $tenantId = $owner->id;

        $store = Store::factory()->create([
            'name' => 'APEX RESTO DEMO',
            'user_id' => $tenantId,
            'status' => true,
        ]);

        foreach ([1, 2] as $n) {
            Pos::create([
                'name' => 'Terminal '.$n,
                'store_id' => $store->id,
                'status' => true,
                'mac' => '00:00:00:00:00:0'.$n,
                'number' => $n,
                'user_id' => $tenantId,
                'reset_counter' => 1,
            ]);
        }

        $stations = collect(['Hot Kitchen', 'Cold Bar', 'Beverage'])
            ->mapWithKeys(fn ($name) => [$name => KitchenStation::factory()->create([
                'name' => $name,
                'store_id' => $store->id,
                'user_id' => $tenantId,
            ])]);

        $beverages = Category::factory()->create([
            'name' => 'BEVERAGES', 'status' => true, 'user_id' => $tenantId,
            'kitchen_station_id' => $stations['Beverage']->id,
        ]);
        $mains = Category::factory()->create([
            'name' => 'MAINS', 'status' => true, 'user_id' => $tenantId,
            'kitchen_station_id' => $stations['Hot Kitchen']->id,
        ]);
        $ingredientsCat = Category::factory()->create([
            'name' => 'INGREDIENTS', 'status' => true, 'user_id' => $tenantId,
        ]);

        // Ingredients stocked in fine units.
        $ingredients = [
            'ice' => ['Ice', 'g', 0.02, 100000],
            'coffee' => ['Coffee Beans', 'g', 1.20, 50000],
            'oat_milk' => ['Oat Milk', 'ml', 0.18, 80000],
            'pork_jowl' => ['Pork Jowl', 'g', 0.45, 60000],
            'margarine' => ['Margarine', 'g', 0.30, 20000],
            'egg' => ['Egg', 'pc', 8.00, 500],
            'onion' => ['Onion', 'g', 0.10, 30000],
            'garlic' => ['Garlic', 'g', 0.25, 10000],
        ];

        $items = [];
        foreach ($ingredients as $key => [$name, $uom, $cost, $stock]) {
            $item = Item::factory()->create([
                'name' => strtoupper($name),
                'category_id' => $ingredientsCat->id,
                'user_id' => $tenantId,
                'cost' => $cost,
                'price' => 0,
                'uom_label' => $uom,
                'status' => true,
                // Stock-only: ingredients live in recipes and inventory,
                // never on the waiter/POS menu.
                'show_in_pos' => false,
            ]);
            ItemStore::factory()->create([
                'item_id' => $item->id,
                'store_id' => $store->id,
                'stock' => $stock,
            ]);
            $items[$key] = $item;
        }

        $composites = new CompositeItemService;

        $latte = Item::factory()->create([
            'name' => 'ICED LATTE', 'category_id' => $beverages->id, 'user_id' => $tenantId,
            'price' => 130, 'status' => true,
        ]);
        $composites->syncComponents($latte, [
            ['component_item_id' => $items['ice']->id, 'qty' => 50],
            ['component_item_id' => $items['coffee']->id, 'qty' => 20],
            ['component_item_id' => $items['oat_milk']->id, 'qty' => 200],
        ], $tenantId);

        $sisig = Item::factory()->create([
            'name' => 'SISIG', 'category_id' => $mains->id, 'user_id' => $tenantId,
            'price' => 340, 'status' => true,
        ]);
        $composites->syncComponents($sisig, [
            ['component_item_id' => $items['pork_jowl']->id, 'qty' => 300],
            ['component_item_id' => $items['margarine']->id, 'qty' => 100],
            ['component_item_id' => $items['egg']->id, 'qty' => 1],
            ['component_item_id' => $items['onion']->id, 'qty' => 100],
            ['component_item_id' => $items['garlic']->id, 'qty' => 50],
        ], $tenantId);

        foreach (['Main Hall' => 6, 'Patio' => 6] as $area => $count) {
            for ($i = 1; $i <= $count; $i++) {
                RestaurantTable::factory()->create([
                    'name' => substr($area, 0, 1).$i,
                    'number' => (string) $i,
                    'area' => $area,
                    'seats' => $i % 2 === 0 ? 4 : 2,
                    'store_id' => $store->id,
                    'user_id' => $tenantId,
                ]);
            }
        }

        Reservation::factory()->count(5)->create([
            'user_id' => $tenantId,
            'store_id' => $store->id,
        ]);

        $this->command?->info("Restaurant demo seeded for tenant #{$tenantId} (demo-owner@apexresto.test).");
    }
}
