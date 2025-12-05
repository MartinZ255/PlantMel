<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Frühstück',
                'description' => 'Rezepte für den Start in den Tag.',
            ],
            [
                'name'        => 'Mittagessen',
                'description' => 'Herzhafte Gerichte für die Mittagspause.',
            ],
            [
                'name'        => 'Abendessen',
                'description' => 'Warme Gerichte für den Abend.',
            ],
            [
                'name'        => 'Dessert',
                'description' => 'Süße Nachspeisen und Nachtische.',
            ],
            [
                'name'        => 'Snack',
                'description' => 'Kleine Snacks für zwischendurch.',
            ],
            [
                'name'        => 'Vegetarisch',
                'description' => 'Rezepte ohne Fleisch.',
            ],
            [
                'name'        => 'Low Carb',
                'description' => 'Kohlenhydratarme Gerichte.',
            ],
        ];

        foreach ($categories as $data) {
            Category::firstOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description']]
            );
        }
    }
}
