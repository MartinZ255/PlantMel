<script setup lang="ts">
import {
    dropdownItem,
    dropdownPanelBase,
    dropdownPanelState,
    navLinkBase,
    navLinkState,
} from '@/components/layout/navStyles';
import { useDisclosure } from '@/composables/useDisclosure';
import { Link } from '@inertiajs/vue3';
import { ChevronDown, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';

/**
 * Gate C: „Sammlungen“ in der Kopfnavigation (angemeldet, ab md).
 * Aufklappmenü statt reinem Link, weil die Seitenleiste bisher jede eigene
 * Sammlung direkt erreichbar machte; das bleibt so (ein Klick mehr), dazu
 * die Übersicht und „Neue Sammlung“. Lange Listen scrollen im Menü.
 */
defineProps<{
    overviewHref: string;
    overviewCurrent: boolean;
    sectionActive: boolean;
    collections: {
        id: number | string;
        name: string;
        href: string;
        current: boolean;
    }[];
}>();

const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);
const menu = useDisclosure({ root, trigger });
const isOpen = menu.isOpen;
</script>

<template>
    <div ref="root" class="relative">
        <button
            ref="trigger"
            type="button"
            :class="[navLinkBase, navLinkState(sectionActive)]"
            aria-controls="collections-menu"
            :aria-expanded="isOpen ? 'true' : 'false'"
            @click="menu.toggle()"
        >
            Sammlungen
            <ChevronDown
                class="size-4 transition-transform"
                :class="isOpen && 'rotate-180'"
                aria-hidden="true"
            />
        </button>
        <div
            id="collections-menu"
            :class="[
                dropdownPanelBase,
                dropdownPanelState(isOpen),
                'left-0 w-72',
            ]"
            :inert="!isOpen"
        >
            <Link
                :href="overviewHref"
                :class="[dropdownItem, 'font-semibold']"
                :aria-current="overviewCurrent ? 'page' : undefined"
            >
                Alle Sammlungen
            </Link>
            <p
                id="collections-menu-own"
                class="mt-2 border-t border-line px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-ink-muted uppercase"
            >
                Deine Sammlungen
            </p>
            <ul
                v-if="collections.length"
                class="flex max-h-[min(50vh,20rem)] flex-col gap-0.5 overflow-y-auto"
                aria-labelledby="collections-menu-own"
            >
                <li v-for="c in collections" :key="c.id">
                    <Link
                        :href="c.href"
                        :class="[
                            dropdownItem,
                            'text-wrap-anywhere',
                            c.current && 'bg-tint font-semibold text-primary',
                        ]"
                        :aria-current="c.current ? 'page' : undefined"
                    >
                        {{ c.name }}
                    </Link>
                </li>
            </ul>
            <p v-else class="px-3 py-2 text-sm text-ink-muted">
                Noch keine Sammlungen
            </p>
            <div class="mt-2 border-t border-line pt-2">
                <Link
                    :href="route('collections.create')"
                    :class="[dropdownItem, 'gap-2 text-primary']"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Neue Sammlung
                </Link>
            </div>
        </div>
    </div>
</template>
