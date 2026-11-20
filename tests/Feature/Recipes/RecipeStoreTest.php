<?php

namespace Tests\Feature\Recipes;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Charakterisierung von Anlegen-Formular (Route-Closure recipes.create in routes/web.php)
 * und Speichern (RecipeController::store, POST /recipes) als Host.
 */
class RecipeStoreTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_create_form_renders_for_host_with_lookup_props(): void
    {
        $this->actingAsHost();
        $dimension = RatingDimension::factory()->create(['name' => 'Geschmack', 'description' => 'Wie schmeckt es?']);
        // E14: andere Dimensionen erscheinen nicht mehr im Formular.
        RatingDimension::factory()->create(['name' => 'Aufwand']);
        $category = Category::factory()->create(['name' => 'Vegan']);
        $zucchini = Ingredient::factory()->create(['name' => 'Zucchini']);
        $apfel = Ingredient::factory()->create(['name' => 'Apfel']);

        $response = $this->get(route('recipes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Create')
            ->where('ratingDimensions', [
                ['id' => $dimension->id, 'name' => 'Geschmack', 'description' => 'Wie schmeckt es?'],
            ])
            ->where('categories', [['id' => $category->id, 'name' => 'Vegan']])
            // Zutaten alphabetisch nach Name
            ->where('ingredients', [
                ['id' => $apfel->id, 'name' => 'Apfel'],
                ['id' => $zucchini->id, 'name' => 'Zucchini'],
            ])
            ->where('importedRecipe', null)
        );
    }

    public function test_store_persists_complete_payload_and_redirects_to_show(): void
    {
        Storage::fake('public');
        $this->actingAsHost();

        $vegan = Category::factory()->create(['name' => 'Vegan']);
        $schnell = Category::factory()->create(['name' => 'Schnell']);
        Category::factory()->create(['name' => 'Dessert']);
        $geschmack = RatingDimension::factory()->create(['name' => 'Geschmack']);
        $tofu = Ingredient::factory()->create(['name' => 'Tofu']);
        $seitan = Ingredient::factory()->create(['name' => 'Seitan']);
        $tempeh = Ingredient::factory()->create(['name' => 'Tempeh']);
        $ingredientCountBefore = Ingredient::count();

        $response = $this->post(route('recipes.store'), [
            'name' => 'Tofu-Pfanne',
            'description' => 'Schnell und einfach.',
            'duration_minutes' => '25',
            'servings' => '2',
            'source' => '',
            // "Unbekannt" gibt es nicht als Kategorie -> wird still ignoriert.
            'tags' => ['Vegan', 'Schnell', 'Unbekannt'],
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$seitan->id, $tempeh->id]],
                ['amount' => '1 EL', 'name' => 'Weiße Misopaste'],
                ['amount' => '', 'name' => '  Salz  '],
            ],
            'steps' => ['C', 'A', 'B'],
            'notes' => "Notiz eins\n\nNotiz zwei",
            'ratings' => [$geschmack->id => '4'],
            'image' => $this->fakePng('pfanne.png'),
        ]);

        $recipe = Recipe::sole();
        $response->assertRedirect(route('recipes.show', $recipe));
        $response->assertSessionHas('success', 'Rezept wurde erfolgreich erstellt.');
        $response->assertSessionHasNoErrors();

        $this->assertSame('Tofu-Pfanne', $recipe->title);
        $this->assertSame('Schnell und einfach.', $recipe->description);
        $this->assertSame(25, $recipe->duration_minutes);
        $this->assertSame(2, $recipe->servings);
        // Reihenfolge der Schritte bleibt erhalten; leere Notizzeilen fallen weg.
        $this->assertSame("1. C\n2. A\n3. B\nNotizen:\nNotiz eins\nNotiz zwei", $recipe->instructions);

        $this->assertEqualsCanonicalizing([$vegan->id, $schnell->id], $recipe->categories()->pluck('categories.id')->all());

        // Zutaten: Menge als Freitext in note, quantity/unit leer, Name getrimmt.
        $this->assertSame(['Tofu', 'Weiße Misopaste', 'Salz'], $this->ingredientNamesInDbOrder($recipe));
        $rows = RecipeIngredient::where('recipe_id', $recipe->id)->orderBy('id')->get();
        $this->assertSame(['200 g', '1 EL', null], $rows->pluck('note')->all());
        $this->assertSame([null, null, null], $rows->pluck('quantity')->all());
        $this->assertSame([null, null, null], $rows->pluck('unit')->all());
        $this->assertSame($tofu->id, $rows[0]->ingredient_id); // vorhandene Zutat wiederverwendet

        // Neue Zutaten per firstOrCreate angelegt.
        $this->assertSame($ingredientCountBefore + 2, Ingredient::count());
        $this->assertDatabaseHas('ingredients', ['name' => 'Weiße Misopaste']);
        $this->assertDatabaseHas('ingredients', ['name' => 'Salz']);

        // Alternativen
        $this->assertDatabaseCount('ingredient_alternatives', 2);
        foreach ([$seitan, $tempeh] as $alt) {
            $this->assertDatabaseHas('ingredient_alternatives', [
                'recipe_id' => $recipe->id,
                'ingredient_id' => $tofu->id,
                'alternative_ingredient_id' => $alt->id,
            ]);
        }

        // Bewertung (E14): nur Geschmack; vorher wurden mehrere Dimensionen gespeichert.
        $this->assertDatabaseCount('recipe_ratings', 1);
        $this->assertDatabaseHas('recipe_ratings', ['recipe_id' => $recipe->id, 'dimension_id' => $geschmack->id, 'value' => 4]);

        // Bild
        $image = RecipeImage::sole();
        $this->assertSame($recipe->id, $image->recipe_id);
        $this->assertStringStartsWith('recipes/', $image->image_path);
        $this->assertStringEndsWith('.png', $image->image_path);
        Storage::disk('public')->assertExists($image->image_path);
    }

    public function test_store_with_empty_taste_value_saves_no_rating(): void
    {
        $this->actingAsHost();
        $geschmack = RatingDimension::factory()->create(['name' => 'Geschmack']);

        $this->post(route('recipes.store'), $this->recipePayload([
            'ratings' => [$geschmack->id => ''], // leerer Wert -> null
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('recipe_ratings', 0);
    }

    public function test_store_with_minimal_payload_has_no_image_ratings_or_categories(): void
    {
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'duration_minutes' => '',
            'servings' => '',
            'description' => '',
        ]))->assertRedirect();

        $recipe = Recipe::sole();
        $this->assertNull($recipe->description);
        $this->assertNull($recipe->duration_minutes);
        $this->assertNull($recipe->servings);
        $this->assertSame('1. Linsen waschen.', $recipe->instructions); // ohne Notizen-Block
        $this->assertDatabaseCount('recipe_images', 0);
        $this->assertDatabaseCount('recipe_ratings', 0);
        $this->assertDatabaseCount('recipe_categories', 0);
        $this->assertDatabaseCount('ingredient_alternatives', 0);
    }

    public function test_stored_recipe_round_trips_through_show(): void
    {
        $this->actingAsHost();
        $dimension = RatingDimension::factory()->create(['name' => 'Geschmack']);

        $this->post(route('recipes.store'), $this->recipePayload([
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'Rote Linsen'],
                ['amount' => '1', 'name' => 'Zwiebel'],
            ],
            'steps' => ['Schneiden', 'Kochen'],
            'notes' => 'Einfrieren möglich',
            'ratings' => [$dimension->id => 3],
        ]));

        $recipe = Recipe::sole();
        $props = $this->pageProps($this->get(route('recipes.show', $recipe)));

        $this->assertSame('Linsen-Bolognese', $props['recipe']['name']);
        $this->assertSame(['Schneiden', 'Kochen'], $props['recipe']['steps']);
        $this->assertSame(['Einfrieren möglich'], $props['recipe']['notes']);
        $this->assertSame([
            ['amount' => '200 g', 'name' => 'Rote Linsen', 'alternatives' => []],
            ['amount' => '1', 'name' => 'Zwiebel', 'alternatives' => []],
        ], $props['recipe']['ingredients']);
        $this->assertEquals(3.0, $props['recipe']['rating']);
        $this->assertSame(1, $props['recipe']['ratingCount']);
    }
}
