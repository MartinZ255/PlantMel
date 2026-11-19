<script setup lang="ts">
import BrandLogo from '@/components/layout/BrandLogo.vue';
import CollectionsMenu from '@/components/layout/CollectionsMenu.vue';
import { navLinkBase, navLinkState } from '@/components/layout/navStyles';
import UserMenu from '@/components/layout/UserMenu.vue';
import Button from '@/components/ui/Button.vue';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { Link } from '@inertiajs/vue3';
import { Lock, Menu, Plus } from 'lucide-vue-next';
import { toRef } from 'vue';
import { route } from 'ziggy-js';

/**
 * Gate C: Kopfnavigation (ersetzt die Seitenleiste).
 *
 * Ab `md` (768 px): Hauptnavigation inline; „Sammlungen“ ist für Angemeldete
 * ein Aufklappmenü (CollectionsMenu). Unter `md`: Menü-Knopf, der das
 * Mobilmenü öffnet (MobileMenu). Rechts: „Rezept erstellen“ (Hosts),
 * Benutzer-Menü bzw. für Gäste „Anmelden“/„Registrieren“.
 */
const props = defineProps<{
    currentPath: string;
    menuOpen: boolean;
}>();

const emit = defineEmits<{ (e: 'open-menu'): void }>();

const nav = useAppNavigation(toRef(props, 'currentPath'));
const { user, canRegister, isHost, startHref, startCurrent, items } = nav;
const collectionLinks = nav.collectionLinks;
</script>

<template>
    <header class="sticky top-0 z-(--z-header) border-b border-line bg-surface">
        <div
            class="mx-auto flex h-(--header-height) w-full max-w-(--content-max) items-center gap-2 px-4 sm:px-6 lg:gap-6 lg:px-8"
        >
            <BrandLogo :href="startHref" :current="startCurrent" />

            <!-- Hauptnavigation ab md -->
            <nav aria-label="Hauptnavigation" class="hidden md:block">
                <ul class="flex items-center gap-1">
                    <li v-for="item in items" :key="item.key">
                        <CollectionsMenu
                            v-if="item.key === 'collections' && user"
                            :overview-href="item.href"
                            :overview-current="item.current"
                            :section-active="item.sectionActive"
                            :collections="collectionLinks"
                        />
                        <Link
                            v-else
                            :href="item.href"
                            :class="[
                                navLinkBase,
                                navLinkState(item.sectionActive),
                            ]"
                            :aria-current="item.current ? 'page' : undefined"
                        >
                            {{ item.label }}
                            <template v-if="item.requiresAuth && !user">
                                <Lock
                                    class="size-3.5 text-ink-muted"
                                    aria-hidden="true"
                                />
                                <span class="sr-only">
                                    (Anmeldung erforderlich)</span
                                >
                            </template>
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <Button
                    v-if="isHost"
                    :href="route('recipes.create')"
                    size="sm"
                    class="hidden sm:inline-flex"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Rezept erstellen
                </Button>

                <!-- Gäste: direkte Wege statt verstecktem Menü -->
                <template v-if="!user">
                    <Link
                        :href="route('login')"
                        :class="[navLinkBase, 'text-ink hover:bg-canvas']"
                    >
                        Anmelden
                    </Link>
                    <Button
                        v-if="canRegister"
                        :href="route('register')"
                        size="sm"
                        class="hidden md:inline-flex"
                    >
                        Registrieren
                    </Button>
                </template>

                <UserMenu
                    v-else
                    :name="user.name"
                    :settings-current="nav.isCurrent(route('settings.index'))"
                />

                <!-- Menü-Knopf unter md -->
                <button
                    type="button"
                    class="inline-flex size-11 items-center justify-center rounded-full text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus md:hidden"
                    aria-label="Menü"
                    aria-controls="mobile-menu"
                    :aria-expanded="menuOpen ? 'true' : 'false'"
                    @click="emit('open-menu')"
                >
                    <Menu class="size-6" aria-hidden="true" />
                </button>
            </div>
        </div>
    </header>
</template>
