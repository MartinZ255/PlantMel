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
            <div class="app-header__icons">
                <button class="app-header__icon" type="button" title="Profil">👤</button>
            </div>
        </header>

        <!-- MAIN -->
        <div
            class="app-main"
            :class="{ 'app-main--sidebar-closed': !isSidebarOpen }"
        >
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
                            class="sidebar-link sidebar-link--active"
                            type="button"
                        >
                            Start
                        </a>
                        <button
                            class="sidebar-link sidebar-link--active sidebar-link--submenu-toggle"
                            type="button"
                            @click="toggleSearchMenu"
                        >
                            <span>Suche</span>
                            <span>▸</span>
                        </button>
                    </nav>
                </div>

                <div>
                    <div class="sidebar-section__title">Rezepte</div>
                    <nav class="sidebar-nav">
                        <a
                            :href="route('collections.index')"
                            class="sidebar-link sidebar-link--active" type="button"
                        >
                            Sammlungen
                        </a>
                        <button class="sidebar-link sidebar-link--active" type="button">Meine Zutaten</button>
                    </nav>
                </div>

                <div>
                    <div class="sidebar-section__title">Sammlungen</div>
                    <div class="sidebar-collections">
                        <div class="sidebar-collection-pill">unter 30 Minuten</div>
                        <div class="sidebar-collection-pill">Pasta</div>
                        <div class="sidebar-collection-pill">Meal Prep</div>
                    </div>
                </div>

                <button class="sidebar-footer" type="button">
                    Abmelden
                </button>
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
import { ref } from 'vue';
import { route } from 'ziggy-js';
import SidebarSearchSubmenu from '@/components/widgets/SidebarSearchSubmenu.vue';

interface Props {
    pageTitle?: string;
}

defineProps<Props>();

const isSearchMenuOpen = ref(false);
const isSidebarOpen = ref(true);

const toggleSearchMenu = () => {
    isSearchMenuOpen.value = !isSearchMenuOpen.value;
};

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};
</script>
