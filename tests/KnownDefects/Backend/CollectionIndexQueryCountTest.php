<?php

namespace Tests\KnownDefects\Backend;

use App\Models\Collection;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Bekannter Defekt F-23 (N+1): CollectionController::index ruft trotz
 * withCount('recipes') pro Sammlung $collection->recipes()->count() auf
 * (app/Http/Controllers/CollectionController.php:28).
 *
 * Sollverhalten: Die Anzahl der Queries für collections.index ist
 * unabhängig von der Anzahl der Sammlungen.
 */
#[Group('known-defect')]
#[Group('F-23')]
class CollectionIndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_index_runs_constant_number_of_queries(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $recipes = Recipe::factory()->count(3)->create();

        Collection::factory()->for($user)->count(2)->create()
            ->each(fn (Collection $c) => $c->recipes()->attach($recipes));
        $withTwo = $this->countQueries($user);

        Collection::factory()->for($user)->count(8)->create()
            ->each(fn (Collection $c) => $c->recipes()->attach($recipes));
        $withTen = $this->countQueries($user);

        $this->assertSame(
            $withTwo,
            $withTen,
            "collections.index: {$withTwo} Queries bei 2 Sammlungen, {$withTen} Queries bei 10 Sammlungen (F-23, N+1).",
        );
    }

    private function countQueries(User $user): int
    {
        $this->actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('collections.index'))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }
}
