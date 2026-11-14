<template>
    <!-- F-05: geschlossen per CSS ausgeblendet und per inert nicht fokussierbar -->
    <div class="sidebar-submenu" :class="{ 'is-open': open }" :inert="!open">
        <a
            v-for="item in items"
            :key="item.label"
            :href="item.href"
            class="sidebar-submenu__item"
            :class="{ 'sidebar-submenu__item--active': isCurrent(item.href) }"
            :aria-current="isCurrent(item.href) ? 'page' : undefined"
        >
            {{ item.label }}
        </a>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    open: boolean;
    /** normalisierter Pfad der aktuellen Seite (ohne Query, ohne Schraegstrich am Ende) */
    currentPath?: string;
}

const props = defineProps<Props>();

const items = computed(() => [
    { label: 'Rezeptsuche', href: route('recipes.index') },
    { label: 'Zutatensuche', href: route('IngredientSearch') },
]);

const isCurrent = (href: string) => {
    if (!props.currentPath) return false;
    const path = new URL(href, 'http://localhost').pathname;
    return (
        (path.length > 1 ? path.replace(/\/+$/, '') : path) ===
        props.currentPath
    );
};
</script>
