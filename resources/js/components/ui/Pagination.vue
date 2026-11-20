<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

/**
 * Gate D2 (F-23): klassische Seitennavigation für serverseitig geblätterte
 * Listen. Die URLs kommen vollständig vom Server (inkl. aller Filter,
 * `withQueryString`), hier wird nichts zusammengesetzt.
 *
 * - `nav aria-label="Seiten"`, aktuelle Seite als Link mit `aria-current="page"`
 * - „Zurück“/„Weiter“ als Links; auf der ersten/letzten Seite nur sichtbar
 *   ausgegraut (kein Link, für Screenreader ausgeblendet)
 * - unter `sm` (320–639 px) statt Seitenzahlen nur „Seite x von y“, damit
 *   nichts umbricht oder seitlich scrollt; ab `sm` Seitenzahlen mit Lücken (…)
 * - `navigate` meldet den abgeschlossenen Seitenwechsel (Fokus/Scroll
 *   übernimmt die Seite, weil nur sie den Listenanfang kennt)
 * - `only`: optional Partial Reload (nur diese Props neu laden)
 */
export interface PaginationPage {
    /** null = Lücke (…) */
    page: number | null;
    url: string | null;
    current: boolean;
}

export interface PaginationData {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
    prevUrl: string | null;
    nextUrl: string | null;
    pages: PaginationPage[];
}

const props = withDefaults(
    defineProps<{
        pagination: PaginationData;
        only?: string[];
    }>(),
    { only: undefined },
);

const emit = defineEmits<{ navigate: [] }>();

const itemBase =
    'pebble-morph inline-flex min-h-11 min-w-11 items-center justify-center gap-1 px-2 text-base whitespace-nowrap sm:gap-1.5 sm:px-3 font-semibold no-underline select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';
const linkIdle = 'text-primary hover:bg-tint active:bg-tint-strong';
const linkCurrent = 'bg-primary text-on-primary shadow-card';
// volle Muted-Farbe: axe prüft auch ausgegrauten Text auf Kontrast
const disabled = 'cursor-default text-ink-muted';

const visitOptions = () => ({
    preserveState: true,
    preserveScroll: true,
    ...(props.only ? { only: props.only } : {}),
    onSuccess: () => emit('navigate'),
});
</script>

<template>
    <nav v-if="pagination.lastPage > 1" aria-label="Seiten" data-pagination>
        <ul
            class="flex items-center justify-between gap-1 sm:flex-wrap sm:justify-center sm:gap-2"
        >
            <li>
                <Link
                    v-if="pagination.prevUrl"
                    :href="pagination.prevUrl"
                    rel="prev"
                    :class="cn(itemBase, linkIdle, 'sm:pr-4')"
                    v-bind="visitOptions()"
                >
                    <ChevronLeft class="size-5 shrink-0" aria-hidden="true" />
                    Zurück
                </Link>
                <span
                    v-else
                    :class="cn(itemBase, disabled, 'sm:pr-4')"
                    aria-hidden="true"
                >
                    <ChevronLeft class="size-5 shrink-0" />
                    Zurück
                </span>
            </li>

            <!-- schmal: nur Position (Seitenzahlen ab sm) -->
            <li class="text-sm font-semibold whitespace-nowrap text-ink sm:hidden">
                Seite {{ pagination.currentPage }} von
                {{ pagination.lastPage }}
            </li>

            <li
                v-for="(item, i) in pagination.pages"
                :key="item.page ?? `gap-${i}`"
                class="hidden sm:block"
            >
                <span
                    v-if="item.page === null || item.url === null"
                    class="inline-flex min-h-11 min-w-6 items-center justify-center text-ink-muted"
                    aria-hidden="true"
                    >…</span
                >
                <Link
                    v-else
                    :href="item.url"
                    :class="cn(itemBase, item.current ? linkCurrent : linkIdle)"
                    :aria-current="item.current ? 'page' : undefined"
                    :aria-label="`Seite ${item.page}`"
                    v-bind="visitOptions()"
                >
                    {{ item.page }}
                </Link>
            </li>

            <li>
                <Link
                    v-if="pagination.nextUrl"
                    :href="pagination.nextUrl"
                    rel="next"
                    :class="cn(itemBase, linkIdle, 'sm:pl-4')"
                    v-bind="visitOptions()"
                >
                    Weiter
                    <ChevronRight class="size-5 shrink-0" aria-hidden="true" />
                </Link>
                <span
                    v-else
                    :class="cn(itemBase, disabled, 'sm:pl-4')"
                    aria-hidden="true"
                >
                    Weiter
                    <ChevronRight class="size-5 shrink-0" />
                </span>
            </li>
        </ul>
    </nav>
</template>
