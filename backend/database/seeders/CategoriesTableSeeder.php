<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoriesTableSeeder extends Seeder
{
    /**
     * Hierarchical category tree: parent => [children].
     *
     * @var array<string, array<int, string>>
     */
    protected array $tree = [
        'Electronics' => ['Mobile Phones', 'Laptops', 'Tablets', 'Headphones', 'Chargers', 'Smartwatches'],
        'Documents' => ['ID Cards', 'Passports', 'Certificates', 'Notebooks'],
        'Accessories' => ['Wallets', 'Keys', 'Bags', 'Jewelry', 'Eyeglasses'],
        'Personal Items' => ['Clothing', 'Umbrellas', 'Water Bottles'],
        'Books & Stationery' => ['Textbooks', 'Notebooks', 'Pens'],
        'Others' => ['Pets', 'Miscellaneous'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->tree as $parentName => $children) {
            $parent = Category::updateOrCreate(
                ['slug' => str($parentName)->slug()],
                [
                    'name' => $parentName,
                    'parent_id' => null,
                    'is_active' => true,
                ]
            );

            foreach ($children as $childName) {
                Category::updateOrCreate(
                    ['slug' => str($childName)->slug()],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command?->info('Categories seeded: '.Category::count().' total ('.count($this->tree).' parents).');
    }
}
