/** Geteilter Nutzer aus HandleInertiaRequests::share. */
export interface SharedUser {
    id: number;
    name: string;
    email: string;
    /** ISO-8601 (UTC) oder null; seit Slice 4 immer vorhanden (F-08). */
    email_verified_at: string | null;
    /**
     * Gate C2: Host-Recht (users.is_host), global geteilt. Steuert nur die
     * Anzeige von „Rezept erstellen“; die Routen prüft der Server.
     */
    is_host: boolean;
}

export interface SharedCollection {
    id: number | string;
    name: string;
}

export interface Auth {
    user: SharedUser | null;
    collections: SharedCollection[];
}

/** F-17: Meldungen aus `->with('success'|'error', …)`; null, wenn nichts gesetzt ist. */
export interface Flash {
    success: string | null;
    error: string | null;
}

/** E17: Schalter aus HandleInertiaRequests::share. */
export interface Features {
    /** Knopf „Mit Google anmelden“ nur bei gesetzter Google-Konfiguration. */
    googleLogin: boolean;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    features: Features;
    flash: Flash;
};

/** Gate C1b: Rezept-Auswahl der Startseite (routes/web.php, Route `landing`). */
export interface FeaturedRecipe {
    id: number;
    name: string;
    /** öffentliche URL über die Disk `public` (immer gesetzt) */
    image: string;
    /** Kategorienamen */
    categories: string[];
    duration_minutes: number | null;
}
