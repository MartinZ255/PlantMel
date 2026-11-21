<script setup lang="ts">
import BrandLogo from '@/components/brand/BrandLogo.vue';
import AppFooter from '@/components/layout/AppFooter.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import type { AppPageProps } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

/**
 * Gate C2: eigenes Layout der Anmelde-Seiten (Login, Registrieren, Passwort
 * vergessen/zurücksetzen/bestätigen, E-Mail bestätigen).
 *
 * Geteilte Fläche: links (mobil oben) ein waldgrünes Blatt mit Wortmarke,
 * Fraunces-Zitat und einer Blob-Komposition aus Blättern; rechts (mobil
 * darunter) das Formular auf Naturgrund. Landmarken: `header` (Marke und
 * Zitat), `main` (Überschrift, Meldungen, Formular), `footer` (Rechtliches).
 *
 * - `eyebrow`: Dachzeile über dem h1 (z. B. „Anmelden“)
 * - `title`: genau ein h1; Slot `#title` erlaubt ein kursives Akzentwort
 * - `status`: Erfolgsmeldung (role="status"), steht über dem Formular
 * - Fehlermeldung aus `flash.error` (z. B. „Sitzung abgelaufen“ nach 419)
 *   erscheint automatisch als role="alert"
 * - Slot `#after`: Nebenwege unter dem Formular (Links)
 */
const props = withDefaults(
    defineProps<{
        pageTitle: string;
        eyebrow: string;
        title: string;
        description?: string;
        status?: string | null;
        quote?: string;
        quoteAccent?: string;
    }>(),
    {
        description: undefined,
        status: null,
        quote: 'Gute Küche beginnt mit einer Handvoll',
        quoteAccent: 'frischer Kräuter.',
    },
);

const page = usePage<AppPageProps>();
const flashError = computed(() => page.props.flash?.error ?? null);
const statusText = computed(() => props.status || null);
</script>

<template>
    <Head :title="pageTitle" />

    <div
        class="min-h-dvh bg-canvas lg:grid lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)]"
    >
        <!-- ============================================================
             Waldgrünes Blatt: Marke, Zitat, Blob-Komposition
             ============================================================ -->
        <header
            class="relative isolate overflow-hidden rounded-b-[2.75rem_2.25rem] bg-forest grain-light on-forest lg:sticky lg:top-0 lg:flex lg:h-dvh lg:flex-col lg:rounded-none lg:rounded-r-[3.5rem_3rem]"
        >
            <BlobBackdrop tone="forest" />
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute -right-8 -bottom-10 h-44 rotate-[-32deg] text-forest-soft sm:h-56 lg:right-auto lg:-bottom-6 lg:-left-10 lg:h-72 lg:rotate-[28deg]"
            />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute top-4 right-[18%] hidden h-24 animate-sway text-forest-soft [--float-rotate:18deg] sm:block lg:top-10 lg:right-10 lg:h-36"
            />

            <div
                class="relative flex flex-col gap-6 px-5 pt-5 pb-10 sm:px-8 sm:pt-6 lg:h-full lg:px-12 lg:pt-10 lg:pb-12 xl:px-16"
            >
                <BrandLogo :href="route('landing')" tone="light" />

                <!-- Komposition nur ab lg: großer Matcha-Blob mit Blatt, Beeren-Korn, Moos-Kiesel -->
                <div
                    class="pointer-events-none relative mx-auto hidden aspect-square w-full max-w-[22rem] lg:my-auto lg:block xl:max-w-[26rem]"
                    aria-hidden="true"
                >
                    <span
                        class="absolute inset-[6%] animate-morph bg-matcha grain shadow-float"
                    ></span>
                    <span
                        class="absolute top-[2%] right-[4%] size-[26%] rounded-[46%_54%_40%_60%/56%_44%_58%_42%] bg-forest-soft shadow-raised"
                    ></span>
                    <span
                        class="absolute bottom-[10%] left-[2%] size-[13%] rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-berry shadow-berry"
                    ></span>
                    <span
                        class="absolute right-[14%] bottom-[2%] size-[18%] rounded-[63%_37%_45%_55%/55%_61%_39%_45%] bg-moss/70"
                    ></span>
                    <LeafOrnament
                        variant="leaf"
                        class="absolute top-[16%] left-[22%] h-[64%] rotate-[-18deg] animate-float-slow text-forest [--float-rotate:-18deg]"
                    />
                    <LeafOrnament
                        variant="sprout"
                        class="absolute top-[7%] right-[10%] size-[16%] text-matcha"
                    />
                </div>

                <figure class="relative max-w-md lg:max-w-lg">
                    <blockquote>
                        <p
                            class="font-display-wonk text-[1.65rem] leading-tight font-medium text-balance text-on-forest sm:text-3xl lg:text-4xl xl:text-[2.6rem] xl:leading-[1.1]"
                        >
                            <span aria-hidden="true" class="text-matcha">„</span
                            >{{ quote }}
                            <span class="accent-word text-matcha">{{
                                quoteAccent
                            }}</span><span
                                aria-hidden="true"
                                class="text-matcha"
                                >“</span
                            >
                        </p>
                    </blockquote>
                    <figcaption
                        class="mt-3 inline-flex items-center gap-2 text-sm font-semibold tracking-[0.14em] text-on-forest-muted uppercase"
                    >
                        <LeafOrnament
                            variant="sprout"
                            class="size-4 text-matcha"
                        />
                        Aus der PlantMel-Küche
                    </figcaption>
                </figure>
            </div>
        </header>

        <!-- ============================================================
             Formular auf Naturgrund
             ============================================================ -->
        <div
            class="relative flex min-w-0 flex-col overflow-x-clip lg:min-h-dvh"
        >
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute top-[12%] -right-10 hidden h-40 rotate-[28deg] text-tint-strong lg:block"
            />
            <main
                id="main-content"
                tabindex="-1"
                class="relative flex flex-1 items-start justify-center px-4 pt-8 pb-6 focus:outline-none sm:px-8 sm:pt-12 lg:items-center lg:px-12 lg:py-14"
            >
                <div class="w-full max-w-[28rem] min-w-0">
                    <p
                        class="mb-3 inline-flex items-center gap-2 rounded-seed bg-surface/80 px-3.5 py-1.5 text-sm font-semibold text-primary shadow-card"
                    >
                        <LeafOrnament
                            variant="sprout"
                            class="size-4 text-moss"
                        />
                        {{ eyebrow }}
                    </p>
                    <h1
                        class="font-display-wonk text-[clamp(2.1rem,9vw,3.25rem)] leading-[1.02] font-semibold text-balance text-wrap-anywhere text-ink"
                    >
                        <slot name="title">{{ title }}</slot>
                    </h1>
                    <p
                        v-if="description"
                        class="mt-3 text-lg leading-relaxed text-ink-muted"
                    >
                        {{ description }}
                    </p>

                    <div
                        v-if="flashError || statusText"
                        class="mt-6 flex flex-col gap-2"
                    >
                        <p
                            v-if="flashError"
                            class="flex items-start gap-3 rounded-[1.25rem_1rem_1.4rem_0.9rem] border border-danger/40 bg-danger-surface px-4 py-3 text-sm font-medium text-wrap-anywhere text-danger shadow-card"
                            role="alert"
                        >
                            <CircleAlert
                                class="mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1">{{ flashError }}</span>
                        </p>
                        <p
                            v-if="statusText"
                            class="flex items-start gap-3 rounded-[1.25rem_1rem_1.4rem_0.9rem] border border-primary/30 bg-success-surface px-4 py-3 text-sm font-medium text-wrap-anywhere text-success shadow-card"
                            role="status"
                        >
                            <CircleCheck
                                class="mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1">{{ statusText }}</span>
                        </p>
                    </div>

                    <!-- Papierblatt mit dem Formular -->
                    <div
                        class="relative mt-6 rounded-[2rem_1.6rem_2.25rem_1.5rem] bg-surface p-5 shadow-float sm:p-7"
                    >
                        <span
                            class="pointer-events-none absolute -top-3 -left-3 size-8 rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-matcha shadow-card"
                            aria-hidden="true"
                        ></span>
                        <slot />
                    </div>

                    <div
                        v-if="$slots.after"
                        class="mt-5 flex flex-col items-center gap-1 text-center"
                    >
                        <slot name="after" />
                    </div>
                </div>
            </main>

            <AppFooter variant="auth" class="pb-6" />
        </div>
    </div>
</template>
