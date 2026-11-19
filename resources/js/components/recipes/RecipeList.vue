<template>
    <!--
        Wird derzeit nirgends verwendet (Gate D: entfernen). Gate C2: ohne
        Legacy-Klassen; Einträge sind echte Schaltflächen (Tastatur).
    -->
    <div class="flex min-w-0 flex-col gap-4">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="font-display text-2xl font-semibold text-ink">
                Rezepte
            </h2>
            <p class="text-sm text-ink-muted">
                {{ recipes.length }} Ergebnisse
            </p>
        </div>

        <ul class="flex flex-col gap-2">
            <li v-for="recipe in recipes" :key="recipe.id">
                <button
                    type="button"
                    class="flex min-h-11 w-full items-start gap-3 rounded-[1.25rem_0.95rem_1.35rem_1.05rem] bg-surface px-4 py-3 text-left shadow-card hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    @click="$emit('select', recipe)"
                >
                    <span
                        class="mt-1.5 size-2.5 shrink-0 rounded-[60%_40%_55%_45%] bg-moss"
                        aria-hidden="true"
                    ></span>
                    <span class="min-w-0">
                        <span
                            class="block font-semibold text-wrap-anywhere text-ink"
                            >{{ recipe.name }}</span
                        >
                        <span class="block text-sm text-ink-muted">{{
                            recipe.meta
                        }}</span>
                    </span>
                </button>
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
interface RecipeListItem {
    id: number | string;
    name: string;
    meta: string;
}

interface Props {
    recipes: RecipeListItem[];
}

defineProps<Props>();

defineEmits<{
    (e: 'select', recipe: RecipeListItem): void;
}>();
</script>
