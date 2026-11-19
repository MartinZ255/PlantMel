<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import type { AppPageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Carrot, FolderHeart, Leaf, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const page = usePage<AppPageProps<{ canRegister?: boolean }>>();
const user = computed(() => page.props.auth?.user ?? null);
const canRegister = computed(() => page.props.canRegister ?? false);

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
        <!-- Einstieg -->
        <section class="relative overflow-hidden bg-surface">
            <div
                class="mx-auto grid w-full max-w-(--content-max) items-center gap-10 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-24"
            >
                <div class="relative z-(--z-raised) min-w-0">
                    <p
                        class="mb-4 inline-flex items-center gap-2 rounded-full bg-tint px-3 py-1 text-sm font-semibold text-primary"
                    >
                        <Leaf class="size-4" aria-hidden="true" />
                        Pflanzenbetonte Küche
                    </p>
                    <h1
                        class="text-4xl font-semibold tracking-tight text-balance [overflow-wrap:anywhere] text-ink sm:text-5xl lg:text-[3.5rem] lg:leading-[1.05]"
                    >
                        Lieblingsrezepte an einem Ort
                    </h1>
                    <p class="mt-5 max-w-xl text-lg text-ink-muted sm:text-xl">
                        Speichere, sortiere und entdecke pflanzenbetonte Rezepte
                        für deinen Alltag – mit PlantMel behältst du den
                        Überblick.
                    </p>

                    <div
                        class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap"
                    >
                        <!-- F-21: öffentlicher Einstieg ohne Anmeldung -->
                        <Button :href="route('recipes.index')" size="lg">
                            Rezepte entdecken
                            <ArrowRight class="size-5" aria-hidden="true" />
                        </Button>
                        <template v-if="user">
                            <Button
                                :href="route('home')"
                                variant="secondary"
                                size="lg"
                            >
                                Zu meinem Dashboard
                            </Button>
                        </template>
                        <template v-else>
                            <Button
                                :href="route('login')"
                                variant="secondary"
                                size="lg"
                            >
                                Anmelden
                            </Button>
                        </template>
                    </div>
                    <p v-if="!user && canRegister" class="mt-4 text-ink-muted">
                        Neu hier?
                        <Link
                            :href="route('register')"
                            class="font-semibold text-primary underline underline-offset-4 hover:text-primary-hover"
                        >
                            Konto erstellen
                        </Link>
                    </p>
                </div>

                <!-- Grafik aus Formen und Farbflächen (keine Fotos, keine Zahlen) -->
                <div
                    class="relative mx-auto aspect-square w-full max-w-md lg:max-w-none"
                    aria-hidden="true"
                >
                    <div
                        class="absolute inset-[8%] rounded-[2.5rem] bg-tint"
                    ></div>
                    <div
                        class="absolute top-[2%] right-[6%] size-[34%] rounded-full bg-accent"
                    ></div>
                    <div
                        class="absolute bottom-[6%] left-[2%] size-[26%] rounded-full bg-primary"
                    ></div>
                    <!-- stilisierte Rezeptkarte -->
                    <div
                        class="absolute top-[20%] left-[16%] w-[62%] -rotate-3 overflow-hidden rounded-2xl border border-line bg-surface shadow-overlay"
                    >
                        <div
                            class="relative aspect-[4/3] bg-gradient-to-br from-tint-strong to-tint"
                        >
                            <Leaf
                                class="absolute top-1/2 left-1/2 size-[30%] -translate-x-1/2 -translate-y-1/2 text-primary"
                            />
                        </div>
                        <div class="flex flex-col gap-2 p-[7%]">
                            <span
                                class="h-2.5 w-3/4 rounded-full bg-ink/80"
                            ></span>
                            <span class="h-2 w-1/2 rounded-full bg-line"></span>
                            <span class="mt-1 flex gap-1.5">
                                <span
                                    class="h-4 w-12 rounded-full bg-tint"
                                ></span>
                                <span
                                    class="h-4 w-10 rounded-full bg-accent-surface"
                                ></span>
                            </span>
                        </div>
                    </div>
                    <!-- zweite, kleinere Karte -->
                    <div
                        class="absolute right-[4%] bottom-[14%] w-[40%] rotate-6 rounded-2xl border border-line bg-surface p-[4%] shadow-raised"
                    >
                        <span
                            class="mb-2 block h-2 w-2/3 rounded-full bg-ink/70"
                        ></span>
                        <span
                            class="mb-1.5 block h-1.5 w-full rounded-full bg-line"
                        ></span>
                        <span
                            class="mb-1.5 block h-1.5 w-5/6 rounded-full bg-line"
                        ></span>
                        <span
                            class="block h-1.5 w-1/2 rounded-full bg-line"
                        ></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Vorteile -->
        <section
            aria-labelledby="landing-benefits-title"
            class="mx-auto w-full max-w-(--content-max) px-4 py-14 sm:px-6 lg:px-8 lg:py-20"
        >
            <h2
                id="landing-benefits-title"
                class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl"
            >
                Was du mit PlantMel machen kannst
            </h2>
            <ul class="mt-8 grid gap-5 md:grid-cols-3">
                <li
                    v-for="b in benefits"
                    :key="b.title"
                    class="rounded-2xl border border-line bg-surface p-6 shadow-card"
                >
                    <span
                        class="inline-flex size-12 items-center justify-center rounded-xl bg-tint text-primary"
                        aria-hidden="true"
                    >
                        <component :is="b.icon" class="size-6" />
                    </span>
                    <h3 class="mt-4 text-lg font-semibold text-ink">
                        {{ b.title }}
                    </h3>
                    <p class="mt-1.5 text-ink-muted">{{ b.text }}</p>
                </li>
            </ul>
        </section>

        <!-- Abschluss -->
        <section class="px-4 pb-16 sm:px-6 lg:px-8">
            <div
                class="mx-auto flex w-full max-w-(--content-max) flex-col items-start gap-5 rounded-3xl bg-primary px-6 py-10 text-on-primary sm:px-10 md:flex-row md:items-center md:justify-between"
            >
                <div class="min-w-0">
                    <h2 class="text-2xl font-semibold tracking-tight">
                        Lust auf etwas Neues?
                    </h2>
                    <p class="mt-1 text-on-primary">
                        Stöbere ohne Anmeldung durch alle Rezepte.
                    </p>
                </div>
                <Link
                    :href="route('recipes.index')"
                    class="inline-flex min-h-12 shrink-0 items-center gap-2 rounded-full bg-surface px-6 text-lg font-semibold text-primary hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                >
                    Zu den Rezepten
                    <ArrowRight class="size-5" aria-hidden="true" />
                </Link>
            </div>
        </section>
    </AppLayout>
</template>
