<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressionstests zu F-07 und F-65 (ADR-002): Globales Löschen von Kategorien
 * und Zutaten nur mit Request-Parameter `confirmed` (truthy: 1, "1", true,
 * "true", "on", "yes"; als Query-String oder im Body).
 *
 * Ohne Bestätigung: 409 `{message, requires_confirmation: true, recipes_count}`
 * (Kategorie) bzw. `{message, requires_confirmation: true, alternatives_count}`
 * (Zutat); nichts wird gelöscht. Die 422-Fälle der Zutat haben Vorrang.
 * Umbenennen liefert zusätzlich `recipes_count`.
 */
#[Group('F-07')]
#[Group('F-65')]
class CatalogDeleteConfirmationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Kategorien
    // ---------------------------------------------------------------

    public function test_category_destroy_without_confirmation_returns_409_and_keeps_everything(): void
    {
        $this->actingAsHost();
        $category = Category::factory()->create(['name' => 'Vegan']);
        $recipes = Recipe::factory()->count(2)->create();
        $category->recipes()->attach($recipes);

        $this->deleteJson(route('categories.destroy', $category))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'Die Kategorie „Vegan“ wird in 2 Rezepten verwendet und dort überall entfernt. Bitte bestätige das Löschen.',
                'requires_confirmation' => true,
                'recipes_count' => 2,
            ]);

        $this->assertModelExists($category);
        $this->assertDatabaseCount('recipe_categories', 2);
    }

    public function test_category_confirmation_messages_for_zero_and_one_recipe(): void
    {
        $this->actingAsHost();
        $unused = Category::factory()->create(['name' => 'Leer']);
        $single = Category::factory()->create(['name' => 'Einmal']);
        $single->recipes()->attach(Recipe::factory()->create());

        $this->deleteJson(route('categories.destroy', $unused))
            ->assertStatus(409)
            ->assertJsonPath('recipes_count', 0)
            ->assertJsonPath('message', 'Die Kategorie „Leer“ wird in keinem Rezept verwendet. Bitte bestätige das Löschen.');

        $this->deleteJson(route('categories.destroy', $single))
            ->assertStatus(409)
            ->assertJsonPath('recipes_count', 1)
            ->assertJsonPath('message', 'Die Kategorie „Einmal“ wird in einem Rezept verwendet und dort entfernt. Bitte bestätige das Löschen.');
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function falsyConfirmations(): array
    {
        return [
            '0' => [0],
            '"0"' => ['0'],
            'false' => [false],
            '"nein"' => ['nein'],
            'leer' => [''],
        ];
    }

    #[DataProvider('falsyConfirmations')]
    public function test_category_destroy_with_falsy_confirmation_still_returns_409(mixed $value): void
    {
        $this->actingAsHost();
        $category = Category::factory()->create();

        $this->deleteJson(route('categories.destroy', $category), ['confirmed' => $value])
            ->assertStatus(409);

        $this->assertModelExists($category);
    }

    public function test_category_destroy_confirmed_via_query_string(): void
    {
        $this->actingAsHost();
        $category = Category::factory()->create();
        $category->recipes()->attach(Recipe::factory()->create());

        $this->deleteJson(route('categories.destroy', ['category' => $category, 'confirmed' => 1]))
            ->assertOk()
            ->assertExactJson(['status' => 'deleted']);

        $this->assertModelMissing($category);
        $this->assertDatabaseCount('recipe_categories', 0);
        $this->assertDatabaseCount('recipes', 1);
    }

    public function test_category_destroy_confirmed_via_form_body(): void
    {
        $this->actingAsHost();
        $category = Category::factory()->create();

        $this->delete(route('categories.destroy', $category), ['confirmed' => 'true'])
            ->assertOk()
            ->assertExactJson(['status' => 'deleted']);

        $this->assertModelMissing($category);
    }

    public function test_category_update_returns_recipes_count(): void
    {
        $this->actingAsHost();
        $category = Category::factory()->create(['name' => 'Alt']);
        $category->recipes()->attach(Recipe::factory()->count(3)->create());
        Category::factory()->create()->recipes()->attach(Recipe::factory()->create());

        $this->putJson(route('categories.update', $category), ['name' => 'Neu'])
            ->assertOk()
            ->assertExactJson(['id' => $category->id, 'name' => 'Neu', 'recipes_count' => 3]);
    }

    // ---------------------------------------------------------------
    // Zutaten
    // ---------------------------------------------------------------

    public function test_ingredient_used_in_recipe_returns_422_even_when_confirmed(): void
    {
        $this->actingAsHost();
        $usage = RecipeIngredient::factory()->create();

        foreach ([[], ['confirmed' => true]] as $data) {
            $this->deleteJson(route('ingredients.destroy', $usage->ingredient), $data)
                ->assertUnprocessable()
                ->assertExactJson([
                    'message' => 'Die Zutat wird in mindestens einem Rezept verwendet und kann nicht gelöscht werden.',
                ]);
        }

        $this->assertModelExists($usage->ingredient);
    }

    public function test_ingredient_used_as_alternative_returns_422_even_when_confirmed(): void
    {
        $this->actingAsHost();
        $alternative = IngredientAlternative::factory()->create();

        foreach ([[], ['confirmed' => true]] as $data) {
            $this->deleteJson(route('ingredients.destroy', $alternative->alternativeIngredient), $data)
                ->assertUnprocessable()
                ->assertExactJson([
                    'message' => 'Die Zutat ist in mindestens einem Rezept als Alternative hinterlegt und kann nicht gelöscht werden.',
                ]);
        }

        $this->assertModelExists($alternative);
    }

    public function test_ingredient_destroy_without_confirmation_reports_cascading_alternatives_f65(): void
    {
        $this->actingAsHost();
        $base = Ingredient::factory()->create(['name' => 'Tofu']);
        // Basiszutat zweier Alternativen, aber in keinem Rezept als Zutat
        $alternatives = IngredientAlternative::factory()->count(2)->create(['ingredient_id' => $base->id]);

        $this->deleteJson(route('ingredients.destroy', $base))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'Mit der Zutat „Tofu“ werden 2 hinterlegte Alternativen gelöscht. Bitte bestätige das Löschen.',
                'requires_confirmation' => true,
                'alternatives_count' => 2,
            ]);

        $this->assertModelExists($base);
        $alternatives->each(fn ($alt) => $this->assertModelExists($alt));

        $this->deleteJson(route('ingredients.destroy', $base), ['confirmed' => true])
            ->assertOk()
            ->assertExactJson(['status' => 'deleted']);

        $this->assertModelMissing($base);
        $alternatives->each(fn ($alt) => $this->assertModelMissing($alt));
    }

    public function test_ingredient_confirmation_messages_for_zero_and_one_alternative(): void
    {
        $this->actingAsHost();
        $unused = Ingredient::factory()->create(['name' => 'Salz']);
        $single = Ingredient::factory()->create(['name' => 'Reis']);
        IngredientAlternative::factory()->create(['ingredient_id' => $single->id]);

        $this->deleteJson(route('ingredients.destroy', $unused))
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'Die Zutat „Salz“ wird global gelöscht. Bitte bestätige das Löschen.',
                'requires_confirmation' => true,
                'alternatives_count' => 0,
            ]);

        $this->deleteJson(route('ingredients.destroy', $single))
            ->assertStatus(409)
            ->assertJsonPath('alternatives_count', 1)
            ->assertJsonPath('message', 'Mit der Zutat „Reis“ wird eine hinterlegte Alternative gelöscht. Bitte bestätige das Löschen.');

        $this->assertModelExists($unused);
        $this->assertModelExists($single);
    }

    public function test_ingredient_destroy_confirmed_via_query_string(): void
    {
        $this->actingAsHost();
        $ingredient = Ingredient::factory()->create();

        $this->deleteJson(route('ingredients.destroy', ['ingredient' => $ingredient, 'confirmed' => '1']))
            ->assertOk()
            ->assertExactJson(['status' => 'deleted']);

        $this->assertModelMissing($ingredient);
    }

    public function test_ingredient_update_returns_distinct_recipes_count(): void
    {
        $this->actingAsHost();
        $tofu = Ingredient::factory()->create(['name' => 'Tofu']);
        $seitan = Ingredient::factory()->create(['name' => 'Seitan']);

        $asIngredient = Recipe::factory()->create();
        RecipeIngredient::factory()->count(2)->create(['recipe_id' => $asIngredient->id, 'ingredient_id' => $tofu->id]);
        IngredientAlternative::factory()->create([
            'recipe_id' => $asIngredient->id,
            'ingredient_id' => $tofu->id,
            'alternative_ingredient_id' => $seitan->id,
        ]);

        $asAlternative = Recipe::factory()->create();
        IngredientAlternative::factory()->create([
            'recipe_id' => $asAlternative->id,
            'ingredient_id' => $seitan->id,
            'alternative_ingredient_id' => $tofu->id,
        ]);

        Recipe::factory()->create(); // ohne Tofu

        $this->putJson(route('ingredients.update', $tofu), ['name' => 'Räuchertofu'])
            ->assertOk()
            ->assertExactJson([
                'id' => $tofu->id,
                'name' => 'Räuchertofu',
                'description' => $tofu->description,
                'recipes_count' => 2,
            ]);
    }
}
