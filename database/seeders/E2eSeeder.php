<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeImport;
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
 * - Bilder werden lokal per GD erzeugt (keine Downloads, keine fremden Fotos): seit Gate C1b
 *   eine weiche „Teller von oben“-Komposition in den Farbtönen des Gerichts statt einer
 *   einfarbigen Fläche (foodPng). Pfade, Rezept-IDs und alle übrigen Daten bleiben gleich.
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

        // Wegwerf-Kategorie für den F-07-Test (darf geloescht werden). Seit Slice 2 einem
        // Rezept zugeordnet (Rezept 6, siehe unten), damit die Bestätigung eine Anzahl nennt.
        Category::firstOrCreate(['name' => 'E2E-Wegwerf-Tag'], ['description' => 'Nur für den E2E-Test F-07.']);

        $base = Carbon::create(2025, 6, 1, 12, 0, 0, 'UTC');

        // ---------------------------------------------------------------
        // Konten (verifiziert, ohne 2FA)
        // ---------------------------------------------------------------
        $user = $this->makeUser('E2E Nutzerin', 'user@example.test', false, $base);
        $host = $this->makeUser('E2E Host', 'host@example.test', true, $base);
        $this->makeUser('E2E Zweitnutzer', 'user2@example.test', false, $base);

        // Abgeschlossener TikTok-Import des Hosts (ID 1) im Altformat MIT `source`:
        // Die Vorbelegung über /rezepte/erstellen?import=1 muss trotz entfallenem
        // Feld „Quelle“ funktionieren (F-15). Wird von keiner Baseline-Seite genutzt.
        $import = new RecipeImport;
        $import->forceFill([
            'user_id' => $host->id,
            'url' => 'https://www.tiktok.com/@e2e/video/1',
            'status' => 'done',
            'source' => 'caption',
            'error' => null,
            'result' => [
                'name' => 'E2E Import-Vorbelegung',
                'description' => 'Synthetisches Import-Ergebnis.',
                'duration_minutes' => 25,
                'servings' => 2,
                'source' => 'https://www.tiktok.com/@e2e/video/1',
                'tags' => ['abendessen'],
                'ingredients' => [
                    ['amount' => '1 Kopf', 'name' => 'Brokkoli'],
                    ['amount' => '3 EL', 'name' => 'Sojasauce'],
                ],
                'steps' => ['Brokkoli waschen.', 'Mit Sojasauce braten.'],
                'notes' => 'Schmeckt auch kalt.',
            ],
            'created_at' => $base,
            'updated_at' => $base,
        ])->save();

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
                'image' => [150, 62, 44], // Linsen-Bolognese: tiefes Tomatenrot
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
                'image' => [226, 150, 52], // Kürbis-Curry: Orange
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
                'image' => [168, 124, 76], // Räuchertofu: Röstbraun
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
                'image' => [196, 150, 92], // Quinoa-Bowl: Sand
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
                'image' => [214, 86, 58], // Tomatenpasta: Tomate
                // E2E-Wegwerf-Tag: F-07-Regressionstest löscht die Kategorie mit Bestätigung
                'categories' => ['Mittagessen', 'E2E-Wegwerf-Tag'],
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
                'image' => [244, 182, 64], // Chia-Mango: Mango
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
                'image' => [208, 116, 62], // Ofengemüse: Karotte
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
            $recipe = new Recipe;
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
                $disk->put($path, $this->foodPng($data['image'], $i + 1));
                RecipeImage::create(['recipe_id' => $recipe->id, 'image_path' => $path]);
            }

            $created[] = $recipe;
        }

        // ---------------------------------------------------------------
        // Sammlungen der Nutzerin: eine gefuellt (ID 1), eine leer (ID 2)
        // ---------------------------------------------------------------
        $filled = new Collection;
        $filled->forceFill([
            'user_id' => $user->id,
            'name' => 'Wochenplan',
            'description' => 'Gefüllte Sammlung mit drei Rezepten.',
            'created_at' => $base,
            'updated_at' => $base->copy()->addHour(),
        ])->save();
        $filled->recipes()->sync([$created[0]->id, $created[1]->id, $created[2]->id]);

        $empty = new Collection;
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
        $user = new User;
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

    /**
     * Gate C1b: lokal erzeugtes Platzhalterbild „Teller von oben“ (keine Downloads, keine
     * fremden Fotos). Deterministisch je Rezept: Tischfläche mit weichem Verlauf, Teller mit
     * Rand und Schatten, darauf viele halbtransparente Kreise in Farbtönen des Gerichts und
     * ein paar Kräuterblätter. Format 4:3 (960 x 720), PNG.
     */
    private function foodPng(array $rgb, int $seed): string
    {
        mt_srand($seed);
        $w = 960;
        $h = 720;
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);

        // Tischfläche: abwechselnd heller Leinenton und dunkles Waldgrün, weicher Verlauf
        $dark = $seed % 2 === 0;
        $top = $dark ? [24, 52, 40] : [236, 232, 218];
        $bottom = $dark ? [14, 36, 28] : [214, 222, 200];
        for ($y = 0; $y < $h; $y++) {
            $t = $y / ($h - 1);
            $c = imagecolorallocate(
                $img,
                (int) round($top[0] + ($bottom[0] - $top[0]) * $t),
                (int) round($top[1] + ($bottom[1] - $top[1]) * $t),
                (int) round($top[2] + ($bottom[2] - $top[2]) * $t),
            );
            imageline($img, 0, $y, $w, $y, $c);
        }

        $clamp = static fn (float $v): int => max(0, min(255, (int) round($v)));
        $shade = static function (array $c, float $f) use ($clamp): array {
            return $f >= 0
                ? [$clamp($c[0] + (255 - $c[0]) * $f), $clamp($c[1] + (255 - $c[1]) * $f), $clamp($c[2] + (255 - $c[2]) * $f)]
                : [$clamp($c[0] * (1 + $f)), $clamp($c[1] * (1 + $f)), $clamp($c[2] * (1 + $f))];
        };
        $color = static fn (array $c, int $alpha = 0) => imagecolorallocatealpha($img, $c[0], $c[1], $c[2], $alpha);

        // Teller (leicht aus der Mitte), Schatten, Rand, Innenfläche
        $cx = (int) ($w * (0.46 + mt_rand(0, 8) / 100));
        $cy = (int) ($h * (0.5 + mt_rand(-4, 4) / 100));
        $r = (int) ($h * 0.43);
        imagefilledellipse($img, $cx + 18, $cy + 26, 2 * $r + 10, 2 * $r + 10, $color([0, 0, 0], $dark ? 70 : 96));
        imagefilledellipse($img, $cx, $cy, 2 * $r, 2 * $r, $color([248, 248, 242]));
        imagefilledellipse($img, $cx, $cy, (int) (1.62 * $r), (int) (1.62 * $r), $color([236, 238, 228]));

        // Essen: viele weiche Kreise in Farbtönen des Gerichts
        $tones = [$rgb, $shade($rgb, -0.25), $shade($rgb, 0.25), $shade($rgb, -0.45), $shade($rgb, 0.45)];
        $inner = 0.66 * $r;
        // Grundschicht (Soße/Brei) als unregelmäßiger Fleck
        for ($i = 0; $i < 9; $i++) {
            $a = $i / 9 * 6.283;
            imagefilledellipse($img, (int) ($cx + cos($a) * $inner * 0.35), (int) ($cy + sin($a) * $inner * 0.35), (int) ($inner * 1.3), (int) ($inner * 1.2), $color($shade($rgb, 0.15), 70));
        }
        for ($i = 0; $i < 420; $i++) {
            $a = mt_rand(0, 6283) / 1000;
            $d = sqrt(mt_rand(0, 1000) / 1000) * $inner;
            $x = (int) ($cx + cos($a) * $d);
            $y = (int) ($cy + sin($a) * $d);
            $s = mt_rand(14, 58);
            imagefilledellipse($img, $x, $y, $s, (int) ($s * (0.7 + mt_rand(0, 40) / 100)), $color($tones[mt_rand(0, 4)], mt_rand(10, 60)));
        }

        // Kräuterblätter (Polygon in Blattform, gedreht)
        $leafGreens = [[92, 140, 58], [61, 110, 52], [140, 178, 92]];
        for ($i = 0; $i < 14; $i++) {
            $a = mt_rand(0, 6283) / 1000;
            $d = sqrt(mt_rand(0, 1000) / 1000) * ($inner * 0.95);
            $lx = $cx + cos($a) * $d;
            $ly = $cy + sin($a) * $d;
            $len = mt_rand(26, 46);
            $rot = mt_rand(0, 6283) / 1000;
            $pts = [];
            for ($k = 0; $k <= 16; $k++) {
                $t = $k / 16;
                $px = $len * $t;
                $py = sin(M_PI * $t) * $len * 0.32;
                $pts[] = [$px, $py];
            }
            for ($k = 15; $k >= 1; $k--) {
                $t = $k / 16;
                $pts[] = [$len * $t, -sin(M_PI * $t) * $len * 0.32];
            }
            $flat = [];
            foreach ($pts as [$px, $py]) {
                $flat[] = (int) round($lx + $px * cos($rot) - $py * sin($rot));
                $flat[] = (int) round($ly + $px * sin($rot) + $py * cos($rot));
            }
            imagefilledpolygon($img, $flat, $color($leafGreens[mt_rand(0, 2)], 8));
        }

        // Ein paar Kräuter und Krümel neben dem Teller
        for ($i = 0; $i < 18; $i++) {
            $x = mt_rand(0, $w);
            $y = mt_rand(0, $h);
            if (hypot($x - $cx, $y - $cy) < $r + 20) {
                continue;
            }
            $s = mt_rand(6, 18);
            imagefilledellipse($img, $x, $y, $s, $s, $color($i % 3 === 0 ? $rgb : $leafGreens[$i % 3], 30));
        }

        // weichzeichnen: wirkt wie unscharfes Foto statt Vektorgrafik
        for ($i = 0; $i < 3; $i++) {
            imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
        }

        ob_start();
        imagepng($img, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }
}
