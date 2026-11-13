<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests brauchen kein gebautes Vite-Manifest (public/build).
        $this->withoutVite();

        // Schutz gegen echte Netzwerkaufrufe (z. B. Gemini-API) und echte
        // Prozessstarts (z. B. yt-dlp): Jeder nicht gefakte Aufruf wirft eine
        // Exception. Tests, die solche Aufrufe brauchen, faken sie gezielt
        // mit Http::fake([...]) bzw. Process::fake([...]).
        //
        // Hinweis: Process::preventStrayProcesses() greift laut Framework
        // (PendingProcess::run/start) nur, wenn die Process-Factory "aufzeichnet".
        // Deshalb zuerst Process::fake([]) ohne Handler – das schaltet die
        // Aufzeichnung ein, ohne irgendeinen Befehl zu erlauben. Spätere
        // Process::fake([...])-Aufrufe in Tests ergänzen gezielt Handler.
        Http::preventStrayRequests();
        Process::fake([]);
        Process::preventStrayProcesses();
    }

    /**
     * Meldet einen verifizierten Nutzer ohne 2FA und ohne Host-Rechte an.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsUser(array $attributes = []): User
    {
        $user = User::factory()
            ->withoutTwoFactor()
            ->create(array_merge(['is_host' => false], $attributes));

        $this->actingAs($user);

        return $user;
    }

    /**
     * Meldet einen verifizierten Host (is_host = true) ohne 2FA an.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsHost(array $attributes = []): User
    {
        $user = User::factory()
            ->withoutTwoFactor()
            ->host()
            ->create($attributes);

        $this->actingAs($user);

        return $user;
    }
}
