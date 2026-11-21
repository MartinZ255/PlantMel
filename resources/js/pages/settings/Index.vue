<script setup lang="ts">
import SettingsShell from '@/components/settings/SettingsShell.vue';
import Badge from '@/components/ui/Badge.vue';
import { getInitials } from '@/composables/useInitials';
import type { AppPageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, KeyRound, Sparkles, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth?.user ?? null);
const initials = computed(() => getInitials(user.value?.name ?? ''));

const cards = [
    {
        route: 'profile.edit',
        title: 'Profil',
        text: 'Name und E-Mail-Adresse ändern',
        icon: UserRound,
        blob: 'rounded-[58%_42%_52%_48%/46%_54%_46%_54%] group-hover:rounded-[46%_54%_40%_60%/56%_44%_58%_42%]',
    },
    {
        route: 'user-password.edit',
        title: 'Passwort',
        text: 'Passwort ändern oder zurücksetzen',
        icon: KeyRound,
        blob: 'rounded-[42%_58%_61%_39%/52%_38%_62%_48%] group-hover:rounded-[56%_44%_48%_52%/40%_56%_44%_60%]',
    },
    // E18
    {
        route: 'settings.ai.edit',
        title: 'KI-Import',
        text: 'Eigenen Gemini-Schlüssel für den TikTok-Import hinterlegen',
        icon: Sparkles,
        blob: 'rounded-[52%_48%_38%_62%/44%_58%_42%_56%] group-hover:rounded-[40%_60%_56%_44%/58%_42%_60%_40%]',
    },
] as const;
</script>

<template>
    <SettingsShell
        page-title="Einstellungen"
        title="Einstellungen"
        description="Verwalte dein Konto und deine Präferenzen"
        current="index"
    >
        <template #title>
            <span class="relative inline-block">
                Einstellungen
                <!-- handgezeichneter Strich wie auf der Startseite -->
                <svg
                    viewBox="0 0 220 24"
                    preserveAspectRatio="none"
                    class="absolute -bottom-2 left-[38%] h-[0.2em] w-[62%] text-berry"
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
        </template>

        <!-- Kontokarte auf Waldgrün -->
        <div
            v-if="user"
            class="relative overflow-hidden rounded-[2.25rem_1.75rem_2.5rem_2rem] bg-forest grain-light px-5 py-6 on-forest shadow-float sm:px-8 sm:py-8"
        >
            <span
                class="pointer-events-none absolute -top-16 -right-12 size-56 rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-forest-soft/80"
                aria-hidden="true"
            ></span>
            <div
                class="relative flex min-w-0 flex-wrap items-center gap-4 sm:gap-6"
            >
                <span
                    class="inline-flex size-16 shrink-0 items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-matcha font-display-wonk text-2xl font-semibold text-forest sm:size-20 sm:text-3xl"
                    aria-hidden="true"
                    >{{ initials }}</span
                >
                <div class="min-w-0 flex-1">
                    <p
                        class="text-sm font-semibold tracking-[0.14em] text-on-forest-muted uppercase"
                    >
                        Angemeldet als
                    </p>
                    <p
                        class="font-display text-2xl font-semibold text-wrap-anywhere text-on-forest sm:text-3xl"
                    >
                        {{ user.name }}
                    </p>
                    <p class="mt-0.5 text-wrap-anywhere text-on-forest-muted">
                        {{ user.email }}
                    </p>
                </div>
                <Badge v-if="user.is_host" tone="matcha">Host</Badge>
            </div>
        </div>

        <ul class="grid gap-4 sm:grid-cols-2" aria-label="Bereiche">
            <li v-for="c in cards" :key="c.route" class="min-w-0">
                <Link
                    :href="route(c.route)"
                    class="group relative flex h-full min-w-0 items-center gap-4 overflow-hidden rounded-[1.75rem_1.5rem_2rem_1.25rem] bg-surface p-5 shadow-card transition-[box-shadow,translate] duration-300 hover:-translate-y-1 hover:shadow-float focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus sm:p-6"
                >
                    <span
                        class="inline-flex size-14 shrink-0 items-center justify-center bg-tint-strong text-primary transition-[border-radius] duration-700 ease-organic"
                        :class="c.blob"
                        aria-hidden="true"
                    >
                        <component :is="c.icon" class="size-6" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block font-display text-2xl font-semibold text-ink"
                            >{{ c.title }}</span
                        >
                        <span class="mt-0.5 block text-ink-muted">{{
                            c.text
                        }}</span>
                    </span>
                    <ArrowRight
                        class="size-5 shrink-0 text-primary transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    />
                </Link>
            </li>
        </ul>
    </SettingsShell>
</template>
