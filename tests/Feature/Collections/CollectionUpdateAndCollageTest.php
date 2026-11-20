<?php

namespace Tests\Feature\Collections;

use App\Models\Collection;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sammlungen umbenennen (PATCH collections.update) und Bild-Collage der
 * Übersicht (CollectionController@index liefert `images`).
 */
class CollectionUpdateAndCollageTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ update

    public function test_owner_can_update_name_and_description(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create([
            'name' => 'Alt',
            'description' => 'Alte Beschreibung',
        ]);
        $recipe = Recipe::factory()->create();
        $collection->recipes()->attach($recipe);

        $this->from(route('collections.show', $collection))
            ->patch(route('collections.update', $collection), [
                'name' => 'Neu',
                'description' => 'Neue Beschreibung',
                // Versuch, den Eigentümer zu wechseln, wird ignoriert
                'user_id' => User::factory()->create()->id,
            ])
            ->assertRedirect(route('collections.show', $collection))
            ->assertSessionHas('success', 'Sammlung wurde gespeichert.')
            ->assertSessionHasNoErrors();

        $fresh = $collection->fresh();
        $this->assertSame('Neu', $fresh->name);
        $this->assertSame('Neue Beschreibung', $fresh->description);
        $this->assertSame($user->id, $fresh->user_id);
        // Rezepte bleiben unverändert
        $this->assertSame([$recipe->id], $fresh->recipes()->pluck('recipes.id')->all());
    }

    public function test_update_with_empty_description_stores_null(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create(['description' => 'x']);

        $this->patch(route('collections.update', $collection), [
            'name' => 'Ohne Text',
            'description' => '',
        ])->assertRedirect(route('collections.show', $collection));

        $this->assertNull($collection->fresh()->description);
        $this->assertSame('Ohne Text', $collection->fresh()->name);
    }

    public function test_foreign_user_gets_403_and_nothing_changes(): void
    {
        $owner = User::factory()->withoutTwoFactor()->create();
        $collection = Collection::factory()->for($owner)->create([
            'name' => 'Fremd',
            'description' => 'bleibt',
        ]);
        $updatedAt = $collection->fresh()->updated_at;

        $this->actingAsUser();

        $this->patch(route('collections.update', $collection), [
            'name' => 'Übernommen',
            'description' => 'geändert',
        ])->assertForbidden();

        // Besitzprüfung vor der Validierung (wie addRecipe): auch ungültig -> 403
        $this->patch(route('collections.update', $collection), ['name' => ''])
            ->assertForbidden();

        $fresh = $collection->fresh();
        $this->assertSame('Fremd', $fresh->name);
        $this->assertSame('bleibt', $fresh->description);
        $this->assertEquals($updatedAt, $fresh->updated_at);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $collection = Collection::factory()->create(['name' => 'Gast']);

        $this->patch(route('collections.update', $collection), ['name' => 'X'])
            ->assertRedirect(route('login'));

        $this->assertSame('Gast', $collection->fresh()->name);
    }

    public function test_unknown_collection_returns_404(): void
    {
        $this->actingAsUser();

        $this->patch(route('collections.update', 999999), ['name' => 'X'])
            ->assertNotFound();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidUpdatePayloads(): array
    {
        return [
            'name fehlt' => [['description' => 'x'], 'name'],
            'name leer' => [['name' => ''], 'name'],
            'name zu lang (256)' => [['name' => str_repeat('a', 256)], 'name'],
            'name kein String' => [['name' => ['a']], 'name'],
            'description kein String' => [['name' => 'Ok', 'description' => ['x']], 'description'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidUpdatePayloads')]
    public function test_update_validation(array $payload, string $field): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create(['name' => 'Bleibt']);

        $this->from(route('collections.show', $collection))
            ->patch(route('collections.update', $collection), $payload)
            ->assertRedirect(route('collections.show', $collection))
            ->assertSessionHasErrors($field);

        $this->assertSame('Bleibt', $collection->fresh()->name);
    }

    public function test_name_with_255_characters_is_accepted(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();
        $name = str_repeat('ä', 255);

        $this->patch(route('collections.update', $collection), ['name' => $name])
            ->assertSessionHasNoErrors();

        $this->assertSame($name, $collection->fresh()->name);
    }

    // ------------------------------------------------------------------ index: images

    public function test_index_images_are_limited_to_four_and_only_recipes_with_image(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();

        // 5 Rezepte mit Bild, 2 ohne
        $withImage = Recipe::factory()->count(5)->create();
        foreach ($withImage as $i => $recipe) {
            RecipeImage::factory()->for($recipe)->create(['image_path' => "recipes/bild-{$i}.jpg"]);
        }
        $withoutImage = Recipe::factory()->count(2)->create();
        $collection->recipes()->attach($withImage->pluck('id')->merge($withoutImage->pluck('id')));
        $expected = collect(range(0, 4))
            ->map(fn ($i) => Storage::disk('public')->url("recipes/bild-{$i}.jpg"))
            ->all();

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('collections/Index')
                ->has('collections', 1)
                ->where('collections.0.recipeCount', 7)
                ->has('collections.0.images', 4)
                ->where('collections.0.images', fn ($images) => collect($images)->every(
                    fn ($url) => in_array($url, $expected, true)
                ) && collect($images)->unique()->count() === 4)
            );
    }

    public function test_index_uses_first_image_of_each_recipe_on_public_disk(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();
        $recipe = Recipe::factory()->create();
        RecipeImage::factory()->for($recipe)->create(['image_path' => 'recipes/erstes.jpg']);
        RecipeImage::factory()->for($recipe)->create(['image_path' => 'recipes/zweites.jpg']);
        $collection->recipes()->attach($recipe);

        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('collections.0.images', [Storage::disk('public')->url('recipes/erstes.jpg')])
            );
    }

    public function test_index_images_empty_without_recipe_images(): void
    {
        $user = $this->actingAsUser();
        $empty = Collection::factory()->for($user)->create(['name' => 'Leer']);
        $noImages = Collection::factory()->for($user)->create(['name' => 'Ohne Bilder']);
        $noImages->recipes()->attach(Recipe::factory()->count(2)->create());

        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('collections', 2)
                ->where('collections.0.images', [])
                ->where('collections.1.images', [])
            );
    }

    public function test_index_images_only_from_own_collections(): void
    {
        $user = $this->actingAsUser();
        $own = Collection::factory()->for($user)->create();
        $ownRecipe = Recipe::factory()->create();
        RecipeImage::factory()->for($ownRecipe)->create(['image_path' => 'recipes/eigen.jpg']);
        $own->recipes()->attach($ownRecipe);

        $foreign = Collection::factory()->create();
        $foreignRecipe = Recipe::factory()->create();
        RecipeImage::factory()->for($foreignRecipe)->create(['image_path' => 'recipes/fremd.jpg']);
        $foreign->recipes()->attach($foreignRecipe);

        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('collections', 1)
                ->where('collections.0.id', $own->id)
                ->where('collections.0.images', [Storage::disk('public')->url('recipes/eigen.jpg')])
            );
    }

    public function test_index_limits_images_per_collection_independently(): void
    {
        $user = $this->actingAsUser();
        $a = Collection::factory()->for($user)->create();
        $b = Collection::factory()->for($user)->create();

        foreach ([$a, $b] as $collection) {
            $recipes = Recipe::factory()->count(5)->create();
            foreach ($recipes as $recipe) {
                RecipeImage::factory()->for($recipe)->create();
            }
            $collection->recipes()->attach($recipes);
        }

        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('collections', 2)
                ->has('collections.0.images', 4)
                ->has('collections.1.images', 4)
            );
    }

    public function test_index_query_count_does_not_grow_with_collections(): void
    {
        $user = $this->actingAsUser();

        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('collections.index'))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $makeCollection = function () use ($user) {
            $collection = Collection::factory()->for($user)->create();
            $recipes = Recipe::factory()->count(3)->create();
            foreach ($recipes as $recipe) {
                RecipeImage::factory()->for($recipe)->create();
            }
            $collection->recipes()->attach($recipes);
        };

        $makeCollection();
        $few = $count();

        foreach (range(1, 4) as $_) {
            $makeCollection();
        }
        $many = $count();

        $this->assertSame($few, $many, 'Abfragen dürfen nicht pro Sammlung wachsen (N+1).');
    }
}
