<?php

namespace Tests\Feature\Collections;

use App\Models\Category;
use App\Models\Collection;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Charakterisierung CollectionController: index, create, show (Ist-Zustand).
 */
class CollectionPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_own_collections_with_recipe_count(): void
    {
        $user = $this->actingAsUser();
        $other = User::factory()->withoutTwoFactor()->create();

        $this->travelTo(now()->subDays(2));
        $older = Collection::factory()->for($user)->create(['name' => 'Ältere', 'description' => null]);
        $this->travelBack();
        $newer = Collection::factory()->for($user)->create(['name' => 'Neuere', 'description' => 'Beschreibung']);
        Collection::factory()->for($other)->create(['name' => 'Fremde']);

        $newer->recipes()->attach(Recipe::factory()->count(3)->create());

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('collections/Index')
                ->has('collections', 2)
                // Sortierung: updated_at absteigend
                ->has('collections.0', fn (Assert $c) => $c
                    ->where('id', $newer->id)
                    ->where('name', 'Neuere')
                    ->where('description', 'Beschreibung')
                    ->where('recipeCount', 3)
                )
                ->has('collections.1', fn (Assert $c) => $c
                    ->where('id', $older->id)
                    ->where('name', 'Ältere')
                    ->where('description', null)
                    ->where('recipeCount', 0)
                )
            );
    }

    public function test_index_is_empty_for_user_without_collections(): void
    {
        $this->actingAsUser();
        Collection::factory()->count(2)->create();

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('collections/Index')
                ->has('collections', 0)
            );
    }

    public function test_create_renders_component(): void
    {
        $this->actingAsUser();

        $this->get(route('collections.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('collections/Create'));
    }

    public function test_show_renders_owner_collection_with_recipes(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create([
            'name' => 'Lieblinge',
            'description' => 'Meine besten',
        ]);

        $recipe = Recipe::factory()->create(['title' => 'Linsencurry', 'duration_minutes' => 35]);
        $recipe->categories()->attach(Category::factory()->create(['name' => 'Vegan']));
        RecipeImage::factory()->for($recipe)->create(['image_path' => 'recipes/curry.jpg']);
        // E14: nur Geschmack zählt, „Gesamt“ wird ignoriert
        $geschmack = RatingDimension::factory()->create(['name' => 'Geschmack']);
        RecipeRating::factory()->for($recipe)->create(['dimension_id' => $geschmack->id, 'value' => 4]);
        RecipeRating::factory()->for($recipe)->create(['dimension_id' => RatingDimension::factory()->create(['name' => 'Gesamt']), 'value' => 1]);

        $plain = Recipe::factory()->create(['title' => 'Brot', 'duration_minutes' => null]);

        $collection->recipes()->attach([$recipe->id, $plain->id]);

        $this->get(route('collections.show', $collection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('collections/Show')
                ->has('collection', fn (Assert $c) => $c
                    ->where('id', $collection->id)
                    ->where('name', 'Lieblinge')
                    ->where('description', 'Meine besten')
                    ->whereType('updatedAt', 'string')
                )
                ->has('recipes', 2)
                ->where('recipes', function ($recipes) use ($recipe, $plain) {
                    $byId = collect($recipes)->keyBy('id');

                    $this->assertSame([
                        'id' => $recipe->id,
                        'name' => 'Linsencurry',
                        // E14: Geschmack 4 (vorher Ø aller Dimensionen)
                        'rating' => 4,
                        'tags' => ['Vegan'],
                        'time' => '35 Min',
                        'image' => Storage::url('recipes/curry.jpg'),
                    ], $byId[$recipe->id]);

                    $this->assertSame([
                        'id' => $plain->id,
                        'name' => 'Brot',
                        'rating' => 0,
                        'tags' => [],
                        'time' => '–',
                        'image' => null,
                    ], $byId[$plain->id]);

                    return true;
                })
            );
    }

    public function test_show_without_taste_dimension_shows_recipes_unrated(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();
        $recipe = Recipe::factory()->create();
        RecipeRating::factory()->for($recipe)->create(['dimension_id' => RatingDimension::factory()->create(['name' => 'Gesamt']), 'value' => 5]);
        $collection->recipes()->attach($recipe);

        $this->get(route('collections.show', $collection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('recipes.0.rating', 0)
            );
    }

    /**
     * Ist-Zustand F-49: CollectionController::show nutzt Storage::url() auf der
     * Default-Disk ("local"), während Rezeptbilder auf der Disk "public" liegen
     * und Home/Rezeptseiten Storage::disk('public')->url() verwenden.
     */
    public function test_show_image_url_uses_default_disk_f49(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();
        $recipe = Recipe::factory()->create();
        RecipeImage::factory()->for($recipe)->create(['image_path' => 'recipes/bild.jpg']);
        $collection->recipes()->attach($recipe);

        $this->assertSame('local', config('filesystems.default'));

        $this->get(route('collections.show', $collection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('recipes.0.image', Storage::disk('local')->url('recipes/bild.jpg'))
                ->where('recipes.0.image', '/storage/recipes/bild.jpg')
            );
    }
}
