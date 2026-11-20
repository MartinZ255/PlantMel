<?php

namespace Tests\Feature\Recipes;

use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Gate C2: Das Bearbeiten-Formular (RecipeController::edit) liefert das aktuelle
 * Rezeptbild mit – im selben Format wie die Detailseite (show), über die Disk `public`.
 * Kopf und Bildvorschau in recipes/Edit.vue und RecipeForm.vue zeigen es an.
 */
class RecipeEditImageTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_edit_provides_first_image_url_on_public_disk(): void
    {
        Storage::fake('public');
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/a.jpg']);
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/b.jpg']);

        $response = $this->get(route('recipes.edit', $recipe));

        $response->assertOk();
        $image = $this->pageProps($response)['recipe']['image'];
        $this->assertSame(Storage::disk('public')->url('recipes/a.jpg'), $image);
        $this->assertStringEndsWith('/storage/recipes/a.jpg', $image);
    }

    public function test_edit_image_matches_show_image(): void
    {
        Storage::fake('public');
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/gleich.jpg']);

        $edit = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe']['image'];
        $show = $this->pageProps($this->get(route('recipes.show', $recipe)))['recipe']['image'];

        $this->assertSame($show, $edit);
    }

    public function test_edit_image_is_null_without_image(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();

        $form = $this->pageProps($this->get(route('recipes.edit', $recipe)))['recipe'];

        $this->assertArrayHasKey('image', $form);
        $this->assertNull($form['image']);
    }
}
