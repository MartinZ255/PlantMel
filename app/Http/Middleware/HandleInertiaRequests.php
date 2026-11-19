<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'name' => config('app.name'),

            // F-17: Erfolgs- und Fehlermeldungen aus `->with('success'|'error', …)`.
            // Lazy ausgewertet; null, wenn nichts gesetzt ist oder keine Session
            // existiert (z. B. 404 ohne passende Route).
            'flash' => [
                'success' => fn () => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => fn () => $request->hasSession() ? $request->session()->get('error') : null,
            ],

            'auth' => [
                'user' => $request->user()
                    ? [
                        'id'    => $request->user()->id,
                        'name'  => $request->user()->name,
                        'email' => $request->user()->email,
                        // F-08/E2: ISO-8601 (UTC) oder null. Der Profilhinweis
                        // (settings/Profile.vue) prueft zusaetzlich
                        // `mustVerifyEmail`, erscheint also nur bei Schalter AN.
                        'email_verified_at' => $request->user()->email_verified_at?->copy()->utc()->toIso8601ZuluString(),
                    ]
                    : null,
                'collections' => $request->user()
                    ? $request->user()
                        ->collections()
                        ->orderByDesc('updated_at')
                        ->get(['id', 'name'])
                        ->map(fn ($collection) => [
                            'id'   => $collection->id,
                            'name' => $collection->name,
                        ])
                        ->values()
                    : [],
            ],
        ];
    }
}
