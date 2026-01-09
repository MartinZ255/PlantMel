<template>
    <div class="app-shell">
        <!-- HEADER -->
        <header class="app-header">
            <!-- Hamburger: steuert Sidebar immer, egal welche Bildschirmbreite -->
            <button
                class="app-header__icon app-header__icon--menu"
                type="button"
                title="Navigation"
                @click="toggleSidebar"
            >
                ☰
            </button>

            <a
                :href="route('home')"
                class="app-header__logo"
            >
                PlantMel
            </a>
            <div class="app-header__title">{{ pageTitle }}</div>
            <div class="app-header__spacer"></div>
            <div class="app-header__icons user-menu">
                <button
                    class="app-header__icon"
                    type="button"
                    title="Profil"
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

                <div
                    class="user-menu__dropdown"
                    :class="{ 'is-open': isUserMenuOpen }"
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
                        <a
                            :href="route('login')"
                            class="user-menu__item"
                        >
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

            <!-- Backdrop: nur wenn Sidebar offen -->
            <div
                v-if="isSidebarOpen"
                class="sidebar-backdrop"
                @click="toggleSidebar"
            ></div>

            <!-- SIDEBAR -->
            <aside
                class="sidebar"
                :class="{ 'sidebar--closed': !isSidebarOpen }"
            >
                <div>
                    <div class="sidebar-section__title">Allgemein</div>
                    <nav class="sidebar-nav">
                        <a
                            :href="route('home')"
                            class="sidebar-collection-pill"
                            type="button"
                        >
                            Start
                        </a>
                        <button
                            class="sidebar-collection-pill sidebar-link--submenu-toggle"
                            :class="{ 'sidebar-link--open': isSearchMenuOpen }"
                            type="button"
                            @click="toggleSearchMenu"
                        >
                            <span class="sidebar-pill-label">Suche</span>
                            <span class="sidebar-chevron">{{ searchChevron }}</span>
                        </button>
                    </nav>
                </div>

                <div>
                    <div class="sidebar-section__title">Rezepte</div>
                    <nav class="sidebar-nav">
                        <a
                            :href="route('collections.index')"
                            class="sidebar-collection-pill" type="button"
                        >
                            Sammlungen
                        </a>
                        <button class="sidebar-collection-pill" type="button">
                            Meine Zutaten
                        </button>
                    </nav>
                </div>

                <div>
                    <div class="sidebar-section__title">Sammlungen</div>
                    <div class="sidebar-collections">
                        <template v-if="userCollections.length">
                            <a
                                v-for="collection in userCollections"
                                :key="collection.id"
                                :href="route('collections.show', collection.id)"
                                class="sidebar-collection-pill"
                            >
                                {{ collection.name }}
                            </a>
                        </template>
                        <template v-else>
                            <div class="sidebar-collection-pill sidebar-collection-pill--empty">
                                Noch keine Sammlungen
                            </div>
                        </template>
                    </div>
                </div>
            </aside>

            <!-- Sidebar-Untermenü -->
            <SidebarSearchSubmenu :open="isSearchMenuOpen" />

            <!-- CONTENT-SLOT -->
            <main class="app-content">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import SidebarSearchSubmenu from '@/components/widgets/SidebarSearchSubmenu.vue';

interface Props {
    pageTitle?: string;
}

defineProps<Props>();

const page = usePage();

const isSearchMenuOpen = ref(false);
const isSidebarOpen = ref(true);
const isUserMenuOpen = ref(false);

const currentUser = computed(() => (page.props as any).auth?.user ?? null);
const userCollections = computed(
    () => ((page.props as any).auth?.collections as Array<{ id: number | string; name: string }>) ?? [],
);
const canRegister = computed(
    () => ((page.props as any).canRegister as boolean | undefined) ?? true,
);
// Typische Pfeile: geschlossen = ▸ (rechts), offen = ▾ (unten)
const searchChevron = computed(() => (isSearchMenuOpen.value ? '▾' : '▸'));

onMounted(() => {
    if (window.matchMedia('(max-width: 900px)').matches) {
        isSidebarOpen.value = false;
    }
});

const toggleSearchMenu = () => {
    isSearchMenuOpen.value = !isSearchMenuOpen.value;
};

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

const toggleUserMenu = () => {
    isUserMenuOpen.value = !isUserMenuOpen.value;
};
</script>
