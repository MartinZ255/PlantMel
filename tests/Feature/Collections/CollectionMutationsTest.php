<?php

namespace Tests\Feature\Collections;

use App\Models\Collection;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Charakterisierung CollectionController: store, addRecipe, removeRecipe, delete.
 */
class CollectionMutationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_collection_for_current_user_and_redirects(): void
    {
        $user = $this->actingAsUser();

        $this->post(route('collections.store'), [
            'name' => 'Sommer',
            'description' => 'Leichte Gerichte',
            // Versuch, eine fremde user_id unterzuschieben, wird ignoriert.
            'user_id' => User::factory()->create()->id,
        ])
            ->assertRedirect(route('collections.index'))
            ->assertSessionHas('success', 'Sammlung wurde erstellt.');

        $this->assertDatabaseCount('collections', 1);
        $this->assertDatabaseHas('collections', [
            'user_id' => $user->id,
            'name' => 'Sommer',
            'description' => 'Leichte Gerichte',
        ]);
    }

    public function test_store_without_description_saves_null(): void
    {
        $user = $this->actingAsUser();

        $this->post(route('collections.store'), ['name' => 'Ohne Text'])
            ->assertRedirect(route('collections.index'));

        $this->assertDatabaseHas('collections', [
            'user_id' => $user->id,
            'name' => 'Ohne Text',
            'description' => null,
        ]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidStorePayloads(): array
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
    #[DataProvider('invalidStorePayloads')]
    public function test_store_validation(array $payload, string $field): void
    {
        $this->actingAsUser();

        $this->from(route('collections.create'))
            ->post(route('collections.store'), $payload)
            ->assertRedirect(route('collections.create'))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('collections', 0);
    }

    public function test_store_accepts_name_with_255_characters(): void
    {
        $this->actingAsUser();

        $this->post(route('collections.store'), ['name' => str_repeat('a', 255)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('collections', 1);
    }

    public function test_add_recipe_is_idempotent(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();
        $recipe = Recipe::factory()->create();

        $this->from(route('recipes.show', $recipe))
            ->post(route('collections.addRecipe', $collection), ['recipe_id' => $recipe->id])
            ->assertRedirect(route('recipes.show', $recipe))
            ->assertSessionHas('success', 'Rezept wurde zur Sammlung hinzugefügt.');

        $this->post(route('collections.addRecipe', $collection), ['recipe_id' => $recipe->id])
            ->assertRedirect();

        $this->assertDatabaseCount('collection_recipes', 1);
        $this->assertDatabaseHas('collection_recipes', [
            'collection_id' => $collection->id,
            'recipe_id' => $recipe->id,
        ]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidAddRecipePayloads(): array
    {
        return [
            'recipe_id fehlt' => [[]],
            'recipe_id existiert nicht' => [['recipe_id' => 999999]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidAddRecipePayloads')]
    public function test_add_recipe_validation(array $payload): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();

        $this->post(route('collections.addRecipe', $collection), $payload)
            ->assertSessionHasErrors('recipe_id');

        $this->assertDatabaseCount('collection_recipes', 0);
    }

    public function test_remove_recipe_detaches_and_redirects_to_show(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();
        [$keep, $remove] = Recipe::factory()->count(2)->create();
        $collection->recipes()->attach([$keep->id, $remove->id]);

        $this->delete(route('collections.removeRecipe', [$collection, $remove]))
            ->assertRedirect(route('collections.show', $collection))
            ->assertSessionHas('success', 'Rezept wurde aus der Sammlung entfernt.');

        $this->assertDatabaseCount('collection_recipes', 1);
        $this->assertDatabaseHas('collection_recipes', ['collection_id' => $collection->id, 'recipe_id' => $keep->id]);
        // Das Rezept selbst bleibt bestehen.
        $this->assertDatabaseHas('recipes', ['id' => $remove->id]);
    }

    public function test_delete_removes_collection_and_pivot_but_not_recipes(): void
    {
        $user = $this->actingAsUser();
        $collection = Collection::factory()->for($user)->create();
        $recipes = Recipe::factory()->count(2)->create();
        $collection->recipes()->attach($recipes);

        $this->delete(route('collections.delete', $collection))
            ->assertRedirect(route('collections.index'))
            ->assertSessionHas('success', 'Sammlung wurde gelöscht.');

        $this->assertModelMissing($collection);
        $this->assertDatabaseCount('collection_recipes', 0);
        $this->assertDatabaseCount('recipes', 2);
    }

    public function test_deleting_user_cascades_to_collections_and_pivot(): void
    {
        $user = User::factory()->create();
        $collection = Collection::factory()->for($user)->create();
        $collection->recipes()->attach(Recipe::factory()->create());
        $foreign = Collection::factory()->create();

        $user->delete();

        $this->assertModelMissing($collection);
        $this->assertModelExists($foreign);
        $this->assertDatabaseCount('collection_recipes', 0);
        $this->assertDatabaseCount('recipes', 1);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function foreignActions(): array
    {
        return [
            'show' => ['show'],
            'addRecipe' => ['addRecipe'],
            'removeRecipe' => ['removeRecipe'],
            'delete' => ['delete'],
        ];
    }

    #[DataProvider('foreignActions')]
    public function test_foreign_user_gets_403_and_nothing_changes(string $action): void
    {
        $owner = User::factory()->withoutTwoFactor()->create();
        $collection = Collection::factory()->for($owner)->create(['name' => 'Fremd']);
        $attached = Recipe::factory()->create();
        $other = Recipe::factory()->create();
        $collection->recipes()->attach($attached);
        $updatedAt = $collection->fresh()->updated_at;

        $this->actingAsUser();

        $response = match ($action) {
            'show' => $this->get(route('collections.show', $collection)),
            'addRecipe' => $this->post(route('collections.addRecipe', $collection), ['recipe_id' => $other->id]),
            'removeRecipe' => $this->delete(route('collections.removeRecipe', [$collection, $attached])),
            'delete' => $this->delete(route('collections.delete', $collection)),
        };

        $response->assertForbidden();

        $this->assertModelExists($collection);
        $fresh = $collection->fresh();
        $this->assertSame('Fremd', $fresh->name);
        $this->assertSame($owner->id, $fresh->user_id);
        $this->assertEquals($updatedAt, $fresh->updated_at);
        $this->assertSame([$attached->id], $fresh->recipes()->pluck('recipes.id')->all());
        $this->assertDatabaseCount('collection_recipes', 1);
    }

    public function test_add_recipe_to_foreign_collection_is_checked_before_validation(): void
    {
        $collection = Collection::factory()->create();
        $this->actingAsUser();

        // Ist-Zustand: Besitzprüfung vor Validierung -> 403 statt 302 mit Fehlern.
        $this->post(route('collections.addRecipe', $collection), ['recipe_id' => 999999])
            ->assertForbidden();
    }

    public function test_unknown_collection_returns_404(): void
    {
        $this->actingAsUser();

        $this->get(route('collections.show', 999999))->assertNotFound();
        $this->delete(route('collections.delete', 999999))->assertNotFound();
    }
}
