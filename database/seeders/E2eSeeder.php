<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeIngredient;
use App\Models\RecipeRating;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Deterministische, rein synthetische Daten für die Playwright-Browser-Baseline.
 *
 * - Nur für die E2E-Datenbank `database/e2e.sqlite` gedacht (siehe tests/e2e/global-setup.ts).
 * - Bewusst NICHT im DatabaseSeeder registriert.
 * - Nutzt keine Factories: Daten werden direkt ueber die Modelle erzeugt.
 * - Bilder werden lokal per GD als einfarbige PNGs erzeugt (keine Downloads).
 *
 * Die IDs (Rezepte 1..8, Sammlungen 1..2) sind stabil, weil die Datei vor jedem Lauf neu
 * angelegt wird. tests/e2e/support/fixtures.ts spiegelt diese Werte.
 */
class E2eSeeder extends Seeder
{
    public const PASSWORD = 'E2e-Passwort-2026!';

    public function run(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        if (config('database.default') !== 'sqlite' || ! str_ends_with(str_replace('\\', '/', $database), 'database/e2e.sqlite')) {
            throw new RuntimeException('E2eSeeder darf nur gegen database/e2e.sqlite laufen (aktuell: '.$database.').');
        }

        $this->call([
            CategorySeeder::class,
            RatingDimensionSeeder::class,
            IngredientSeeder::class,
        ]);

        // Wegwerf-Kategorie für den F-07-Test (darf geloescht werden).
        Category::firstOrCreate(['name' => 'E2E-Wegwerf-Tag'], ['description' => 'Nur für den E2E-Test F-07.']);

        $base = Carbon::create(2025, 6, 1, 12, 0, 0, 'UTC');

        // ---------------------------------------------------------------
        // Konten (verifiziert, ohne 2FA)
        // ---------------------------------------------------------------
        $user = $this->makeUser('E2E Nutzerin', 'user@example.test', false, $base);
        $this->makeUser('E2E Host', 'host@example.test', true, $base);
        $this->makeUser('E2E Zweitnutzer', 'user2@example.test', false, $base);

        // ---------------------------------------------------------------
        // Bilder (lokal erzeugt)
        // ---------------------------------------------------------------
        $disk = Storage::disk('public');
        $disk->deleteDirectory('e2e');
        $disk->makeDirectory('e2e');

        $dimensions = RatingDimension::orderBy('id')->get()->keyBy('name');

        $recipes = [
            [
                'title' => 'Cremige Linsen-Bolognese',
                'description' => 'Herzhafte Bolognese mit roten Linsen, ideal für die schnelle Feierabendküche.',
                'duration' => 35,
                'servings' => 4,
                'image' => [125, 168, 121],
                'categories' => ['Abendessen', 'Vegetarisch'],
                'ingredients' => [
                    ['Rote Linsen', '200 g', ['Braune Linsen']],
                    ['Vollkornpasta', '400 g', ['Quinoa', 'Basmatireis']],
                    ['Tomatenpassata', '500 ml', []],
                    ['Zwiebeln', '1 Stück', []],
                    ['Knoblauch', '2 Zehen', []],
                    ['Olivenöl', '2 EL', ['Rapsöl']],
                ],
                'steps' => [
                    'Zwiebeln und Knoblauch fein würfeln und in Olivenöl glasig andünsten.',
                    'Linsen und Passata zugeben und 15 Minuten köcheln lassen.',
                    'Pasta nach Packungsangabe kochen und mit der Sauce mischen.',
                ],
                'notes' => ['Hält sich im Kühlschrank drei Tage.', 'Mit Hefeflocken bestreuen schmeckt sie noch kräftiger.', 'Für Kinder etwas weniger Knoblauch verwenden.'],
                'ratings' => ['Schwierigkeit' => 2, 'Geschmack' => 5, 'Aufwand' => 2, 'Gesamt' => 4],
            ],
            [
                'title' => 'Kürbis-Kichererbsen-Erdnusscurrygewürzmischung mit Röstzwiebel-Topping',
                'description' => 'Ein absichtlich sehr langer zusammengesetzter Titel, um Umbruch und Overflow zu prüfen.',
                'duration' => 50,
                'servings' => 3,
                'image' => [232, 180, 83],
                'categories' => ['Mittagessen', 'Abendessen', 'Vegetarisch', 'Low Carb'],
                'ingredients' => [
                    ['Kichererbsen (gekocht)', '1 Dose', ['Weiße Bohnen (gekocht)']],
                    ['Pflanzliche Kochsahne', '200 ml', []],
                    ['Spinat (frisch oder TK)', '150 g', []],
                    ['Basmatireis', '250 g', ['Vollkornreis']],
                ],
                'steps' => [
                    'Kürbis würfeln und mit der Gewürzmischung im Topf anrösten.',
                    'Kichererbsen und Kochsahne zugeben, 20 Minuten köcheln.',
                    'Spinat unterheben, mit Reis und Röstzwiebeln servieren.',
                    'Nach Geschmack mit Limette abschmecken.',
                ],
                'notes' => ['Die Gewürzmischung kann man vorab in größerer Menge mischen.', 'Röstzwiebeln erst kurz vor dem Servieren daraufgeben.'],
                'ratings' => ['Schwierigkeit' => 3, 'Geschmack' => 4, 'Aufwand' => 4, 'Gesamt' => 4],
            ],
            [
                'title' => 'Overnight Oats mit Beeren',
                'description' => 'Frühstück ohne Bild: prüft die Darstellung der Detailseite ohne Foto.',
                'duration' => 5,
                'servings' => 1,
                'image' => null,
                'categories' => ['Frühstück'],
                'ingredients' => [
                    ['Haferflocken', '60 g', []],
                    ['Haferdrink', '150 ml', ['Sojajoghurt natur']],
                    ['Chiasamen', '1 EL', []],
                    ['Ahornsirup', '1 TL', ['Agavendicksaft']],
                ],
                'steps' => [
                    'Alle Zutaten in einem Glas verrühren.',
                    'Über Nacht im Kühlschrank quellen lassen.',
                ],
                'notes' => ['Morgens mit frischen Beeren toppen.', 'Hält zwei Tage.'],
                'ratings' => ['Schwierigkeit' => 1, 'Geschmack' => 4, 'Aufwand' => 1, 'Gesamt' => 4],
            ],
            [
                'title' => 'Gebratener Räuchertofu mit Brokkoli',
                'description' => 'Schnelle Pfanne mit Sojasauce.',
                'duration' => 20,
                'servings' => 2,
                'image' => [94, 120, 160],
                'categories' => ['Abendessen'],
                'ingredients' => [
                    ['Räuchertofu', '200 g', ['Naturtofu', 'Tempeh']],
                    ['Brokkoli', '1 Kopf', []],
                    ['Sojasauce', '3 EL', []],
                ],
                'steps' => [
                    'Tofu würfeln und scharf anbraten.',
                    'Brokkoli zugeben und mit Sojasauce ablöschen.',
                ],
                'notes' => [],
                'ratings' => ['Schwierigkeit' => 2, 'Geschmack' => 3, 'Aufwand' => 2, 'Gesamt' => 3],
            ],
            [
                'title' => 'Quinoa-Bowl mit schwarzen Bohnen',
                'description' => null,
                'duration' => 30,
                'servings' => 2,
                'image' => [160, 90, 70],
                'categories' => ['Mittagessen', 'Vegetarisch'],
                'ingredients' => [
                    ['Quinoa', '150 g', []],
                    ['Schwarze Bohnen (gekocht)', '1 Dose', []],
                    ['Paprika (rot)', '1 Stück', []],
                ],
                'steps' => [
                    'Quinoa kochen.',
                    'Bohnen und Paprika anbraten und auf der Quinoa anrichten.',
                ],
                'notes' => ['Dazu passt ein Joghurt-Dip.'],
                'ratings' => ['Gesamt' => 5],
            ],
            [
                'title' => 'Schnelle Tomatenpasta',
                'description' => 'Ohne Bewertungen.',
                'duration' => 15,
                'servings' => 2,
                'image' => [200, 70, 60],
                'categories' => ['Mittagessen'],
                'ingredients' => [
                    ['Vollkornpasta', '250 g', []],
                    ['Dosentomaten (stückig)', '1 Dose', []],
                ],
                'steps' => [
                    'Pasta kochen, Tomaten erhitzen, mischen.',
                ],
                'notes' => [],
                'ratings' => [],
            ],
            [
                'title' => 'Chia-Pudding mit Mango',
                'description' => 'Dessert für Vorbereitung am Vorabend.',
                'duration' => 10,
                'servings' => 2,
                'image' => [240, 200, 120],
                'categories' => ['Dessert', 'Snack'],
                'ingredients' => [
                    ['Chiasamen', '4 EL', ['Leinsamen (geschrotet)']],
                    ['Haferdrink', '250 ml', []],
                ],
                'steps' => [
                    'Chiasamen mit Haferdrink verrühren.',
                    'Mindestens vier Stunden kühlen.',
                ],
                'notes' => ['Mango erst beim Servieren zugeben.'],
                'ratings' => ['Schwierigkeit' => 1, 'Geschmack' => 5, 'Aufwand' => 1, 'Gesamt' => 5],
            ],
            [
                'title' => 'Ofengemüse mit Hummus',
                'description' => 'Rezept ohne Kategorien.',
                'duration' => 45,
                'servings' => 4,
                'image' => [110, 140, 90],
                'categories' => [],
                'ingredients' => [
                    ['Karotten', '4 Stück', []],
                    ['Zucchini', '1 Stück', []],
                    ['Champignons', '250 g', []],
                    ['Kichererbsen (gekocht)', '1 Dose', []],
                ],
                'steps' => [
                    'Gemüse schneiden und auf einem Blech verteilen.',
                    'Bei 200 Grad 30 Minuten rösten.',
                    'Kichererbsen zu Hummus pürieren und dazu servieren.',
                ],
                'notes' => [],
                'ratings' => ['Gesamt' => 3],
            ],
        ];

        $created = [];
        foreach ($recipes as $i => $data) {
            $timestamp = $base->copy()->addDays($i);
            $recipe = new Recipe();
            $recipe->forceFill([
                'title' => $data['title'],
                'description' => $data['description'],
                'instructions' => $this->instructions($data['steps'], $data['notes']),
                'duration_minutes' => $data['duration'],
                'servings' => $data['servings'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->save();

            if ($data['categories']) {
                $recipe->categories()->sync(Category::whereIn('name', $data['categories'])->pluck('id'));
            }

            foreach ($data['ingredients'] as [$name, $amount, $alternatives]) {
                $ingredient = Ingredient::firstOrCreate(['name' => $this->fixName($name)]);
                RecipeIngredient::create([
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $ingredient->id,
                    'quantity' => null,
                    'unit' => null,
                    'note' => $amount,
                ]);
                foreach ($alternatives as $altName) {
                    $alt = Ingredient::firstOrCreate(['name' => $this->fixName($altName)]);
                    IngredientAlternative::create([
                        'recipe_id' => $recipe->id,
                        'ingredient_id' => $ingredient->id,
                        'alternative_ingredient_id' => $alt->id,
                        'note' => null,
                    ]);
                }
            }

            foreach ($data['ratings'] as $dimensionName => $value) {
                RecipeRating::create([
                    'recipe_id' => $recipe->id,
                    'dimension_id' => $dimensions[$dimensionName]->id,
                    'value' => $value,
                ]);
            }

            if ($data['image'] !== null) {
                $path = 'e2e/recipe-'.($i + 1).'.png';
                $disk->put($path, $this->solidPng($data['image']));
                RecipeImage::create(['recipe_id' => $recipe->id, 'image_path' => $path]);
            }

            $created[] = $recipe;
        }

        // ---------------------------------------------------------------
        // Sammlungen der Nutzerin: eine gefuellt (ID 1), eine leer (ID 2)
        // ---------------------------------------------------------------
        $filled = new Collection();
        $filled->forceFill([
            'user_id' => $user->id,
            'name' => 'Wochenplan',
            'description' => 'Gefüllte Sammlung mit drei Rezepten.',
            'created_at' => $base,
            'updated_at' => $base->copy()->addHour(),
        ])->save();
        $filled->recipes()->sync([$created[0]->id, $created[1]->id, $created[2]->id]);

        $empty = new Collection();
        $empty->forceFill([
            'user_id' => $user->id,
            'name' => 'Leere Sammlung',
            'description' => null,
            'created_at' => $base,
            'updated_at' => $base,
        ])->save();
    }

    private function makeUser(string $name, string $email, bool $isHost, Carbon $at): User
    {
        $user = new User();
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $at,
            'password' => Hash::make(self::PASSWORD),
            'is_host' => $isHost,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        return $user;
    }

    /**
     * Die Basis-Seeder verwenden echte Umlaute; die Rezeptdaten oben teils ASCII.
     */
    private function fixName(string $name): string
    {
        return ['Olivenöl' => 'Olivenöl', 'Rapsöl' => 'Rapsöl'][$name] ?? $name;
    }

    private function instructions(array $steps, array $notes): string
    {
        $lines = [];
        foreach ($steps as $i => $step) {
            $lines[] = ($i + 1).'. '.$step;
        }
        if ($notes) {
            $lines[] = 'Notizen:';
            foreach ($notes as $note) {
                $lines[] = $note;
            }
        }

        return implode("\n", $lines);
    }

    private function solidPng(array $rgb): string
    {
        $img = imagecreatetruecolor(1200, 800);
        imagefill($img, 0, 0, imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]));
        ob_start();
        imagepng($img, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }
}
