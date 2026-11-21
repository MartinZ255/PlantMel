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

        // E18: Community-Rezepte einreichen – höchstens 10 neue je Stunde und Nutzer
        RateLimiter::for('community-recipes', function (Request $request) {
            return Limit::perHour(10)
                ->by('user:'.($request->user()?->id ?? $request->ip()))
                ->response(fn () => back()
                    ->with('error', 'Du hast in kurzer Zeit sehr viele Rezepte eingereicht. Bitte versuche es später erneut.'));
        });

        // E18: Meldungen (auch Gäste) – höchstens 5 je Stunde und Absender
        // (Nutzer-ID bzw. IP; die IP wird nur im Cache gezählt, nicht gespeichert)
        RateLimiter::for('recipe-reports', function (Request $request) {
            return Limit::perHour(5)
                ->by($request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip())
                ->response(fn () => back()
                    ->with('error', 'Zu viele Meldungen in kurzer Zeit. Bitte versuche es später erneut.'));
        });
    }
}
