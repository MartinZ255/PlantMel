<template>
    <Head :title="pageTitle" />

    <div class="flex min-h-dvh flex-col">
        <!-- F-42: erstes fokussierbares Element -->
        <a
            href="#main-content"
            class="fixed top-2 left-3 z-(--z-skip-link) -translate-y-[200%] rounded-pebble bg-forest px-4 py-2.5 text-sm font-semibold text-on-forest shadow-float focus:translate-y-0"
        >
            Zum Inhalt springen
        </a>

        <AppHeader
            :current-path="currentPath"
            :menu-open="isMobileMenuOpen"
            @open-menu="openMobileMenu"
        />

        <MobileMenu
            :open="isMobileMenuOpen"
            :current-path="currentPath"
            @close="closeMobileMenu"
        />

        <main
            id="main-content"
            tabindex="-1"
            class="w-full min-w-0 flex-1 focus:outline-none"
        >
            <!-- F-17: zentrale Rückmeldung; nimmt keinen Fokus -->
            <div
                v-if="flashSuccess || flashError"
                class="mx-auto w-full max-w-(--content-max) px-4 pt-4 sm:px-6 lg:px-8"
            >
                <div
                    v-if="flashSuccess"
                    class="flex items-start gap-3 rounded-[1.25rem_1rem_1.4rem_0.9rem] border border-primary/30 bg-success-surface py-2.5 pr-2 pl-4 text-sm text-success shadow-card"
                    role="status"
                >
                    <CircleCheck
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <p
                        class="min-w-0 flex-1 py-0.5 font-medium text-wrap-anywhere"
                    >
                        {{ flashSuccess }}
                    </p>
                    <button
                        type="button"
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-md hover:bg-tint-strong"
                        aria-label="Meldung schließen"
                        @click="flashSuccess = null"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <div
                    v-if="flashError"
                    class="mt-2 flex items-start gap-3 rounded-[1.25rem_1rem_1.4rem_0.9rem] border border-danger/40 bg-danger-surface py-2.5 pr-2 pl-4 text-sm text-danger shadow-card first:mt-0"
                    role="alert"
                >
                    <CircleAlert
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <p
                        class="min-w-0 flex-1 py-0.5 font-medium text-wrap-anywhere"
                    >
                        {{ flashError }}
                    </p>
                    <button
                        type="button"
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-md hover:bg-white/60"
                        aria-label="Meldung schließen"
                        @click="flashError = null"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <!-- Musterseiten gestalten die volle Breite selbst (bleed);
                 alle übrigen Seiten bekommen den bisherigen Inhaltsrahmen -->
            <slot v-if="bleed" />
            <div
                v-else
                class="mx-auto flex w-full max-w-(--content-max) flex-col gap-5 px-4 py-6 sm:px-6 lg:px-8"
            >
                <slot />
            </div>
        </main>

        <AppFooter />
    </div>
</template>

<script setup lang="ts">
import AppFooter from '@/components/layout/AppFooter.vue';
import AppHeader from '@/components/layout/AppHeader.vue';
import MobileMenu from '@/components/layout/MobileMenu.vue';
import { normalizePath } from '@/composables/useAppNavigation';
import type { AppPageProps, Flash } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Props {
    pageTitle?: string;
    /** Inhalt ohne Rahmen (volle Breite), für neu gestaltete Seiten */
    bleed?: boolean;
}

withDefaults(defineProps<Props>(), { pageTitle: undefined, bleed: false });

const page = usePage<AppPageProps>();

const currentPath = computed(() => normalizePath(page.url));

// --- Mobilmenü (< md) ---------------------------------------------------------
/** Tailwind `md` = 48rem; darüber gibt es kein Mobilmenü */
const DESKTOP_QUERY = '(min-width: 48rem)';
const isMobileMenuOpen = ref(false);

const openMobileMenu = () => {
    isMobileMenuOpen.value = true;
};
const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

let mediaQuery: MediaQueryList | null = null;
const onMediaChange = (event: MediaQueryListEvent) => {
    if (event.matches) closeMobileMenu();
};

// --- Flash-Meldungen (F-17) ----------------------------------------------------
const SUCCESS_TIMEOUT_MS = 6000;
const flashSuccess = ref<string | null>(null);
const flashError = ref<string | null>(null);
let successTimer: ReturnType<typeof setTimeout> | undefined;

function showFlash(flash: Flash | undefined) {
    clearTimeout(successTimer);
    flashSuccess.value = flash?.success ?? null;
    flashError.value = flash?.error ?? null;
    if (flashSuccess.value) {
        successTimer = setTimeout(() => {
            flashSuccess.value = null;
        }, SUCCESS_TIMEOUT_MS);
    }
}

// Jede Antwort liefert ein neues flash-Objekt: neue Meldung anzeigen bzw. alte
// beim nächsten Seitenwechsel ausblenden. Partielle Reloads lassen es unverändert.
watch(
    () => page.props.flash,
    (flash) => showFlash(flash),
);

let stopNavigateListener: (() => void) | null = null;

onMounted(() => {
    showFlash(page.props.flash);
    mediaQuery = window.matchMedia(DESKTOP_QUERY);
    mediaQuery.addEventListener('change', onMediaChange);
    // Menü schließt nach jeder Inertia-Navigation
    stopNavigateListener = router.on('navigate', () => closeMobileMenu());
});

onBeforeUnmount(() => {
    clearTimeout(successTimer);
    mediaQuery?.removeEventListener('change', onMediaChange);
    stopNavigateListener?.();
});
</script>
