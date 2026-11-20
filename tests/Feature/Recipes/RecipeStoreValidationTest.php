<?php

namespace Tests\Feature\Recipes;

use App\Models\RatingDimension;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Validierung von RecipeController::store (POST /recipes) als Host.
 * Jeder Fall: exakter Fehler-Key in der Session, keine Datenbankzeile.
 */
class RecipeStoreValidationTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAsHost();
    }

    private function assertNothingPersisted(): void
    {
        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('recipe_ingredients', 0);
        $this->assertDatabaseCount('recipe_ratings', 0);
        $this->assertDatabaseCount('recipe_images', 0);
        $this->assertDatabaseCount('ingredients', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_missing_name_is_rejected(): void
    {
        $payload = $this->recipePayload();
        unset($payload['name']);

        $this->from(route('recipes.create'))
            ->post(route('recipes.store'), $payload)
            ->assertRedirect(route('recipes.create'))
            ->assertSessionHasErrors(['name']);

        $this->assertNothingPersisted();
    }

    public function test_name_longer_than_255_is_rejected(): void
    {
        $this->post(route('recipes.store'), $this->recipePayload(['name' => str_repeat('a', 256)]))
            ->assertSessionHasErrors(['name']);

        $this->assertNothingPersisted();
    }

    public function test_empty_ingredient_row_is_rejected_with_indexed_key(): void
    {
        $this->post(route('recipes.store'), $this->recipePayload([
            'ingredients' => [
                ['amount' => '200 g', 'name' => 'Rote Linsen'],
                ['amount' => '1', 'name' => ''],
            ],
        ]))->assertSessionHasErrors(['ingredients.1.name']);

        $this->assertNothingPersisted();
    }

    public function test_empty_step_is_rejected_with_indexed_key(): void
    {
        $this->post(route('recipes.store'), $this->recipePayload([
            'steps' => ['Waschen', ''],
        ]))->assertSessionHasErrors(['steps.1']);

        $this->assertNothingPersisted();
    }

    public function test_missing_ingredients_and_steps_are_rejected(): void
    {
        $payload = $this->recipePayload();
        unset($payload['ingredients'], $payload['steps']);

        $this->post(route('recipes.store'), $payload)
            ->assertSessionHasErrors(['ingredients', 'steps']);

        $this->assertNothingPersisted();
    }

    /**
     * F-06: Das Formular startet mit einer leeren Zutatenzeile und einem leeren Schritt
     * (RecipeForm.vue). Wer nur den Titel ausfüllt, bekommt Fehler unter Keys, die das
     * Formular nicht anzeigt. Hier nur die Server-Seite.
     */
    public function test_form_defaults_with_only_title_produce_row_errors_f06(): void
    {
        $this->post(route('recipes.store'), [
            'name' => 'Nur Titel',
            'description' => '',
            'duration_minutes' => '',
            'servings' => '',
            'source' => '',
            'tags' => [],
            'ingredients' => [['amount' => '', 'name' => '']],
            'steps' => [''],
            'notes' => '',
            'ratings' => [],
        ])->assertSessionHasErrors(['ingredients.0.name', 'steps.0']);

        $this->assertNothingPersisted();
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $this->post(route('recipes.store'), $this->recipePayload([
            'image' => UploadedFile::fake()->create('rezept.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors(['image']);

        $this->assertNothingPersisted();
    }

    /**
     * @return array<string, array{int}>
     */
    public static function outOfRangeRatings(): array
    {
        return [
            'Wert 0 zu klein' => [0],
            'Wert 6 zu groß' => [6],
        ];
    }

    #[DataProvider('outOfRangeRatings')]
    public function test_rating_outside_one_to_five_is_rejected(int $value): void
    {
        $dimension = RatingDimension::factory()->create(['name' => 'Geschmack']);

        $this->post(route('recipes.store'), $this->recipePayload([
            'ratings' => [$dimension->id => $value],
        ]))->assertSessionHasErrors(['ratings.'.$dimension->id]);

        $this->assertNothingPersisted();
    }

    public function test_non_existing_alternative_id_is_rejected(): void
    {
        $this->post(route('recipes.store'), $this->recipePayload([
            'ingredients' => [
                ['amount' => '1', 'name' => 'Tofu', 'alternativeIngredientIds' => [999999]],
            ],
        ]))->assertSessionHasErrors(['ingredients.0.alternativeIngredientIds.0']);

        $this->assertNothingPersisted();
    }

    public function test_negative_duration_and_zero_servings_are_rejected(): void
    {
        $this->post(route('recipes.store'), $this->recipePayload([
            'duration_minutes' => -1,
            'servings' => 0,
        ]))->assertSessionHasErrors(['duration_minutes', 'servings']);

        $this->assertNothingPersisted();
    }
}
