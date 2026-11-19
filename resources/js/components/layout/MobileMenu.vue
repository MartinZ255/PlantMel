<script setup lang="ts">
import Button from '@/components/ui/Button.vue';
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

const rowClass = (active: boolean) => [
    'flex min-h-12 w-full items-center gap-2 rounded-lg px-3 text-base font-medium focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus',
    active ? 'bg-tint text-primary' : 'text-ink hover:bg-canvas',
];
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-(--z-modal-backdrop) md:hidden">
            <div
                class="absolute inset-0 bg-ink/55"
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
                class="absolute inset-y-0 right-0 flex w-[min(88vw,22rem)] flex-col overflow-y-auto overscroll-contain bg-surface shadow-overlay focus:outline-none"
            >
                <div
                    class="flex h-(--header-height) shrink-0 items-center justify-between border-b border-line px-4"
                >
                    <h2
                        id="mobile-menu-title"
                        class="text-lg font-semibold text-ink"
                    >
                        Menü
                    </h2>
                    <button
                        type="button"
                        class="inline-flex size-11 items-center justify-center rounded-full text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        aria-label="Menü schließen"
                        @click="close"
                    >
                        <X class="size-6" aria-hidden="true" />
                    </button>
                </div>

                <nav aria-label="Hauptnavigation" class="flex-1 px-3 py-3">
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
                                        class="inline-flex items-center gap-1 text-sm font-normal text-ink-muted"
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
                                    class="size-4 text-ink-muted"
                                    aria-hidden="true"
                                />
                            </Link>

                            <!-- eigene Sammlungen (wie bisher in der Seitenleiste) -->
                            <ul
                                v-if="item.key === 'collections' && user"
                                class="mt-0.5 mb-1 ml-3 flex flex-col gap-0.5 border-l-2 border-line pl-2"
                                aria-label="Deine Sammlungen"
                            >
                                <li v-for="c in collectionLinks" :key="c.id">
                                    <Link
                                        :href="c.href"
                                        :class="[
                                            rowClass(c.current),
                                            'text-wrap-anywhere min-h-11 text-[0.9375rem] font-normal',
                                        ]"
                                        :aria-current="
                                            c.current ? 'page' : undefined
                                        "
                                    >
                                        {{ c.name }}
                                    </Link>
                                </li>
                                <li v-if="!collectionLinks.length">
                                    <p class="px-3 py-2 text-sm text-ink-muted">
                                        Noch keine Sammlungen
                                    </p>
                                </li>
                                <li>
                                    <Link
                                        :href="route('collections.create')"
                                        :class="[
                                            rowClass(false),
                                            'min-h-11 text-[0.9375rem] text-primary',
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

                <div class="flex flex-col gap-2 border-t border-line px-4 py-4">
                    <Button v-if="isHost" :href="route('recipes.create')" block>
                        <Plus class="size-4" aria-hidden="true" />
                        Rezept erstellen
                    </Button>

                    <template v-if="user">
                        <p
                            class="text-wrap-anywhere px-1 text-sm text-ink-muted"
                        >
                            Angemeldet als
                            <span class="font-semibold text-ink">{{
                                user.name
                            }}</span>
                        </p>
                        <Button
                            :href="route('settings.index')"
                            variant="secondary"
                            block
                        >
                            Einstellungen
                        </Button>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-full px-5 font-semibold text-danger hover:bg-danger-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            Abmelden
                        </Link>
                    </template>
                    <template v-else>
                        <Button
                            :href="route('login')"
                            variant="secondary"
                            block
                        >
                            Anmelden
                        </Button>
                        <Button
                            v-if="canRegister"
                            :href="route('register')"
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
