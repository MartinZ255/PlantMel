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
                'categories' => ['Gemüse'],
            ],
            [
                'name' => 'Zwiebeln',
                'description' => 'Basisaroma für fast alle herzhaften Gerichte.',
                'categories' => ['Gemüse', 'Aromageber'],
            ],
            [
                'name' => 'Knoblauch',
                'description' => 'Intensives Aroma, roh oder angebraten einsetzbar.',
                'categories' => ['Gemüse', 'Aromageber'],
            ],
            [
                'name' => 'Paprika (rot)',
                'description' => 'Süßliches Gemüse, ideal zum Rösten, Füllen oder für Pfannengerichte.',
                'categories' => ['Gemüse'],
            ],
            [
                'name' => 'Zucchini',
                'description' => 'Mildes Gemüse, gut für Pfannen, Ofengerichte und One-Pot-Gerichte.',
                'categories' => ['Gemüse'],
            ],
            [
                'name' => 'Brokkoli',
                'description' => 'Kreuzblütler-Gemüse, reich an Mikronährstoffen, perfekt zum Dämpfen oder Wokken.',
                'categories' => ['Gemüse'],
            ],
            [
                'name' => 'Spinat (frisch oder TK)',
                'description' => 'Blattgemüse, ideal für Currys, Pasta und Aufläufe.',
                'categories' => ['Gemüse', 'Blattgemüse'],
            ],
            [
                'name' => 'Champignons',
                'description' => 'Milde Pilze mit umami Aroma, vielseitig einsetzbar.',
                'categories' => ['Gemüse', 'Pilze'],
            ],

            // =======================
            // Hülsenfrüchte
            // =======================
            [
                'name' => 'Kichererbsen (gekocht)',
                'description' => 'Proteinreiche Hülsenfrucht für Currys, Eintöpfe, Salate und Hummus.',
                'categories' => ['Hülsenfrüchte', 'Proteinquelle'],
            ],
            [
                'name' => 'Rote Linsen',
                'description' => 'Schnell garende Linsen, ideal für Dal, Suppen und Eintöpfe.',
                'categories' => ['Hülsenfrüchte', 'Proteinquelle'],
            ],
            [
                'name' => 'Braune Linsen',
                'description' => 'Formstabile Linsen, geeignet für Salate, Bolognese und Bratlinge.',
                'categories' => ['Hülsenfrüchte', 'Proteinquelle'],
            ],
            [
                'name' => 'Schwarze Bohnen (gekocht)',
                'description' => 'Herzhafte Bohnen, perfekt für Bowls, Chili und Tacos.',
                'categories' => ['Hülsenfrüchte', 'Proteinquelle'],
            ],
            [
                'name' => 'Weiße Bohnen (gekocht)',
                'description' => 'Milde Bohnen, gut für cremige Eintöpfe und Aufstriche.',
                'categories' => ['Hülsenfrüchte', 'Proteinquelle'],
            ],

            // =======================
            // Getreide & Pseudogetreide
            // =======================
            [
                'name' => 'Vollkornpasta',
                'description' => 'Ballaststoffreiche Pasta als Basis für schnelle Alltagsgerichte.',
                'categories' => ['Getreide', 'Sättigungsbeilage'],
            ],
            [
                'name' => 'Basmatireis',
                'description' => 'Aromatischer Langkornreis, ideal für Currys und Pfannengerichte.',
                'categories' => ['Getreide', 'Sättigungsbeilage'],
            ],
            [
                'name' => 'Vollkornreis',
                'description' => 'Nussiger Reis mit höherem Ballaststoffgehalt, für Bowls und Eintöpfe.',
                'categories' => ['Getreide', 'Sättigungsbeilage'],
            ],
            [
                'name' => 'Quinoa',
                'description' => 'Glutenfreies Pseudogetreide, eiweißreich und ideal für Bowls und Salate.',
                'categories' => ['Pseudogetreide', 'Glutenfrei', 'Proteinquelle'],
            ],
            [
                'name' => 'Haferflocken',
                'description' => 'Basis für Porridge, Crumble und Bratlings-Bindung.',
                'categories' => ['Getreide', 'Frühstück'],
            ],

            // =======================
            // Nüsse & Samen
            // =======================
            [
                'name' => 'Cashewkerne',
                'description' => 'Milde Nüsse, ideal für cremige Soßen, Currys und Toppings.',
                'categories' => ['Nüsse & Samen', 'Proteinquelle'],
            ],
            [
                'name' => 'Mandeln',
                'description' => 'Allround-Nuss für Toppings, Saucen und Backen.',
                'categories' => ['Nüsse & Samen'],
            ],
            [
                'name' => 'Sonnenblumenkerne',
                'description' => 'Günstige Kerne für Toppings, Pesto und Aufstriche.',
                'categories' => ['Nüsse & Samen'],
            ],
            [
                'name' => 'Chiasamen',
                'description' => 'Ballaststoffreiche Samen, ideal für Pudding und Toppings.',
                'categories' => ['Nüsse & Samen', 'Frühstück'],
            ],
            [
                'name' => 'Leinsamen (geschrotet)',
                'description' => 'Omega-3-Quelle, geeignet als Eiersatz und für Bowls.',
                'categories' => ['Nüsse & Samen', 'Frühstück'],
            ],

            // =======================
            // Tofu & Alternativen
            // =======================
            [
                'name' => 'Naturtofu',
                'description' => 'Neutrale Proteinbasis, die gut Marinaden und Gewürze aufnimmt.',
                'categories' => ['Tofu & Alternativen', 'Proteinquelle'],
            ],
            [
                'name' => 'Räuchertofu',
                'description' => 'Kräftig-aromatischer Tofu, ideal zum Anbraten und für Aufläufe.',
                'categories' => ['Tofu & Alternativen', 'Proteinquelle'],
            ],
            [
                'name' => 'Tempeh',
                'description' => 'Fermentierte Sojabohnen, nussig und fest in der Konsistenz.',
                'categories' => ['Tofu & Alternativen', 'Proteinquelle'],
            ],

            // =======================
            // Pflanzliche Milchprodukte
            // =======================
            [
                'name' => 'Haferdrink',
                'description' => 'Pflanzliche Milchalternative für Porridge, Kaffee und Soßen.',
                'categories' => ['Pflanzliche Milchprodukte', 'Frühstück'],
            ],
            [
                'name' => 'Sojajoghurt natur',
                'description' => 'Neutraler Joghurtersatz, ideal für Bowls, Dips und Dressings.',
                'categories' => ['Pflanzliche Milchprodukte', 'Frühstück'],
            ],
            [
                'name' => 'Pflanzliche Kochsahne',
                'description' => 'Zum Verfeinern von Soßen, Currys und Aufläufen.',
                'categories' => ['Pflanzliche Milchprodukte'],
            ],

            // =======================
            // Öle & Fette
            // =======================
            [
                'name' => 'Olivenöl',
                'description' => 'Aromatisches Öl für Dressings, Marinaden und zum Braten bei mittlerer Hitze.',
                'categories' => ['Öle & Fette'],
            ],
            [
                'name' => 'Rapsöl',
                'description' => 'Neutraleres Öl zum Braten und Backen.',
                'categories' => ['Öle & Fette'],
            ],
            [
                'name' => 'Kokosöl',
                'description' => 'Für asiatisch angehauchte Gerichte und Backen.',
                'categories' => ['Öle & Fette'],
            ],

            // =======================
            // Saucen, Würzmittel & Basics
            // =======================
            [
                'name' => 'Sojasauce',
                'description' => 'Umami-Würze für Pfannengerichte, Bowls und Marinaden.',
                'categories' => ['Saucen & Würzmittel'],
            ],
            [
                'name' => 'Gemüsebrühe (Pulver oder Paste)',
                'description' => 'Basiswürze für Suppen, Eintöpfe und Saucen.',
                'categories' => ['Saucen & Würzmittel'],
            ],
            [
                'name' => 'Tomatenpassata',
                'description' => 'Grundlage für Saucen, Eintöpfe und Ofengerichte.',
                'categories' => ['Basics', 'Saucen & Würzmittel'],
            ],
            [
                'name' => 'Dosentomaten (stückig)',
                'description' => 'Praktische Basis für schnelle Pastasaucen und Eintöpfe.',
                'categories' => ['Basics', 'Saucen & Würzmittel'],
            ],
            [
                'name' => 'Hefeflocken',
                'description' => 'Käseartige Würze für Saucen, Pesto und Toppings.',
                'categories' => ['Saucen & Würzmittel'],
            ],

            // =======================
            // Süßungsmittel
            // =======================
            [
                'name' => 'Ahornsirup',
                'description' => 'Flüssige Süße für Dressings, Marinaden und Desserts.',
                'categories' => ['Süßungsmittel'],
            ],
            [
                'name' => 'Agavendicksaft',
                'description' => 'Neutraler Süßmacher für Drinks, Bowls und Backen.',
                'categories' => ['Süßungsmittel'],
            ],
        ];

        foreach ($ingredients as $data) {
            // Ingredient anlegen oder finden
            $ingredient = Ingredient::firstOrCreate(
                ['name' => $data['name']],                // eindeutiges Kriterium
                ['description' => $data['description']]   // default-Werte beim Erstellen
            );

            // Kategorien zuordnen (falls vorhanden)
            if (!empty($data['categories'])) {
                $categoryIds = Category::whereIn('name', $data['categories'])->pluck('id');

                if ($categoryIds->isNotEmpty()) {
                    // Many-to-Many-Beziehung, ohne vorhandene Verknüpfungen zu löschen
                    $ingredient->categories()->syncWithoutDetaching($categoryIds);
                }
            }
        }
    }
}
