<script setup lang="ts">
import BlobImage from '@/components/ui/BlobImage.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import type { FeaturedRecipe } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

/**
 * Gate C1b: organische Bildkomposition im Einstieg der Startseite.
 *
 * Mit Rezepten: bis zu drei echte Rezeptbilder in Blob-Masken, überlappend
 * auf einem atmenden Matcha-Blob; eine schwebende Karte nennt das zuletzt
 * hinzugefügte Rezept (echte Daten, Link). Die Bilder sind hier dekorativ
 * (`alt=""`), weil die Karte und das Band darunter dieselben Rezepte benennen.
 * Ohne Rezepte: rein grafische Komposition (Blobs, Blatt, Zweig).
 * Feste Seitenverhältnisse: kein Layout-Springen.
 */
const props = defineProps<{ recipes: FeaturedRecipe[] }>();

const main = computed(() => props.recipes[0] ?? null);
const second = computed(() => props.recipes[1] ?? null);
const third = computed(() => props.recipes[2] ?? null);
</script>

<template>
    <div
        class="relative mx-auto aspect-square w-full max-w-[30rem] lg:max-w-[38rem]"
    >
        <!-- Hintergrundebene -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <span
                class="absolute inset-[9%_5%_8%_12%] animate-morph bg-matcha"
            ></span>
            <span
                class="absolute top-[3%] left-[10%] size-[13%] rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-berry shadow-berry"
            ></span>
            <span
                class="absolute right-[0%] bottom-[30%] size-[7%] rounded-full bg-forest"
            ></span>
            <LeafOrnament
                variant="eucalyptus"
                class="absolute -top-[2%] right-[34%] h-[30%] animate-sway text-moss [--float-rotate:18deg]"
            />
        </div>

        <template v-if="main">
            <div
                class="group absolute top-[13%] left-[3%] w-[60%] drop-shadow-[0_30px_40px_rgb(20_61_45/0.35)]"
            >
                <BlobImage
                    :src="main.image"
                    :shape="1"
                    ratio="4/5"
                    eager
                    class="ring-[6px] ring-surface"
                />
            </div>
            <div
                v-if="second"
                class="group absolute top-[4%] right-[3%] w-[38%] animate-float-slow [--float-rotate:4deg]"
            >
                <BlobImage
                    :src="second.image"
                    :shape="3"
                    ratio="1/1"
                    class="shadow-float ring-[5px] ring-surface"
                />
            </div>
            <div
                v-if="third"
                class="group absolute right-[5%] bottom-[7%] w-[42%]"
            >
                <BlobImage
                    :src="third.image"
                    :shape="5"
                    ratio="5/4"
                    class="shadow-float ring-[5px] ring-surface"
                />
            </div>

            <!-- schwebende Karte: zuletzt hinzugefügtes Rezept -->
            <Link
                :href="route('recipes.show', main.id)"
                class="absolute bottom-[1%] left-0 flex max-w-[68%] min-w-0 flex-col gap-0.5 rounded-pebble bg-surface/95 px-4 py-3 shadow-float backdrop-blur-sm transition-[translate,box-shadow] duration-300 hover:-translate-y-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus sm:px-5"
            >
                <span
                    class="text-xs font-semibold tracking-[0.12em] text-berry uppercase"
                    >Zuletzt hinzugefügt</span
                >
                <span
                    class="line-clamp-2 font-display text-lg leading-snug font-semibold text-wrap-anywhere text-ink sm:text-xl"
                    >{{ main.name }}</span
                >
                <span
                    v-if="main.duration_minutes"
                    class="inline-flex items-center gap-1.5 text-sm text-ink-muted"
                >
                    <Clock class="size-4" aria-hidden="true" />
                    {{ main.duration_minutes }} Min.
                </span>
            </Link>
        </template>

        <!-- ohne Rezeptbilder: gestaltete Komposition -->
        <div v-else class="absolute inset-0" aria-hidden="true">
            <span
                class="absolute top-[16%] left-[10%] blob-mask flex aspect-[4/5] w-[56%] items-center justify-center bg-forest shadow-float blob-2"
            >
                <LeafOrnament variant="leaf" class="h-[58%] text-matcha" />
            </span>
            <span
                class="absolute top-[12%] right-[8%] flex aspect-square w-[34%] items-center justify-center rounded-full bg-surface shadow-float ring-[10px] ring-tint-strong"
            >
                <LeafOrnament variant="sprout" class="w-[52%] text-moss" />
            </span>
            <span
                class="absolute right-[10%] bottom-[10%] blob-mask aspect-[5/4] w-[40%] bg-berry-surface shadow-float blob-4"
            ></span>
            <LeafOrnament
                variant="sprig"
                class="absolute right-[16%] bottom-[12%] h-[34%] animate-float text-moss [--float-rotate:-24deg]"
            />
        </div>
    </div>
</template>
