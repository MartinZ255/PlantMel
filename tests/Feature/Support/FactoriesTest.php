<?php

namespace Tests\Feature\Support;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\Ingredient;
use App\Models\IngredientAlternative;
use App\Models\RatingDimension;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\RecipeImport;
use App\Models\RecipeIngredient;
use App\Models\RecipeRating;
use App\Models\User;
use Database\Factories\RecipeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

/**
 * Absicherung der Testinfrastruktur aus Slice 0 (F-09, F-28, F-63):
 * Factories, Login-Helfer und Schutz gegen echte HTTP-/Prozessaufrufe.
 */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_keys_are_enforced_in_the_test_database(): void
    {
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
    }

    public function test_user_factory_host_state(): void
    {
        $user = User::factory()->create();
        $host = User::factory()->host()->create();

        $this->assertFalse($user->fresh()->is_host);
        $this->assertTrue($host->fresh()->is_host);
    }

    public function test_acting_as_user_logs_in_verified_non_host_without_two_factor(): void
    {
        $user = $this->actingAsUser(['name' => 'Testperson']);

        $this->assertTrue(Auth::check());
        $this->assertTrue(Auth::user()->is($user));
        $fresh = $user->fresh();
        $this->assertSame('Testperson', $fresh->name);
        $this->assertFalse($fresh->is_host);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
    }

    public function test_acting_as_host_logs_in_verified_host_without_two_factor(): void
    {
        $host = $this->actingAsHost();

        $this->assertTrue(Auth::user()->is($host));
        $fresh = $host->fresh();
        $this->assertTrue($fresh->is_host);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNull($fresh->two_factor_secret);
    }

    public function test_recipe_factory_uses_numbered_instructions_format(): void
    {
        $recipe = Recipe::factory()->create();

        $this->assertMatchesRegularExpression('/^1\. .+(\n\d+\. .+)*$/', $recipe->instructions);

        $withSteps = Recipe::factory()
            ->withSteps(['Zwiebel schneiden.', 'Anbraten.'], "Schmeckt auch kalt.\n\nHält 2 Tage.")
            ->create();

        $this->assertSame(
            "1. Zwiebel schneiden.\n2. Anbraten.\nNotizen:\nSchmeckt auch kalt.\nHält 2 Tage.",
            $withSteps->instructions,
        );
        $this->assertSame($withSteps->instructions, RecipeFactory::instructionsText(
            ['Zwiebel schneiden.', 'Anbraten.'],
            "Schmeckt auch kalt.\n\nHält 2 Tage.",
        ));
    }

    public function test_recipe_image_factory(): void
    {
        $image = RecipeImage::factory()->create();

        $this->assertNotNull($image->recipe);
        $this->assertStringStartsWith('recipes/', $image->image_path);
    }

    public function test_ingredient_factory_produces_unique_names(): void
    {
        $ingredients = Ingredient::factory()->count(30)->create();

        $this->assertCount(30, $ingredients->pluck('name')->unique());
    }

    public function test_recipe_ingredient_factory(): void
    {
        $recipe = Recipe::factory()->create();

        RecipeIngredient::factory()->count(3)->for($recipe)->create();
        RecipeIngredient::factory()->withoutQuantity()->for($recipe)->create();

        $this->assertCount(4, $recipe->recipeIngredients);
        $this->assertNotNull($recipe->recipeIngredients->first()->ingredient);
        $this->assertNull($recipe->recipeIngredients->last()->quantity);
    }

    public function test_ingredient_alternative_factory(): void
    {
        $alternatives = IngredientAlternative::factory()->count(3)->create();
        $alternative = $alternatives->first();

        $this->assertNotNull($alternative->recipe);
        $this->assertNotNull($alternative->ingredient);
        $this->assertNotNull($alternative->alternativeIngredient);
        $this->assertNotSame($alternative->ingredient_id, $alternative->alternative_ingredient_id);
    }

    public function test_category_factory_and_recipe_pivot(): void
    {
        $recipe = Recipe::factory()
            ->hasAttached(Category::factory()->count(2), [], 'categories')
            ->create();

        $this->assertCount(2, $recipe->categories);
        $this->assertCount(1, $recipe->categories->first()->recipes);
    }

    public function test_rating_dimension_and_recipe_rating_factories(): void
    {
        $recipe = Recipe::factory()->create();

        RecipeRating::factory()->count(3)->for($recipe)->create();

        $this->assertCount(3, $recipe->ratings);
        $this->assertSame(3, RatingDimension::count());
        $recipe->ratings->each(function (RecipeRating $rating) {
            $this->assertGreaterThanOrEqual(1, $rating->value);
            $this->assertLessThanOrEqual(5, $rating->value);
            $this->assertNotNull($rating->dimension);
        });
    }

    public function test_collection_factory_and_recipe_pivot(): void
    {
        $collection = Collection::factory()
            ->hasAttached(Recipe::factory()->count(2), [], 'recipes')
            ->create();

        $this->assertNotNull($collection->user);
        $this->assertCount(2, $collection->recipes);
    }

    public function test_comment_factory(): void
    {
        $comment = Comment::factory()->create();

        $this->assertNotNull($comment->recipe);
        $this->assertNotNull($comment->user);
        $this->assertNotNull($comment->fresh()->created_at);
    }

    public function test_recipe_import_factory_states(): void
    {
        $pending = RecipeImport::factory()->create();
        $this->assertSame('pending', $pending->status);
        $this->assertNull($pending->result);
        $this->assertTrue($pending->user->is_host);
        $this->assertStringStartsWith('https://www.tiktok.com/@example/video/', $pending->url);

        $this->assertSame('pending', RecipeImport::factory()->pending()->create()->status);

        $done = RecipeImport::factory()->done('video')->create()->fresh();
        $this->assertSame('done', $done->status);
        $this->assertSame('video', $done->source);
        $this->assertIsArray($done->result);
        $this->assertSame(
            ['name', 'description', 'duration_minutes', 'servings', 'source', 'tags', 'ingredients', 'steps', 'notes'],
            array_keys($done->result),
        );
        $this->assertSame('TikTok: '.$done->url, $done->result['source']);
        $this->assertSame(['amount', 'name'], array_keys($done->result['ingredients'][0]));

        $failed = RecipeImport::factory()->failed('Zeitüberschreitung')->create();
        $this->assertSame('failed', $failed->status);
        $this->assertSame('Zeitüberschreitung', $failed->error);
        $this->assertNull($failed->result);
    }

    public function test_unfaked_http_request_throws(): void
    {
        $this->expectException(StrayRequestException::class);

        Http::get('https://generativelanguage.googleapis.com/v1beta/models');
    }

    public function test_unfaked_process_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('without a matching fake');

        Process::run(['yt-dlp', '--version']);
    }

    public function test_faked_process_is_still_allowed(): void
    {
        Process::fake(['*yt-dlp*' => Process::result('2025.01.01')]);

        $this->assertSame('2025.01.01', trim(Process::run(['yt-dlp', '--version'])->output()));
    }
}
