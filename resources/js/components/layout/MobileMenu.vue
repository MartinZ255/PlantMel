<script setup lang="ts">
import Button from '@/components/ui/Button.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useModal } from '@/composables/useModal';
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Lock, Plus, X } from 'lucide-vue-next';
import { computed, ref, toRef } from 'vue';
import { route } from 'ziggy-js';

/**
 * Gate C: Mobilmenü (< md) als Seitenblatt von rechts.
 *
 * Modal über `useModal` (F-29/F-40): außerhalb von `#app` (Teleport), Hintergrund
 * `inert`, Scroll-Sperre, Escape schließt und gibt den Fokus an den
 * Menü-Knopf zurück, Fokus beim Öffnen auf den ersten Eintrag, Fokusfalle.
 * Tippen auf die abgedunkelte Fläche schließt. Geschlossen ist nichts davon
 * im DOM (nicht per Tab erreichbar).
 */
const props = defineProps<{ open: boolean; currentPath: string }>();
const emit = defineEmits<{ (e: 'close'): void }>();

const nav = useAppNavigation(toRef(props, 'currentPath'));
const { user, canRegister, isHost, startHref, startCurrent, items } = nav;
const collectionLinks = nav.collectionLinks;

const panel = ref<HTMLElement | null>(null);
// Fokus beim Öffnen auf den ersten Eintrag (Link ist eine Komponente → $el)
const firstLink = ref<{ $el?: HTMLElement } | null>(null);
const firstLinkEl = computed<HTMLElement | null>(
    () => firstLink.value?.$el ?? null,
);

useModal({
    open: () => props.open,
    dialog: panel,
    onClose: () => emit('close'),
    initialFocus: firstLinkEl,
    trapFocus: true,
});

const close = () => emit('close');

// C1b: Waldgrün-Blatt; aktive Seite als Matcha-Kiesel
const rowClass = (active: boolean) => [
    'flex min-h-12 w-full items-center gap-2 rounded-[1.1rem_0.85rem_1.2rem_0.9rem] px-3.5 text-base font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus',
    active ? 'bg-matcha text-forest' : 'text-on-forest hover:bg-forest-soft',
];
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-(--z-modal-backdrop) md:hidden">
            <div
                class="absolute inset-0 bg-forest-deep/55 backdrop-blur-[3px]"
                aria-hidden="true"
                @click="close"
            ></div>
            <div
                id="mobile-menu"
                ref="panel"
                role="dialog"
                aria-modal="true"
                aria-labelledby="mobile-menu-title"
                tabindex="-1"
                class="absolute inset-y-0 right-0 flex w-[min(88vw,22rem)] flex-col overflow-x-hidden overflow-y-auto overscroll-contain rounded-l-[2.5rem] bg-forest-deep grain-light on-forest shadow-overlay focus:outline-none"
            >
                <!-- dekorative Ebene: weicher Blob und ein Zweig -->
                <div
                    class="pointer-events-none absolute inset-0 overflow-hidden"
                    aria-hidden="true"
                >
                    <span
                        class="absolute -top-20 -right-16 size-64 rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-forest-soft/70"
                    ></span>
                    <LeafOrnament
                        variant="eucalyptus"
                        class="absolute -bottom-8 -left-6 h-52 rotate-[-28deg] text-forest-soft"
                    />
                </div>
                <div
                    class="relative flex h-(--header-height) shrink-0 items-center justify-between px-5"
                >
                    <h2
                        id="mobile-menu-title"
                        class="font-display-wonk text-2xl font-semibold text-on-forest"
                    >
                        Menü
                    </h2>
                    <button
                        type="button"
                        class="inline-flex size-11 items-center justify-center rounded-pebble bg-forest-soft text-on-forest hover:bg-forest focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        aria-label="Menü schließen"
                        @click="close"
                    >
                        <X class="size-6" aria-hidden="true" />
                    </button>
                </div>

                <nav
                    aria-label="Hauptnavigation"
                    class="relative flex-1 px-3 py-2"
                >
                    <ul class="flex flex-col gap-0.5">
                        <li>
                            <Link
                                ref="firstLink"
                                :href="startHref"
                                :class="rowClass(startCurrent)"
                                :aria-current="
                                    startCurrent ? 'page' : undefined
                                "
                            >
                                Startseite
                            </Link>
                        </li>
                        <li v-for="item in items" :key="item.key">
                            <Link
                                :href="item.href"
                                :class="rowClass(item.current)"
                                :aria-current="
                                    item.current ? 'page' : undefined
                                "
                            >
                                <span class="flex-1">{{ item.label }}</span>
                                <template v-if="item.requiresAuth && !user">
                                    <span
                                        class="inline-flex items-center gap-1 text-sm font-normal text-on-forest-muted"
                                        aria-hidden="true"
                                    >
                                        <Lock class="size-3.5" />
                                        Anmeldung
                                    </span>
                                    <span class="sr-only">
                                        (Anmeldung erforderlich)</span
                                    >
                                </template>
                                <ChevronRight
                                    v-else
                                    class="size-4 opacity-70"
                                    aria-hidden="true"
                                />
                            </Link>

                            <!-- eigene Sammlungen (wie bisher in der Seitenleiste) -->
                            <ul
                                v-if="item.key === 'collections' && user"
                                class="mt-0.5 mb-1 ml-4 flex flex-col gap-0.5 border-l-2 border-forest-soft pl-2"
                                aria-label="Deine Sammlungen"
                            >
                                <li v-for="c in collectionLinks" :key="c.id">
                                    <Link
                                        :href="c.href"
                                        :class="[
                                            rowClass(c.current),
                                            'min-h-11 text-[0.9375rem] font-normal text-wrap-anywhere',
                                        ]"
                                        :aria-current="
                                            c.current ? 'page' : undefined
                                        "
                                    >
                                        {{ c.name }}
                                    </Link>
                                </li>
                                <li v-if="!collectionLinks.length">
                                    <p
                                        class="px-3 py-2 text-sm text-on-forest-muted"
                                    >
                                        Noch keine Sammlungen
                                    </p>
                                </li>
                                <li>
                                    <Link
                                        :href="route('collections.create')"
                                        :class="[
                                            rowClass(false),
                                            'min-h-11 text-[0.9375rem] text-matcha',
                                        ]"
                                    >
                                        <Plus
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Neue Sammlung
                                    </Link>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </nav>

                <div
                    class="relative flex flex-col gap-2 border-t border-forest-soft px-4 py-5"
                >
                    <Button
                        v-if="isHost"
                        :href="route('recipes.create')"
                        variant="lime"
                        block
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Rezept erstellen
                    </Button>

                    <template v-if="user">
                        <p
                            class="px-1 text-sm text-wrap-anywhere text-on-forest-muted"
                        >
                            Angemeldet als
                            <span class="font-semibold text-on-forest">{{
                                user.name
                            }}</span>
                        </p>
                        <Button
                            :href="route('settings.index')"
                            variant="outline-light"
                            block
                        >
                            Einstellungen
                        </Button>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-pebble px-5 font-semibold text-berry-surface underline-offset-4 hover:bg-forest-soft hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            Abmelden
                        </Link>
                    </template>
                    <template v-else>
                        <Button
                            :href="route('login')"
                            variant="outline-light"
                            block
                        >
                            Anmelden
                        </Button>
                        <Button
                            v-if="canRegister"
                            :href="route('register')"
                            variant="lime"
                            block
                        >
                            Registrieren
                        </Button>
                    </template>
                </div>
            </div>
        </div>
    </Teleport>
</template>
