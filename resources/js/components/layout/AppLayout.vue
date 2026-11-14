<template>
    <Head :title="pageTitle" />

    <div
        class="app-shell"
        :style="drawerTop !== null ? { '--drawer-top': `${drawerTop}px` } : {}"
    >
        <!-- F-42: erstes fokussierbares Element -->
        <a href="#main-content" class="skip-link">Zum Inhalt springen</a>

        <!-- HEADER -->
        <header ref="headerRef" class="app-header">
            <!-- Hamburger: steuert Sidebar immer, egal welche Bildschirmbreite -->
            <button
                ref="menuButtonRef"
                class="app-header__icon app-header__icon--menu"
                type="button"
                title="Navigation"
                aria-label="Navigation"
                aria-controls="app-sidebar"
                :aria-expanded="isSidebarOpen ? 'true' : 'false'"
                @click="toggleSidebar"
            >
                <span aria-hidden="true">☰</span>
            </button>

            <!-- F-21: Gaeste landen auf der oeffentlichen Startseite statt auf /login -->
            <a :href="startHref" class="app-header__logo"> PlantMel </a>
            <!-- Kontext-Titel, bewusst keine Ueberschrift (das h1 steht im Seiteninhalt) -->
            <div class="app-header__title">{{ pageTitle }}</div>
            <div class="app-header__spacer"></div>
            <div ref="userMenuRef" class="app-header__icons user-menu">
                <button
                    ref="userMenuTriggerRef"
                    class="app-header__icon"
                    type="button"
                    title="Profil"
                    aria-label="Profil"
                    aria-haspopup="true"
                    :aria-expanded="isUserMenuOpen ? 'true' : 'false'"
                    aria-controls="user-menu-dropdown"
                    @click="toggleUserMenu"
                >
                    <!-- Minimalistisches Outline-User-Icon, passend zum UI-Stil -->
                    <svg
                        class="user-avatar-icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="8" r="4" />
                        <path
                            d="M5 19c1.5-3 4-4.5 7-4.5s5.5 1.5 7 4.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </button>

                <!-- geschlossen: per CSS unsichtbar/nicht klickbar und per inert nicht fokussierbar (F-04) -->
                <div
                    id="user-menu-dropdown"
                    class="user-menu__dropdown"
                    :class="{ 'is-open': isUserMenuOpen }"
                    :inert="!isUserMenuOpen"
                >
                    <template v-if="currentUser">
                        <div class="user-menu__item user-menu__item--label">
                            Eingeloggt als {{ currentUser.name }}
                        </div>
                        <a
                            :href="route('settings.index')"
                            class="user-menu__item"
                        >
                            Einstellungen
                        </a>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="user-menu__item user-menu__item--danger"
                        >
                            Abmelden
                        </Link>
                    </template>

                    <template v-else>
                        <a :href="route('login')" class="user-menu__item">
                            Anmelden
                        </a>
                        <a
                            v-if="canRegister"
                            :href="route('register')"
                            class="user-menu__item"
                        >
                            Registrieren
                        </a>
                    </template>
                </div>
            </div>
        </header>

        <!-- MAIN -->
        <div
            class="app-main"
            :class="{ 'app-main--sidebar-closed': !isSidebarOpen }"
        >
            <!-- Backdrop (nur <=900 px sichtbar, F-29): Tippen schliesst den Drawer -->
            <div
                v-if="isSidebarOpen"
                class="sidebar-backdrop"
                aria-hidden="true"
                @click="closeSidebar()"
            ></div>

            <!-- SIDEBAR: geschlossen per inert nicht fokussierbar (F-29/F-40) -->
            <aside
                id="app-sidebar"
                class="sidebar"
                :class="{ 'sidebar--closed': !isSidebarOpen }"
                aria-label="Seitenleiste"
                :inert="!isSidebarOpen"
            >
                <div>
                    <div class="sidebar-section__title">Allgemein</div>
                    <nav class="sidebar-nav" aria-label="Allgemein">
                        <a
                            :href="startHref"
                            class="sidebar-collection-pill"
                            :class="activeClass(startHref)"
                            :aria-current="ariaCurrent(startHref)"
                        >
                            Start
                        </a>
                        <!-- F-05: Untermenue liegt im DOM direkt hinter dem Ausloeser (mobil im Drawer) -->
                        <div class="sidebar-search">
                            <button
                                class="sidebar-collection-pill sidebar-link--submenu-toggle"
                                :class="{
                                    'sidebar-link--open': isSearchMenuOpen,
                                    'sidebar-link--active': isSearchActive,
                                }"
                                type="button"
                                aria-controls="sidebar-search-submenu"
                                :aria-expanded="
                                    isSearchMenuOpen ? 'true' : 'false'
                                "
                                @click="toggleSearchMenu"
                            >
                                <span class="sidebar-pill-label">Suche</span>
                                <span
                                    class="sidebar-chevron"
                                    aria-hidden="true"
                                    >{{ searchChevron }}</span
                                >
                            </button>

                            <SidebarSearchSubmenu
                                id="sidebar-search-submenu"
                                :open="isSearchMenuOpen"
                                :current-path="currentPath"
                            />
                        </div>
                    </nav>
                </div>

                <div>
                    <div class="sidebar-section__title">Rezepte</div>
                    <nav class="sidebar-nav" aria-label="Rezepte">
                        <a
                            :href="route('collections.index')"
                            class="sidebar-collection-pill"
                            :class="activeClass(route('collections.index'))"
                            :aria-current="
                                ariaCurrent(route('collections.index'))
                            "
                        >
                            <template v-if="currentUser">Sammlungen</template>
                            <template v-else>
                                <span>Sammlungen</span>
                                <span
                                    class="sidebar-login-hint"
                                    aria-hidden="true"
                                    >· Anmeldung</span
                                >
                                <span class="sr-only">
                                    (Anmeldung erforderlich)</span
                                >
                            </template>
                        </a>
                        <a
                            :href="route('myIngredients')"
                            class="sidebar-collection-pill"
                            :class="activeClass(route('myIngredients'))"
                            :aria-current="ariaCurrent(route('myIngredients'))"
                        >
                            <template v-if="currentUser"
                                >Meine Zutaten</template
                            >
                            <template v-else>
                                <span>Meine Zutaten</span>
                                <span
                                    class="sidebar-login-hint"
                                    aria-hidden="true"
                                    >· Anmeldung</span
                                >
                                <span class="sr-only">
                                    (Anmeldung erforderlich)</span
                                >
                            </template>
                        </a>
                    </nav>
                </div>

                <div>
                    <div class="sidebar-section__title">Sammlungen</div>
                    <nav class="sidebar-collections" aria-label="Sammlungen">
                        <template v-if="userCollections.length">
                            <a
                                v-for="collection in userCollections"
                                :key="collection.id"
                                :href="route('collections.show', collection.id)"
                                class="sidebar-collection-pill"
                                :class="
                                    activeClass(
                                        route(
                                            'collections.show',
                                            collection.id,
                                        ),
                                    )
                                "
                                :aria-current="
                                    ariaCurrent(
                                        route(
                                            'collections.show',
                                            collection.id,
                                        ),
                                    )
                                "
                            >
                                {{ collection.name }}
                            </a>
                        </template>
                        <template v-else>
                            <div
                                class="sidebar-collection-pill sidebar-collection-pill--empty"
                            >
                                {{
                                    currentUser
                                        ? 'Noch keine Sammlungen'
                                        : 'Nach dem Anmelden verfügbar'
                                }}
                            </div>
                        </template>
                    </nav>
                </div>
            </aside>

            <!-- CONTENT-SLOT -->
            <main id="main-content" class="app-content" tabindex="-1">
                <!-- F-17: zentrale Rueckmeldung; nimmt keinen Fokus -->
                <div
                    v-if="flashSuccess"
                    class="flash-message flash-message--success"
                    role="status"
                >
                    <p class="flash-message__text">{{ flashSuccess }}</p>
                    <button
                        type="button"
                        class="flash-message__close"
                        aria-label="Meldung schließen"
                        @click="flashSuccess = null"
                    >
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div
                    v-if="flashError"
                    class="flash-message flash-message--error"
                    role="alert"
                >
                    <p class="flash-message__text">{{ flashError }}</p>
                    <button
                        type="button"
                        class="flash-message__close"
                        aria-label="Meldung schließen"
                        @click="flashError = null"
                    >
                        <span aria-hidden="true">×</span>
                    </button>
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>

<script setup lang="ts">
import SidebarSearchSubmenu from '@/components/widgets/SidebarSearchSubmenu.vue';
import type { AppPageProps, Flash } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    pageTitle?: string;
}

defineProps<Props>();

const page = usePage<AppPageProps>();

/** Gleicher Breakpoint wie im CSS (Drawer-Modus). */
const MOBILE_QUERY = '(max-width: 900px)';
const isMobile = () =>
    typeof window !== 'undefined' && window.matchMedia(MOBILE_QUERY).matches;

const isSearchMenuOpen = ref(false);
// Mobil startet der Drawer geschlossen (kein Aufklappen/Zuklappen beim Laden)
const isSidebarOpen = ref(!isMobile());
const isUserMenuOpen = ref(false);
const userMenuRef = ref<HTMLElement | null>(null);
const userMenuTriggerRef = ref<HTMLButtonElement | null>(null);
const menuButtonRef = ref<HTMLButtonElement | null>(null);
const headerRef = ref<HTMLElement | null>(null);
/** Oberkante des Drawers = sichtbare Unterkante des Headers (F-69). */
const drawerTop = ref<number | null>(null);

const currentUser = computed(() => page.props.auth?.user ?? null);
const userCollections = computed(() => page.props.auth?.collections ?? []);
const canRegister = computed(
    () => (page.props.canRegister as boolean | undefined) ?? true,
);

// F-21: Start/Logo fuer Gaeste auf die oeffentliche Landing-Seite
const startHref = computed(() =>
    currentUser.value ? route('home') : route('landing'),
);

// Aktiver Menuepunkt (aria-current + .sidebar-link--active)
const currentPath = computed(() => normalizePath(page.url));
function normalizePath(url: string): string {
    const path = new URL(url, 'http://localhost').pathname;
    return path.length > 1 ? path.replace(/\/+$/, '') : path;
}
const isCurrent = (href: string) => normalizePath(href) === currentPath.value;
const ariaCurrent = (href: string) => (isCurrent(href) ? 'page' : undefined);
const activeClass = (href: string) => ({
    'sidebar-link--active': isCurrent(href),
});
const isSearchActive = computed(
    () =>
        isCurrent(route('recipes.index')) ||
        isCurrent(route('IngredientSearch')),
);

// Typische Pfeile: geschlossen = ▸ (rechts), offen = ▾ (unten)
const searchChevron = computed(() => (isSearchMenuOpen.value ? '▾' : '▸'));

// --- Flash-Meldungen (F-17) ------------------------------------------------
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
// beim naechsten Seitenwechsel ausblenden. Partielle Reloads lassen es unveraendert.
watch(
    () => page.props.flash,
    (flash) => showFlash(flash),
);

// --- Drawer ------------------------------------------------------------------
function setScrollLock(locked: boolean) {
    // <body> ist der Scroll-Container (html/body height: 100%)
    document.body.classList.toggle('drawer-scroll-lock', locked);
}

function updateDrawerTop() {
    const header = headerRef.value;
    drawerTop.value = header
        ? Math.max(0, Math.round(header.getBoundingClientRect().bottom))
        : null;
}

function openSidebar() {
    if (isMobile()) updateDrawerTop();
    isSidebarOpen.value = true;
}

function closeSidebar(restoreFocus = false) {
    if (!isSidebarOpen.value) return;
    isSidebarOpen.value = false;
    if (restoreFocus) menuButtonRef.value?.focus();
}

const toggleSidebar = () => {
    if (isSidebarOpen.value) closeSidebar();
    else openSidebar();
};

watch(isSidebarOpen, (open) => setScrollLock(open && isMobile()));

// --- User-Menue: Escape schliesst (Fokus zurueck), Klick ausserhalb schliesst (F-04)
const closeUserMenu = (restoreFocus = false) => {
    if (!isUserMenuOpen.value) return;
    isUserMenuOpen.value = false;
    if (restoreFocus) userMenuTriggerRef.value?.focus();
};

const onDocumentKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Escape') return;
    if (isUserMenuOpen.value) {
        closeUserMenu(true);
        return;
    }
    // Escape schliesst den mobilen Drawer (F-29); Desktop-Sidebar bleibt
    if (isSidebarOpen.value && isMobile()) {
        const focusInSidebar = !!(
            document.activeElement &&
            document
                .getElementById('app-sidebar')
                ?.contains(document.activeElement)
        );
        closeSidebar(focusInSidebar);
    }
};

const onDocumentPointerdown = (event: PointerEvent) => {
    if (!isUserMenuOpen.value) return;
    const target = event.target as Node | null;
    if (target && userMenuRef.value?.contains(target)) return;
    closeUserMenu();
};

let mediaQuery: MediaQueryList | null = null;
const onMediaChange = (event: MediaQueryListEvent) => {
    // Wechsel Desktop <-> Mobil: Grundzustand der jeweiligen Darstellung
    isSidebarOpen.value = !event.matches;
    setScrollLock(false);
};

let stopNavigateListener: (() => void) | null = null;

onMounted(() => {
    showFlash(page.props.flash);
    document.addEventListener('keydown', onDocumentKeydown);
    document.addEventListener('pointerdown', onDocumentPointerdown);
    mediaQuery = window.matchMedia(MOBILE_QUERY);
    mediaQuery.addEventListener('change', onMediaChange);
    // Drawer schliesst nach jeder Inertia-Navigation (normale Links laden ohnehin neu)
    stopNavigateListener = router.on('navigate', () => {
        if (isMobile()) closeSidebar();
        isUserMenuOpen.value = false;
    });
});

onBeforeUnmount(() => {
    clearTimeout(successTimer);
    document.removeEventListener('keydown', onDocumentKeydown);
    document.removeEventListener('pointerdown', onDocumentPointerdown);
    mediaQuery?.removeEventListener('change', onMediaChange);
    stopNavigateListener?.();
    setScrollLock(false);
});

const toggleSearchMenu = () => {
    isSearchMenuOpen.value = !isSearchMenuOpen.value;
};

const toggleUserMenu = () => {
    isUserMenuOpen.value = !isUserMenuOpen.value;
};
</script>
