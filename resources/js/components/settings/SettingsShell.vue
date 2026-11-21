<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { Link } from '@inertiajs/vue3';
import { KeyRound, LayoutGrid, Sparkles, UserRound } from 'lucide-vue-next';
import { route } from 'ziggy-js';

/**
 * Gate C2: gemeinsamer Rahmen der Konto-Einstellungen (Übersicht, Profil,
 * Passwort) im Stil „Natur & organisch“: heller Blob-Kopf mit Fraunces-
 * Titel, darunter eine Bereichsnavigation (Kiesel, aktiv in Matcha) und
 * der Inhalt auf Papierflächen. Genau ein h1 (Slot `#title` für ein
 * kursives Akzentwort).
 */
defineProps<{
    pageTitle: string;
    title: string;
    description: string;
    current: 'index' | 'profile' | 'password' | 'ai';
}>();

const sections = [
    {
        key: 'index',
        label: 'Übersicht',
        route: 'settings.index',
        icon: LayoutGrid,
    },
    { key: 'profile', label: 'Profil', route: 'profile.edit', icon: UserRound },
    {
        key: 'password',
        label: 'Passwort',
        route: 'user-password.edit',
        icon: KeyRound,
    },
    // E18: eigener Gemini-Schlüssel
    {
        key: 'ai',
        label: 'KI-Import',
        route: 'settings.ai.edit',
        icon: Sparkles,
    },
] as const;
</script>

<template>
    <AppLayout :page-title="pageTitle" bleed>
        <section
            class="relative overflow-hidden"
            aria-labelledby="settings-title"
        >
            <BlobBackdrop tone="light" />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute top-4 right-[6%] hidden h-28 animate-float-slow text-tint-strong [--float-rotate:22deg] sm:block lg:h-36"
            />
            <div
                class="relative mx-auto w-full max-w-(--content-max) px-4 pt-8 pb-6 sm:px-6 sm:pt-12 lg:px-8"
            >
                <p
                    class="mb-3 inline-flex items-center gap-2 rounded-seed bg-surface/80 px-3.5 py-1.5 text-sm font-semibold text-primary shadow-card"
                >
                    <LeafOrnament variant="sprout" class="size-4 text-moss" />
                    Dein Konto
                </p>
                <h1
                    id="settings-title"
                    class="font-display-wonk text-[clamp(2.3rem,9vw,4.25rem)] leading-[1.02] font-semibold text-balance text-wrap-anywhere text-ink"
                >
                    <slot name="title">{{ title }}</slot>
                </h1>
                <p class="mt-3 max-w-2xl text-lg text-ink-muted sm:text-xl">
                    {{ description }}
                </p>
            </div>
        </section>

        <div
            class="mx-auto grid w-full max-w-(--content-max) gap-6 px-4 pb-12 sm:px-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-10 lg:px-8"
        >
            <nav aria-label="Einstellungen" class="min-w-0">
                <ul
                    class="flex flex-wrap gap-2 lg:sticky lg:top-[calc(var(--header-height)+1.5rem)] lg:flex-col"
                >
                    <li v-for="s in sections" :key="s.key">
                        <Link
                            :href="route(s.route)"
                            class="inline-flex min-h-11 w-full items-center gap-2.5 pebble-morph px-4 text-[0.9375rem] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                            :class="
                                current === s.key
                                    ? 'bg-matcha text-forest shadow-card'
                                    : 'bg-surface/70 text-ink hover:bg-tint hover:text-primary'
                            "
                            :aria-current="
                                current === s.key ? 'page' : undefined
                            "
                        >
                            <component
                                :is="s.icon"
                                class="size-4.5 shrink-0"
                                aria-hidden="true"
                            />
                            {{ s.label }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="flex min-w-0 flex-col gap-6">
                <slot />
            </div>
        </div>
    </AppLayout>
</template>
