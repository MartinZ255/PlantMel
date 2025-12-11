<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Vegane Zutaten, gruppiert nach Kategorienamen,
        // die zu deinem Category-Model passen sollten.
        // Passe die Kategorienamen ggf. an deine tatsächlichen Category-Datensätze an.
        $ingredients = [
            // =======================
            // Gemüse
            // =======================
            [
                'name' => 'Karotten',
                'description' => 'Süßliches Wurzelgemüse, vielseitig in Suppen, Eintöpfen und Ofengerichten.',
            ],
            [
                'name' => 'Zwiebeln',
                'description' => 'Basisaroma für fast alle herzhaften Gerichte.',
            ],
            [
                'name' => 'Knoblauch',
                'description' => 'Intensives Aroma, roh oder angebraten einsetzbar.',
            ],
            [
                'name' => 'Paprika (rot)',
                'description' => 'Süßliches Gemüse, ideal zum Rösten, Füllen oder für Pfannengerichte.',
            ],
            [
                'name' => 'Zucchini',
                'description' => 'Mildes Gemüse, gut für Pfannen, Ofengerichte und One-Pot-Gerichte.',
            ],
            [
                'name' => 'Brokkoli',
                'description' => 'Kreuzblütler-Gemüse, reich an Mikronährstoffen, perfekt zum Dämpfen oder Wokken.',
            ],
            [
                'name' => 'Spinat (frisch oder TK)',
                'description' => 'Blattgemüse, ideal für Currys, Pasta und Aufläufe.',
            ],
            [
                'name' => 'Champignons',
                'description' => 'Milde Pilze mit umami Aroma, vielseitig einsetzbar.',
            ],

            // =======================
            // Hülsenfrüchte
            // =======================
            [
                'name' => 'Kichererbsen (gekocht)',
                'description' => 'Proteinreiche Hülsenfrucht für Currys, Eintöpfe, Salate und Hummus.',
            ],
            [
                'name' => 'Rote Linsen',
                'description' => 'Schnell garende Linsen, ideal für Dal, Suppen und Eintöpfe.',
            ],
            [
                'name' => 'Braune Linsen',
                'description' => 'Formstabile Linsen, geeignet für Salate, Bolognese und Bratlinge.',
            ],
            [
                'name' => 'Schwarze Bohnen (gekocht)',
                'description' => 'Herzhafte Bohnen, perfekt für Bowls, Chili und Tacos.',
            ],
            [
                'name' => 'Weiße Bohnen (gekocht)',
                'description' => 'Milde Bohnen, gut für cremige Eintöpfe und Aufstriche.',
            ],

            // =======================
            // Getreide & Pseudogetreide
            // =======================
            [
                'name' => 'Vollkornpasta',
                'description' => 'Ballaststoffreiche Pasta als Basis für schnelle Alltagsgerichte.',
            ],
            [
                'name' => 'Basmatireis',
                'description' => 'Aromatischer Langkornreis, ideal für Currys und Pfannengerichte.',
            ],
            [
                'name' => 'Vollkornreis',
                'description' => 'Nussiger Reis mit höherem Ballaststoffgehalt, für Bowls und Eintöpfe.',
            ],
            [
                'name' => 'Quinoa',
                'description' => 'Glutenfreies Pseudogetreide, eiweißreich und ideal für Bowls und Salate.',
            ],
            [
                'name' => 'Haferflocken',
                'description' => 'Basis für Porridge, Crumble und Bratlings-Bindung.',
            ],

            // =======================
            // Nüsse & Samen
            // =======================
            [
                'name' => 'Cashewkerne',
                'description' => 'Milde Nüsse, ideal für cremige Soßen, Currys und Toppings.',
            ],
            [
                'name' => 'Mandeln',
                'description' => 'Allround-Nuss für Toppings, Saucen und Backen.',
            ],
            [
                'name' => 'Sonnenblumenkerne',
                'description' => 'Günstige Kerne für Toppings, Pesto und Aufstriche.',
            ],
            [
                'name' => 'Chiasamen',
                'description' => 'Ballaststoffreiche Samen, ideal für Pudding und Toppings.',
            ],
            [
                'name' => 'Leinsamen (geschrotet)',
                'description' => 'Omega-3-Quelle, geeignet als Eiersatz und für Bowls.',
            ],

            // =======================
            // Tofu & Alternativen
            // =======================
            [
                'name' => 'Naturtofu',
                'description' => 'Neutrale Proteinbasis, die gut Marinaden und Gewürze aufnimmt.',
            ],
            [
                'name' => 'Räuchertofu',
                'description' => 'Kräftig-aromatischer Tofu, ideal zum Anbraten und für Aufläufe.',
            ],
            [
                'name' => 'Tempeh',
                'description' => 'Fermentierte Sojabohnen, nussig und fest in der Konsistenz.',
            ],

            // =======================
            // Pflanzliche Milchprodukte
            // =======================
            [
                'name' => 'Haferdrink',
                'description' => 'Pflanzliche Milchalternative für Porridge, Kaffee und Soßen.',
            ],
            [
                'name' => 'Sojajoghurt natur',
                'description' => 'Neutraler Joghurtersatz, ideal für Bowls, Dips und Dressings.',
            ],
            [
                'name' => 'Pflanzliche Kochsahne',
                'description' => 'Zum Verfeinern von Soßen, Currys und Aufläufen.',
            ],

            // =======================
            // Öle & Fette
            // =======================
            [
                'name' => 'Olivenöl',
                'description' => 'Aromatisches Öl für Dressings, Marinaden und zum Braten bei mittlerer Hitze.',
            ],
            [
                'name' => 'Rapsöl',
                'description' => 'Neutraleres Öl zum Braten und Backen.',
            ],
            [
                'name' => 'Kokosöl',
                'description' => 'Für asiatisch angehauchte Gerichte und Backen.',
            ],

            // =======================
            // Saucen, Würzmittel & Basics
            // =======================
            [
                'name' => 'Sojasauce',
                'description' => 'Umami-Würze für Pfannengerichte, Bowls und Marinaden.',
            ],
            [
                'name' => 'Gemüsebrühe (Pulver oder Paste)',
                'description' => 'Basiswürze für Suppen, Eintöpfe und Saucen.',
            ],
            [
                'name' => 'Tomatenpassata',
                'description' => 'Grundlage für Saucen, Eintöpfe und Ofengerichte.',
            ],
            [
                'name' => 'Dosentomaten (stückig)',
                'description' => 'Praktische Basis für schnelle Pastasaucen und Eintöpfe.',
            ],
            [
                'name' => 'Hefeflocken',
                'description' => 'Käseartige Würze für Saucen, Pesto und Toppings.',
            ],

            // =======================
            // Süßungsmittel
            // =======================
            [
                'name' => 'Ahornsirup',
                'description' => 'Flüssige Süße für Dressings, Marinaden und Desserts.',
            ],
            [
                'name' => 'Agavendicksaft',
                'description' => 'Neutraler Süßmacher für Drinks, Bowls und Backen.',
            ],
        ];

        foreach ($ingredients as $data) {
            // Ingredient anlegen oder finden
            $ingredient = Ingredient::firstOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description']]
            );

        }
    }
}
