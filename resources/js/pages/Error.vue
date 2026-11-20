<template>
    <!-- Seitentitel setzt AppLayout zentral -->
    <AppLayout :page-title="heading" bleed>
        <section
            class="relative overflow-hidden"
            aria-labelledby="error-heading"
        >
            <BlobBackdrop tone="light" />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute top-8 -left-6 h-28 animate-float-slow text-tint-strong [--float-rotate:-26deg] sm:left-[3%] lg:h-40"
            />

            <div
                class="relative mx-auto grid w-full max-w-(--content-max) items-center gap-10 px-4 pt-10 pb-16 sm:px-6 sm:pt-14 lg:grid-cols-[1.1fr_0.9fr] lg:gap-14 lg:px-8 lg:pt-20 lg:pb-24"
            >
                <div class="relative min-w-0">
                    <p
                        class="mb-4 inline-flex items-center gap-2 rounded-seed bg-surface/80 px-3.5 py-1.5 text-sm font-semibold text-berry shadow-card"
                    >
                        <LeafOrnament
                            variant="sprout"
                            class="size-4 text-moss"
                        />
                        Fehler {{ status }}
                    </p>
                    <h1
                        id="error-heading"
                        class="font-display-wonk text-[clamp(2.4rem,10vw,5rem)] leading-[1] font-semibold text-balance text-wrap-anywhere text-ink"
                    >
                        {{ heading }}
                    </h1>
                    <p
                        class="mt-6 max-w-xl text-lg leading-relaxed text-ink sm:text-xl"
                    >
                        {{ message }}
                    </p>
                    <p
                        v-if="hint"
                        class="mt-2 max-w-xl leading-relaxed text-ink-muted"
                    >
                        {{ hint }}
                    </p>

                    <div
                        class="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
                    >
                        <!-- Volle Seitenladung: der Fehlerzustand soll nicht nachwirken -->
                        <Button :href="startHref" external size="lg">
                            <House class="size-5" aria-hidden="true" />
                            Zur Startseite
                        </Button>
                        <Button
                            :href="route('recipes.index')"
                            external
                            variant="secondary"
                            size="lg"
                        >
                            Zu den Rezepten
                        </Button>
                        <Button
                            v-if="!currentUser"
                            :href="route('login')"
                            external
                            variant="ghost"
                            size="lg"
                            class="underline decoration-berry decoration-2 underline-offset-4"
                        >
                            Anmelden
                        </Button>
                    </div>
                </div>

                <!-- Organische Illustration: Statuszahl in Fraunces auf Matcha-Blob mit Blättern -->
                <div
                    class="pointer-events-none relative mx-auto aspect-square w-full max-w-[19rem] sm:max-w-[24rem] lg:max-w-[28rem]"
                    aria-hidden="true"
                >
                    <span
                        class="absolute inset-[8%] animate-morph bg-matcha grain shadow-float"
                    ></span>
                    <span
                        class="absolute top-[4%] right-[6%] size-[24%] rounded-[46%_54%_40%_60%/56%_44%_58%_42%] bg-forest shadow-raised"
                    ></span>
                    <span
                        class="absolute bottom-[8%] left-[4%] size-[12%] rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-berry shadow-berry"
                    ></span>
                    <LeafOrnament
                        variant="eucalyptus"
                        class="absolute right-[8%] -bottom-[4%] h-[46%] animate-sway rotate-[24deg] text-moss [--float-rotate:24deg]"
                    />
                    <LeafOrnament
                        variant="leaf"
                        class="absolute top-[12%] left-[2%] h-[30%] rotate-[-38deg] animate-float text-forest-soft [--float-rotate:-38deg]"
                    />
                    <component
                        :is="icon"
                        class="absolute top-[10.5%] right-[13%] size-[10%] text-matcha"
                        stroke-width="2.2"
                    />
                    <span
                        class="absolute inset-0 flex items-center justify-center font-display-wonk text-[clamp(4.5rem,24vw,9rem)] leading-none font-semibold text-forest"
                        >{{ status }}</span
                    >
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
// F-68 (Slice 3): Fehlerseite fuer Inertia-Antworten mit Status 403, 404,
// 429, 500 und 503 (gerendert in bootstrap/app.php, withExceptions).
// Gate C2: organische Illustration im Stil „Natur & organisch“.
import AppLayout from '@/components/layout/AppLayout.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import Button from '@/components/ui/Button.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import {
    CircleAlert,
    Compass,
    Hourglass,
    House,
    Lock,
    ServerCrash,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    status: number;
    message: string;
}>();

const page = usePage<AppPageProps>();
const currentUser = computed(() => page.props.auth?.user ?? null);
// Angemeldete zurueck auf ihre Startseite, Gaeste auf die oeffentliche Landing-Seite
const startHref = computed(() =>
    currentUser.value ? route('home') : route('landing'),
);

const headings: Record<number, string> = {
    403: 'Kein Zugriff',
    404: 'Seite nicht gefunden',
    419: 'Sitzung abgelaufen',
    429: 'Zu viele Anfragen',
    500: 'Serverfehler',
    503: 'Wartungsarbeiten',
};

// Zusatzsatz unter der Servermeldung (ergänzt sie, ersetzt sie nicht)
const hints: Record<number, string> = {
    403: 'Diese Seite ist nur mit passender Berechtigung erreichbar.',
    404: 'Vielleicht hat sich die Adresse geändert – auf der Startseite und bei den Rezepten findest du sicher weiter.',
    419: 'Lade die Seite neu und versuche es noch einmal.',
    429: 'Warte einen Moment, dann geht es weiter.',
    500: 'Bitte versuche es in ein paar Minuten noch einmal.',
    503: 'Wir sind gleich wieder für dich da.',
};

const icons = {
    403: Lock,
    404: Compass,
    419: Hourglass,
    429: Hourglass,
    500: ServerCrash,
    503: Wrench,
} as const;

const heading = computed(() => headings[props.status] ?? 'Fehler');
const hint = computed(() => hints[props.status] ?? null);
const icon = computed(
    () => icons[props.status as keyof typeof icons] ?? CircleAlert,
);
</script>
