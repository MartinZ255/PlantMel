<script setup lang="ts">
import {
    dropdownItem,
    dropdownItemDanger,
    dropdownPanelBase,
    dropdownPanelState,
} from '@/components/layout/navStyles';
import { useIsHost } from '@/components/layout/useIsHost';
import {
    normalizePath,
    useAppNavigation,
} from '@/composables/useAppNavigation';
import { useDisclosure } from '@/composables/useDisclosure';
import { getInitials } from '@/composables/useInitials';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

/**
 * Gate C: Benutzer-Menü (Einstellungen, Abmelden). Verhalten wie seit
 * Slice 1 (F-04): geschlossen unsichtbar, nicht klickbar und `inert`;
 * Escape schließt mit Fokus zurück, Außenklick und Fokusverlust schließen.
 * Disclosure-Muster (kein `role="menu"`): Tab führt durch die Einträge.
 * Gate C2: Hosts finden hier zusätzlich „Rezept erstellen“ (auch mobil,
 * wo der Knopf der Kopfzeile ausgeblendet ist).
 */
const props = defineProps<{ name: string; settingsCurrent: boolean }>();

const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);
const menu = useDisclosure({ root, trigger });
const isOpen = menu.isOpen;
const isHost = useIsHost();

const initials = computed(() => getInitials(props.name));

// E18: „Zu prüfen“ (Host, mit Anzahl) und „Meine Rezepte“ aus der Navigationsquelle
const page = usePage();
const { accountItems } = useAppNavigation(
    computed(() => normalizePath(page.url)),
);
</script>

<template>
    <div ref="root" class="relative">
        <button
            ref="trigger"
            type="button"
            class="inline-flex min-h-10 items-center gap-2 rounded-pebble border border-line-strong/40 bg-surface/70 py-1 pr-2 pl-1 text-ink transition-colors hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            aria-controls="user-menu"
            :aria-expanded="isOpen ? 'true' : 'false'"
            @click="menu.toggle()"
        >
            <span
                class="inline-flex size-8 items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-forest text-sm font-semibold text-matcha"
                aria-hidden="true"
                >{{ initials }}</span
            >
            <!-- Name aus dem Inhalt (kein aria-label): sichtbarer Text ist Teil des Namens (WCAG 2.5.3) -->
            <span class="sr-only">Benutzermenü</span>
            <span
                class="hidden max-w-40 truncate text-sm font-medium lg:inline"
                >{{ name }}</span
            >
            <ChevronDown class="size-4" aria-hidden="true" />
        </button>
        <div
            id="user-menu"
            :class="[
                dropdownPanelBase,
                dropdownPanelState(isOpen),
                'right-0 w-64',
            ]"
            :inert="!isOpen"
        >
            <p
                class="border-b border-line px-3 pt-1 pb-2.5 text-sm text-wrap-anywhere text-ink-muted"
            >
                Angemeldet als
                <span class="font-semibold text-ink">{{ name }}</span>
            </p>
            <ul class="mt-1 flex flex-col gap-0.5">
                <li v-if="isHost">
                    <Link
                        :href="route('recipes.create')"
                        :class="[
                            dropdownItem,
                            'gap-2 font-semibold text-primary',
                        ]"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Rezept erstellen
                    </Link>
                </li>
                <li v-for="item in accountItems" :key="item.key">
                    <Link
                        :href="item.href"
                        :class="[dropdownItem, 'gap-2']"
                        :aria-current="item.current ? 'page' : undefined"
                        :data-account-nav="item.key"
                    >
                        <span class="min-w-0 flex-1">{{ item.label }}</span>
                        <span
                            v-if="item.count"
                            class="inline-flex min-w-6 items-center justify-center rounded-[60%_40%_55%_45%] bg-berry px-1.5 text-xs font-semibold text-white"
                            data-moderation-count
                            >{{ item.count
                            }}<span class="sr-only"> offen</span></span
                        >
                    </Link>
                </li>
                <li>
                    <Link
                        :href="route('settings.index')"
                        :class="dropdownItem"
                        :aria-current="settingsCurrent ? 'page' : undefined"
                    >
                        Einstellungen
                    </Link>
                </li>
                <li>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        :class="dropdownItemDanger"
                    >
                        Abmelden
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
