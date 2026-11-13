<?php

namespace Tests\Feature\Recipes;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeRating;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Charakterisierung der öffentlichen Rezept-Detailseite (RecipeController::show, GET /rezept/{recipe}).
 */
class RecipeShowTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_guest_sees_show_component_with_expected_prop_structure(): void
    {
        $recipe = Recipe::factory()->create([
            'title' => 'Tofu-Curry',
            'description' => 'Cremig.',
            'duration_minutes' => 30,
            'servings' => 2,
        ]);
        $recipe->categories()->attach(Category::factory()->create(['name' => 'Vegan']));

        $response = $this->get(route('recipes.show', $recipe));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('recipes/Show')
            ->has('recipe', fn (Assert $r) => $r
                ->where('id', $recipe->id)
                ->where('name', 'Tofu-Curry')
                ->where('description', 'Cremig.')
                ->where('image', null)
                ->where('duration_minutes', 30)
                ->where('categories', ['Vegan'])
                ->where('tags', []) // fest leer, Kategorien kommen unter "categories"
                ->where('servings', 2)
                ->where('rating', null)
                ->where('ratingCount', 0)
                ->where('ratingDimensions', [])
                ->missing('source') // F-15: Feld entfällt
                ->has('ingredients')
                ->has('steps')
                ->has('notes')
                ->where('createdAt', $recipe->created_at->toDateString())
                ->where('updatedAt', $recipe->updated_at->toDateString())
            )
            ->where('collections', [])
            ->where('isHost', false)
        );
    }

    public function test_instructions_are_split_into_steps_and_notes(): void
    {
        $recipe = Recipe::factory()->create([
            'instructions' => "1. Zwiebeln schneiden\n2. Anbraten\n\n3. Servieren\nNotizen:\nHält 3 Tage\nAuch kalt lecker",
        ]);

        $props = $this->pageProps($this->get(route('recipes.show', $recipe)));

        $this->assertSame(['Zwiebeln schneiden', 'Anbraten', 'Servieren'], $props['recipe']['steps']);
        $this->assertSame(['Hält 3 Tage', 'Auch kalt lecker'], $props['recipe']['notes']);
    }

    public function test_instructions_without_numbering_or_notes_header(): void
    {
        $recipe = Recipe::factory()->create([
            'instructions' => "Alles mischen\r\nnotizen\r\nohne Doppelpunkt, Groß-/Kleinschreibung egal",
        ]);

        $props = $this->pageProps($this->get(route('recipes.show', $recipe)));

        $this->assertSame(['Alles mischen'], $props['recipe']['steps']);
        $this->assertSame(['ohne Doppelpunkt, Groß-/Kleinschreibung egal'], $props['recipe']['notes']);
    }

    public function test_rating_is_aggregated_per_dimension_and_overall(): void
    {
        $recipe = Recipe::factory()->create();
        $geschmack = RatingDimension::factory()->create(['name' => 'Geschmack']);
        $aufwand = RatingDimension::factory()->create(['name' => 'Aufwand']);
        $optik = RatingDimension::factory()->create(['name' => 'Optik']);

        foreach ([[$geschmack, 4], [$aufwand, 5], [$optik, 5]] as [$dim, $value]) {
            RecipeRating::factory()->create([
                'recipe_id' => $recipe->id,
                'dimension_id' => $dim->id,
                'value' => $value,
            ]);
        }
        // Bewertung eines anderen Rezepts darf nicht einfließen.
        RecipeRating::factory()->create(['dimension_id' => $geschmack->id, 'value' => 1]);

        $props = $this->pageProps($this->get(route('recipes.show', $recipe)));

        $this->assertSame(4.7, $props['recipe']['rating']); // (4+5+5)/3, eine Nachkommastelle
        $this->assertSame(3, $props['recipe']['ratingCount']);
        $this->assertEquals([
            ['id' => $geschmack->id, 'name' => 'Geschmack', 'avg' => 4.0, 'count' => 1],
            ['id' => $aufwand->id, 'name' => 'Aufwand', 'avg' => 5.0, 'count' => 1],
            ['id' => $optik->id, 'name' => 'Optik', 'avg' => 5.0, 'count' => 1],
        ], $props['recipe']['ratingDimensions']);
    }

    public function test_image_url_uses_first_image_on_public_disk(): void
    {
        Storage::fake('public');
        $recipe = Recipe::factory()->create();
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/a.jpg']);
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/b.jpg']);

        $props = $this->pageProps($this->get(route('recipes.show', $recipe)));

        $this->assertSame(Storage::disk('public')->url('recipes/a.jpg'), $props['recipe']['image']);
        $this->assertStringEndsWith('/storage/recipes/a.jpg', $props['recipe']['image']);
    }

    public function test_ingredients_include_amount_name_and_alternative_names(): void
    {
        $recipe = Recipe::factory()->create();
        $tofu = $this->attachIngredient($recipe, 'Tofu', '300 g');
        $this->attachIngredient($recipe, 'Salz', null);
        $seitan = Ingredient::factory()->create(['name' => 'Seitan']);
        $tempeh = Ingredient::factory()->create(['name' => 'Tempeh']);

        foreach ([$seitan, $tempeh] as $alt) {
            IngredientAlternative::factory()->create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $tofu->id,
                'alternative_ingredient_id' => $alt->id,
            ]);
        }
        // Alternative in einem anderen Rezept gehört nicht dazu.
        IngredientAlternative::factory()->create([
            'ingredient_id' => $tofu->id,
            'alternative_ingredient_id' => Ingredient::factory()->create(['name' => 'Jackfruit'])->id,
        ]);

        $props = $this->pageProps($this->get(route('recipes.show', $recipe)));

        $this->assertSame([
            ['amount' => '300 g', 'name' => 'Tofu', 'alternatives' => ['Seitan', 'Tempeh']],
            ['amount' => null, 'name' => 'Salz', 'alternatives' => []],
        ], $props['recipe']['ingredients']);
    }

    public function test_guest_gets_empty_collections(): void
    {
        $recipe = Recipe::factory()->create();
        Collection::factory()->create(['name' => 'Fremd']);

        $this->get(route('recipes.show', $recipe))
            ->assertInertia(fn (Assert $page) => $page->where('collections', []));
    }

    public function test_user_gets_only_own_collections_sorted_by_name(): void
    {
        $recipe = Recipe::factory()->create();
        $user = $this->actingAsUser();
        $zeta = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Zeta']);
        $alpha = Collection::factory()->create(['user_id' => $user->id, 'name' => 'Alpha']);
        Collection::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'Fremd']);

        $props = $this->pageProps($this->get(route('recipes.show', $recipe)));

        $this->assertSame([
            ['id' => $alpha->id, 'name' => 'Alpha'],
            ['id' => $zeta->id, 'name' => 'Zeta'],
        ], $props['collections']);
        $this->assertFalse($props['isHost']);
    }

    public function test_host_flag_is_true_for_host(): void
    {
        $recipe = Recipe::factory()->create();
        $this->actingAsHost();

        $this->get(route('recipes.show', $recipe))
            ->assertInertia(fn (Assert $page) => $page->where('isHost', true));
    }

    public function test_unknown_recipe_returns_404(): void
    {
        $this->get('/rezept/999999')->assertNotFound();
    }

    public function test_factory_instructions_format_matches_show_parser(): void
    {
        $recipe = Recipe::factory()->withSteps(['A', 'B'], "X\nY")->create();

        $this->assertSame(RecipeFactory::instructionsText(['A', 'B'], "X\nY"), $recipe->instructions);

        $props = $this->pageProps($this->get(route('recipes.show', $recipe)));
        $this->assertSame(['A', 'B'], $props['recipe']['steps']);
        $this->assertSame(['X', 'Y'], $props['recipe']['notes']);
    }
}
