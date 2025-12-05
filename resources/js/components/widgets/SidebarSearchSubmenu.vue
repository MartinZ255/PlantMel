<template>
    <div class="sidebar-submenu" :class="{ 'is-open': open }">
        <a
            :href="route('recipes.index')"
            class="sidebar-submenu__item" type="button">
            Rezeptsuche
        </a>
        <a
            :href="route('IngredientSearch')"
            class="sidebar-submenu__item" type="button">
            Zutatensuche
        </a>
    </div>
</template>

<script setup lang="ts">
import { route } from 'ziggy-js';
interface Props {
    open: boolean;
}

defineProps<Props>();
</script>
