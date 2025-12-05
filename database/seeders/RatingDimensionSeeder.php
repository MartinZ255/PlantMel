<?php

namespace Database\Seeders;

use App\Models\RatingDimension;
use Illuminate\Database\Seeder;

class RatingDimensionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dimensions = [
            [
                'name'        => 'Schwierigkeit',
                'description' => 'Wie anspruchsvoll ist das Rezept in der Zubereitung?',
            ],
            [
                'name'        => 'Geschmack',
                'description' => 'Subjektive geschmackliche Bewertung des Rezepts.',
            ],
            [
                'name'        => 'Aufwand',
                'description' => 'Zeitlicher und organisatorischer Aufwand.',
            ],
            [
                'name'        => 'Gesamt',
                'description' => 'Gesamtbewertung des Rezepts.',
            ],
        ];

        foreach ($dimensions as $data) {
            RatingDimension::firstOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description']]
            );
        }
    }
}
