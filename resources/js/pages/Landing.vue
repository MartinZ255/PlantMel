<script setup lang="ts">
import FeaturedRecipeBand from '@/components/landing/FeaturedRecipeBand.vue';
import LandingHeroArt from '@/components/landing/LandingHeroArt.vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import Button from '@/components/ui/Button.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import Reveal from '@/components/ui/Reveal.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
import type { AppPageProps, FeaturedRecipe } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Carrot, FolderHeart, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = withDefaults(
    defineProps<{
        canRegister?: boolean;
        /** Gate C1b: bis zu 6 Rezepte mit Bild, neueste zuerst (routes/web.php) */
        featuredRecipes?: FeaturedRecipe[];
    }>(),
    { canRegister: false, featuredRecipes: () => [] },
);

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth?.user ?? null);

const featured = computed(() => props.featuredRecipes ?? []);

// E8/F-55: nur vorhandene Funktionen bewerben (genau drei Punkte)
const benefits = [
    {
        icon: Search,
        title: 'Rezepte finden',
        text: 'Rezepte durchsuchen und nach Zutaten und Kategorien filtern.',
    },
    {
        icon: FolderHeart,
        title: 'Sammlungen anlegen',
        text: 'Eigene Sammlungen erstellen und verwalten – etwa für „Unter der Woche“, „Besuch“ oder „Meal Prep“.',
    },
    {
        icon: Carrot,
        title: 'Zutaten im Blick',
        text: 'In der Zutatensuche alle Zutaten auf einen Blick finden.',
    },
];
</script>

<template>
    <AppLayout page-title="PlantMel – Deine Rezept-Sammlung" bleed>
        <!-- ============================================================
             Einstieg: große Fraunces-Headline + organische Bildkomposition
             ============================================================ -->
        <section
            aria-labelledby="landing-title"
            class="relative overflow-hidden"
        >
            <BlobBackdrop tone="light" />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute top-6 -left-6 h-32 animate-float-slow text-tint-strong [--float-rotate:-28deg] sm:left-[3%] lg:h-44"
            />

            <div
                class="relative mx-auto grid w-full max-w-(--content-max) items-center gap-10 px-4 pt-10 pb-12 sm:px-6 sm:pt-14 lg:grid-cols-[1.05fr_1fr] lg:gap-14 lg:px-8 lg:pt-16 lg:pb-20"
            >
                <div class="relative min-w-0">
                    <p
                        class="mb-6 inline-flex items-center gap-2 rounded-seed bg-surface/80 px-3.5 py-1.5 text-sm font-semibold text-primary shadow-card"
                    >
                        <LeafOrnament
                            variant="sprout"
                            class="size-4 text-moss"
                        />
                        Pflanzenbetonte Küche
                    </p>
                    <h1
                        id="landing-title"
                        class="font-display-wonk text-[clamp(2.4rem,10.5vw,5.75rem)] leading-[0.98] font-semibold text-balance [overflow-wrap:anywhere] text-ink"
                    >
                        Lieblings&shy;rezepte,
                        <span class="relative inline-block whitespace-nowrap">
                            <span class="accent-word text-primary"
                                >liebevoll</span
                            >
                            <!-- handgezeichneter Strich unter dem Akzentwort -->
                            <svg
                                viewBox="0 0 220 24"
                                preserveAspectRatio="none"
                                class="absolute -bottom-1 left-0 h-[0.22em] w-full text-berry"
                                aria-hidden="true"
                                focusable="false"
                            >
                                <path
                                    d="M3 17 C 40 6 92 4 140 10 C 168 13 196 9 217 5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </span>
                        gesammelt.
                    </h1>
                    <p
                        class="mt-7 max-w-xl text-lg leading-relaxed text-ink-muted sm:text-xl"
                    >
                        Speichere, sortiere und entdecke pflanzenbetonte Rezepte
                        für deinen Alltag – mit PlantMel behältst du den
                        Überblick.
                    </p>

                    <div
                        class="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
                    >
                        <!-- F-21: öffentlicher Einstieg ohne Anmeldung -->
                        <Button :href="route('recipes.index')" size="lg">
                            Rezepte entdecken
                            <ArrowRight class="size-5" aria-hidden="true" />
                        </Button>
                        <Button
                            v-if="user"
                            :href="route('home')"
                            variant="secondary"
                            size="lg"
                        >
                            Zu meinem Dashboard
                        </Button>
                        <Button
                            v-else
                            :href="route('login')"
                            variant="secondary"
                            size="lg"
                        >
                            Anmelden
                        </Button>
                    </div>
                    <p v-if="!user && canRegister" class="mt-5 text-ink-muted">
                        Neu hier?
                        <Link
                            :href="route('register')"
                            class="font-semibold text-primary underline decoration-berry decoration-2 underline-offset-4 hover:text-primary-hover"
                        >
                            Konto erstellen
                        </Link>
                    </p>
                </div>

                <LandingHeroArt :recipes="featured.slice(0, 3)" />
            </div>
        </section>

        <!-- ============================================================
             Vorteile: Waldgrün-Band mit welligen Übergängen
             ============================================================ -->
        <!-- Wellen liegen IN der Waldgrün-Fläche (Naturgrund-Farbe), damit Körnung und
             Verlauf ohne Nahtstelle bis an die Welle reichen -->
        <section
            aria-labelledby="landing-benefits-title"
            class="relative overflow-hidden bg-forest grain-light on-forest"
        >
            <BlobBackdrop tone="forest" />
            <WaveDivider :variant="1" flip class="text-canvas" />
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute top-[24%] right-[4%] h-40 animate-sway text-forest-soft [--float-rotate:24deg] lg:h-56"
            />
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute -bottom-10 left-[38%] hidden h-48 rotate-[64deg] text-forest-soft lg:block"
            />

            <div
                class="relative mx-auto grid w-full max-w-(--content-max) gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[0.85fr_1.15fr] lg:gap-16 lg:px-8 lg:py-16"
            >
                <Reveal class="min-w-0">
                    <p
                        class="mb-3 inline-flex items-center gap-2 text-sm font-semibold tracking-[0.14em] text-matcha uppercase"
                    >
                        <LeafOrnament variant="sprout" class="size-4" />
                        Deine Küche, sortiert
                    </p>
                    <h2
                        id="landing-benefits-title"
                        class="font-display-wonk text-4xl font-semibold text-balance text-on-forest sm:text-5xl"
                    >
                        Was du mit PlantMel
                        <span class="accent-word text-matcha">machen</span>
                        kannst
                    </h2>
                </Reveal>

                <ul
                    class="flex flex-col gap-5"
                    aria-labelledby="landing-benefits-title"
                >
                    <Reveal
                        v-for="(b, i) in benefits"
                        :key="b.title"
                        as="li"
                        :delay="i * 120"
                        class="relative flex min-w-0 items-start gap-5 rounded-[2rem_1.5rem_2.25rem_1.75rem] bg-forest-deep/45 p-5 sm:p-6"
                        :class="i === 1 ? 'lg:ml-12' : i === 2 ? 'lg:ml-4' : ''"
                    >
                        <span
                            class="relative blob-mask inline-flex size-16 shrink-0 items-center justify-center bg-matcha text-forest shadow-card"
                            :class="['blob-1', 'blob-3', 'blob-5'][i]"
                            aria-hidden="true"
                        >
                            <component :is="b.icon" class="size-7" />
                        </span>
                        <div class="min-w-0">
                            <h3
                                class="flex items-baseline gap-3 font-display text-2xl font-semibold text-on-forest"
                            >
                                <span
                                    class="accent-word text-lg text-matcha"
                                    aria-hidden="true"
                                    >0{{ i + 1 }}</span
                                >
                                {{ b.title }}
                            </h3>
                            <p class="mt-1.5 text-on-forest-muted">
                                {{ b.text }}
                            </p>
                        </div>
                    </Reveal>
                </ul>
            </div>
            <WaveDivider :variant="2" class="text-canvas" />
        </section>

        <!-- ============================================================
             Auswahl: wischbares Band mit echten Rezeptbildern
             ============================================================ -->
        <FeaturedRecipeBand v-if="featured.length" :recipes="featured" />

        <!-- ============================================================
             Abschluss-Aufruf auf einem großen Matcha-Blob
             ============================================================ -->
        <section
            aria-labelledby="landing-cta-title"
            class="px-4 pt-6 pb-4 sm:px-6 lg:px-8"
        >
            <Reveal
                class="relative mx-auto w-full max-w-(--content-max) overflow-hidden rounded-[3rem_2.25rem_3.5rem_2.5rem] bg-matcha grain px-6 py-12 sm:px-12 lg:px-16 lg:py-16"
            >
                <span
                    class="pointer-events-none absolute -top-24 -right-16 size-72 animate-morph bg-tint-strong/80 sm:size-96"
                    aria-hidden="true"
                ></span>
                <LeafOrnament
                    variant="sprig"
                    class="pointer-events-none absolute right-[6%] -bottom-6 h-44 animate-float text-moss/70 [--float-rotate:-18deg] sm:h-56"
                />
                <div class="relative max-w-2xl">
                    <h2
                        id="landing-cta-title"
                        class="font-display-wonk text-4xl font-semibold text-balance text-forest sm:text-5xl"
                    >
                        Lust auf etwas
                        <span class="accent-word text-berry">Neues</span>?
                    </h2>
                    <p class="mt-3 text-lg text-forest">
                        Stöbere ohne Anmeldung durch alle Rezepte.
                    </p>
                    <Button
                        :href="route('recipes.index')"
                        size="lg"
                        class="mt-7"
                    >
                        Zu den Rezepten
                        <ArrowRight class="size-5" aria-hidden="true" />
                    </Button>
                </div>
            </Reveal>
        </section>
    </AppLayout>
</template>
