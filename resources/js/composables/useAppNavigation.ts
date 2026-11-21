import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type Ref } from 'vue';
import { route } from 'ziggy-js';

/**
 * Gate C: gemeinsame Daten der Kopfnavigation und des Mobilmenüs, damit
 * beide dieselben Einträge, Ziele und aktiven Zustände zeigen.
 */

/** Pfad ohne Query und ohne Schrägstrich am Ende. */
export function normalizePath(url: string): string {
    const path = new URL(url, 'http://localhost').pathname;
    return path.length > 1 ? path.replace(/\/+$/, '') : path;
}

export interface MainNavItem {
    key:
        | 'recipes'
        | 'community'
        | 'ingredients'
        | 'collections'
        | 'myIngredients';
    label: string;
    href: string;
    /** Nur für Angemeldete nutzbar (Gäste werden zur Anmeldung geleitet) */
    requiresAuth: boolean;
    /** Exakt diese Seite: aria-current="page" */
    current: boolean;
    /** Bereich aktiv (Unterseiten), nur optisch hervorgehoben */
    sectionActive: boolean;
}

/** E18: Konto-Einträge (Nutzermenü, Mobilmenü): „Zu prüfen“ (Host) und „Meine Rezepte“. */
export interface AccountNavItem {
    key: 'moderation' | 'myRecipes';
    label: string;
    href: string;
    current: boolean;
    /** Anzahl für das Badge (nur „Zu prüfen“), sonst null */
    count: number | null;
}

export function useAppNavigation(currentPath: Ref<string>) {
    const page = usePage<AppPageProps<{ canRegister?: boolean }>>();

    const user = computed(() => page.props.auth?.user ?? null);
    const collections = computed(() => page.props.auth?.collections ?? []);
    const canRegister = computed(() => page.props.canRegister ?? true);
    // Gate C2: Host-Status kommt global aus `auth.user.is_host`, siehe
    // `components/layout/useIsHost.ts` (nicht mehr aus der Seiten-Prop).

    // F-21: Gäste landen auf der öffentlichen Startseite
    const startHref = computed(() =>
        user.value ? route('home') : route('landing'),
    );

    const isCurrent = (href: string) =>
        normalizePath(href) === currentPath.value;

    const startCurrent = computed(() => isCurrent(startHref.value));

    const items = computed<MainNavItem[]>(() => {
        const p = currentPath.value;
        const recipes = route('recipes.index');
        const community = route('community.index');
        const ingredients = route('IngredientSearch');
        const collectionsIndex = route('collections.index');
        const myIngredients = route('myIngredients');
        return [
            {
                key: 'recipes',
                label: 'Rezepte',
                href: recipes,
                requiresAuth: false,
                current: isCurrent(recipes),
                sectionActive:
                    isCurrent(recipes) || /^\/rezept(\/|e\/)/.test(p),
            },
            // E18: Community-Rezepte (öffentlich)
            {
                key: 'community',
                label: 'Community',
                href: community,
                requiresAuth: false,
                current: isCurrent(community),
                sectionActive: /^\/community(\/|$)/.test(p),
            },
            {
                key: 'ingredients',
                label: 'Zutatensuche',
                href: ingredients,
                requiresAuth: false,
                current: isCurrent(ingredients),
                sectionActive: isCurrent(ingredients),
            },
            {
                key: 'collections',
                label: 'Sammlungen',
                href: collectionsIndex,
                requiresAuth: true,
                current: isCurrent(collectionsIndex),
                sectionActive: /^\/sammlung(en)?(\/|$)/.test(p),
            },
            {
                key: 'myIngredients',
                label: 'Meine Zutaten',
                href: myIngredients,
                requiresAuth: true,
                current: isCurrent(myIngredients),
                sectionActive: isCurrent(myIngredients),
            },
        ];
    });

    // E18: „Zu prüfen“ nur für Hosts (Anzahl aus der geteilten Prop
    // `moderation`), „Meine Rezepte“ für alle Angemeldeten
    const accountItems = computed<AccountNavItem[]>(() => {
        if (!user.value) return [];
        const items: AccountNavItem[] = [];
        if (user.value.is_host) {
            const href = route('moderation.index');
            items.push({
                key: 'moderation',
                label: 'Zu prüfen',
                href,
                current: isCurrent(href),
                count: page.props.moderation?.count ?? 0,
            });
        }
        const mine = route('community.mine');
        items.push({
            key: 'myRecipes',
            label: 'Meine Rezepte',
            href: mine,
            current: isCurrent(mine),
            count: null,
        });
        return items;
    });

    const collectionLinks = computed(() =>
        collections.value.map((c) => {
            const href = route('collections.show', c.id);
            return { id: c.id, name: c.name, href, current: isCurrent(href) };
        }),
    );

    return {
        user,
        canRegister,
        startHref,
        startCurrent,
        items,
        accountItems,
        collectionLinks,
        isCurrent,
    };
}
