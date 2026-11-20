<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TikTok-Import: höchstens 5 Starts pro Stunde und Nutzer (F-26)
        RateLimiter::for('recipe-imports', function (Request $request) {
            return Limit::perHour(5)
                ->by('user:'.($request->user()?->id ?? $request->ip()))
                ->response(fn (Request $request, array $headers) => response()->json([
                    'message' => 'Zu viele Importe in kurzer Zeit. Bitte später erneut versuchen.',
                ], 429, $headers));
        });
    }
}
