<?php

namespace Tests\Feature\Recipes;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Charakterisierung von Bearbeiten-Formular (RecipeController::edit, GET /rezept/{recipe}/bearbeiten)
 * und Aktualisieren (RecipeController::update, POST /rezept/{recipe}) als Host.
 */
class RecipeEditUpdateTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_edit_props_round_trip_what_store_saved(): void
    {
        $this->actingAsHost();
        $vegan = Category::factory()->create(['name' => 'Vegan']);
        $schnell = Category::factory()->create(['name' => 'Schnell']);
        $geschmack = RatingDimension::factory()->create(['name' => 'Geschmack', 'description' => null]);
        $aufwand = RatingDimension::factory()->create(['name' => 'Aufwand', 'description' => null]);
        $seitan = Ingredient::factory()->create(['name' => 'Seitan']);
        $tempeh = Ingredient::factory()->create(['name' => 'Tempeh']);

        $this->post(route('recipes.store'), [
            'name' => 'Tofu-Pfanne',
            'description' => 'Schnell und einfach.',
            'duration_minutes' => 25,
            'servings' => 2,
            'tags' => ['Vegan', 'Schnell'],
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$seitan->id, $tempeh->id]],
                ['amount' => '1 EL', 'name' => 'Sojasauce'],
                ['amount' => '', 'name' => 'Salz'],
            ],
            'steps' => ['C', 'A', 'B'],
            'notes' => "Notiz eins\nNotiz zwei",
            'ratings' => [$geschmack->id => 4, $aufwand->id => 2],
        ])->assertSessionHasNoErrors();

        $recipe = Recipe::sole();
        $response = $this->get(route('recipes.edit', $recipe));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Edit')
            ->has('ratingDimensions', 2)
            ->has('categories', 2)
            ->has('ingredients', 5) // alle Zutaten für die Auswahl
            ->where('isHost', true)
        );

        $props = $this->pageProps($response);
        $form = $props['recipe'];

        $this->assertSame($recipe->id, $form['id']);
        $this->assertSame('Tofu-Pfanne', $form['name']);
        $this->assertSame('Schnell und einfach.', $form['description']);
        $this->assertSame(25, $form['duration_minutes']);
        $this->assertSame(2, $form['servings']);
        $this->assertNull($form['source']); // F-15
        $this->assertEqualsCanonicalizing(['Vegan', 'Schnell'], $form['tags']);
        $this->assertSame(['C', 'A', 'B'], $form['steps']);
        $this->assertSame("Notiz eins\nNotiz zwei", $form['notes']);
        $this->assertSame([
            ['amount' => '200 g', 'name' => 'Tofu', 'alternativeIngredientIds' => [$seitan->id, $tempeh->id]],
            ['amount' => '1 EL', 'name' => 'Sojasauce', 'alternativeIngredientIds' => []],
            ['amount' => '', 'name' => 'Salz', 'alternativeIngredientIds' => []], // null-Menge -> ''
        ], $form['ingredients']);
        $this->assertSame([$geschmack->id => 4, $aufwand->id => 2], $form['ratings']);

        // Auswahllisten: Zutaten alphabetisch.
        $this->assertSame(
            ['Salz', 'Seitan', 'Sojasauce', 'Tempeh', 'Tofu'],
            array_column($props['ingredients'], 'name')
        );
        $this->assertEqualsCanonicalizing([$vegan->id, $schnell->id], array_column($props['categories'], 'id'));
    }

    public function test_edit_without_notes_returns_null_notes(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->withSteps(['Nur ein Schritt'])->create();

        $form = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];

        $this->assertSame(['Nur ein Schritt'], $form['steps']);
        $this->assertNull($form['notes']);
        $this->assertSame([], $form['ingredients']);
        $this->assertSame([], $form['ratings']);
        $this->assertSame([], $form['tags']);
    }

    public function test_edit_unknown_recipe_returns_404(): void
    {
        $this->actingAsHost();

        $this->get('/rezept/999999/bearbeiten')->assertNotFound();
    }

    public function test_update_replaces_fields_ingredients_ratings_and_tags(): void
    {
        $this->actingAsHost();
        $vegan = Category::factory()->create(['name' => 'Vegan']);
        $schnell = Category::factory()->create(['name' => 'Schnell']);
        $geschmack = RatingDimension::factory()->create();
        $aufwand = RatingDimension::factory()->create();

        $recipe = Recipe::factory()->withSteps(['Alt'], 'Alte Notiz')->create([
            'title' => 'Alter Titel',
            'description' => 'Alt',
            'duration_minutes' => 10,
            'servings' => 1,
        ]);
        $recipe->categories()->attach($vegan);
        $this->attachIngredient($recipe, 'Altzutat', '1 kg');
        $this->attachIngredient($recipe, 'Tofu', '100 g');
        RecipeRating::factory()->create(['recipe_id' => $recipe->id, 'dimension_id' => $geschmack->id, 'value' => 1]);
        RecipeRating::factory()->create(['recipe_id' => $recipe->id, 'dimension_id' => $aufwand->id, 'value' => 1]);

        $response = $this->post(route('recipes.update', $recipe), [
            'name' => 'Neuer Titel',
            'description' => 'Neu',
            'duration_minutes' => 20,
            'servings' => 3,
            'tags' => ['Schnell'],
            'ingredients' => [
                ['amount' => '3', 'name' => 'Zwiebel'],
                ['amount' => '200 g', 'name' => 'Tofu'],
                ['amount' => '', 'name' => 'Apfel'],
            ],
            'steps' => ['Zwei', 'Eins'],
            'notes' => '',
            'ratings' => [$geschmack->id => 5],
        ]);

        $response->assertRedirect(route('recipes.show', $recipe));
        $response->assertSessionHas('success', 'Rezept wurde erfolgreich aktualisiert.');

        $recipe->refresh();
        $this->assertSame('Neuer Titel', $recipe->title);
        $this->assertSame('Neu', $recipe->description);
        $this->assertSame(20, $recipe->duration_minutes);
        $this->assertSame(3, $recipe->servings);
        $this->assertSame("1. Zwei\n2. Eins", $recipe->instructions); // Notizen entfernt

        $this->assertSame([$schnell->id], $recipe->categories()->pluck('categories.id')->all());

        // Zutaten ersetzt, Reihenfolge wie gesendet (F-56: nur implizit über die Insert-Reihenfolge).
        $this->assertSame(['Zwiebel', 'Tofu', 'Apfel'], $this->ingredientNamesInDbOrder($recipe));
        $this->assertDatabaseCount('recipe_ingredients', 3);
        $this->assertDatabaseHas('recipe_ingredients', ['recipe_id' => $recipe->id, 'note' => '200 g']);
        $this->assertDatabaseMissing('recipe_ingredients', ['note' => '1 kg']);
        // Die nicht mehr genutzte Zutat selbst bleibt im Katalog.
        $this->assertDatabaseHas('ingredients', ['name' => 'Altzutat']);

        // Bewertungen ersetzt (nicht gesendete Dimension entfällt).
        $this->assertDatabaseCount('recipe_ratings', 1);
        $this->assertDatabaseHas('recipe_ratings', ['recipe_id' => $recipe->id, 'dimension_id' => $geschmack->id, 'value' => 5]);

        $show = $this->pageProps($this->get(route('recipes.show', $recipe)));
        $this->assertSame(['Zwiebel', 'Tofu', 'Apfel'], array_column($show['recipe']['ingredients'], 'name'));
        $this->assertSame(['Zwei', 'Eins'], $show['recipe']['steps']);
        $this->assertSame([], $show['recipe']['notes']);
    }

    public function test_update_without_tags_detaches_all_categories(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        $recipe->categories()->attach(Category::factory()->count(2)->create());

        $this->post(route('recipes.update', $recipe), $this->recipePayload(['tags' => []]))
            ->assertRedirect(route('recipes.show', $recipe));

        $this->assertDatabaseCount('recipe_categories', 0);
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_update_with_new_image_replaces_record_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        Storage::disk('public')->put('recipes/alt.png', 'alt');
        $oldImage = RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/alt.png']);

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'image' => $this->fakePng('neu.png'),
        ]))->assertRedirect(route('recipes.show', $recipe));

        Storage::disk('public')->assertMissing('recipes/alt.png');
        $this->assertDatabaseMissing('recipe_images', ['id' => $oldImage->id]);

        $newImage = RecipeImage::sole();
        $this->assertSame($recipe->id, $newImage->recipe_id);
        $this->assertStringStartsWith('recipes/', $newImage->image_path);
        Storage::disk('public')->assertExists($newImage->image_path);
    }

    public function test_update_without_new_image_keeps_old_image(): void
    {
        Storage::fake('public');
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        Storage::disk('public')->put('recipes/alt.png', 'alt');
        $oldImage = RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/alt.png']);

        $this->post(route('recipes.update', $recipe), $this->recipePayload())
            ->assertRedirect(route('recipes.show', $recipe));

        Storage::disk('public')->assertExists('recipes/alt.png');
        $this->assertSame([$oldImage->id], RecipeImage::pluck('id')->all());
    }

    public function test_update_validation_error_keeps_recipe_unchanged(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->withSteps(['Alt'])->create(['title' => 'Alter Titel']);
        $this->attachIngredient($recipe, 'Tofu', '100 g');

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'name' => '',
            'steps' => [''],
        ]))->assertSessionHasErrors(['name', 'steps.0']);

        $recipe->refresh();
        $this->assertSame('Alter Titel', $recipe->title);
        $this->assertSame('1. Alt', $recipe->instructions);
        $this->assertSame(['Tofu'], $this->ingredientNamesInDbOrder($recipe));
    }
}
