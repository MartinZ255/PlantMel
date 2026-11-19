<template>
    <!-- Gate C2: Zutat als kleines „Samenkorn“ – organische Form, Blatt als Symbol -->
    <div
        class="group flex h-full min-h-14 min-w-0 items-center gap-3 rounded-[1.25rem_0.95rem_1.35rem_1.05rem] bg-surface py-2.5 pr-4 pl-2.5 shadow-card transition-[border-radius] duration-500 hover:rounded-[0.95rem_1.35rem_1.05rem_1.25rem]"
    >
        <span
            class="inline-flex size-9 shrink-0 items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] transition-[border-radius] duration-700 ease-organic group-hover:rounded-[46%_54%_40%_60%/56%_44%_58%_42%]"
            :class="tone"
            aria-hidden="true"
        >
            <LeafOrnament variant="sprout" class="size-5" />
        </span>
        <span
            class="min-w-0 font-medium text-wrap-anywhere text-ink"
            data-ingredient-name
        >
            {{ ingredient.name }}
        </span>
    </div>
</template>

<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { computed } from 'vue';

interface Ingredient {
    id: number;
    name: string;
}

const props = defineProps<{
    ingredient: Ingredient;
}>();

// Symbolfarbe wechselt mit der ID, damit lange Listen lebendig wirken
const tone = computed(
    () =>
        [
            'bg-matcha text-forest',
            'bg-tint-strong text-primary',
            'bg-berry-surface text-berry',
            'bg-tint text-moss',
        ][Math.abs(props.ingredient.id) % 4],
);
</script>
