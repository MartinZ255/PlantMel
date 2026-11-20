<script setup lang="ts">
import IngredientCard from '@/components/recipes/IngredientCard.vue';
import { computed } from 'vue';

/**
 * Gate C2: Zutaten alphabetisch gruppiert – je Anfangsbuchstabe ein großer
 * Fraunces-Buchstabe in einer Blob-Form, daneben die Zutaten als „Samen“.
 * Reihenfolge der Zutaten bleibt wie geliefert (Server sortiert nach Name);
 * die Gruppen werden nach deutscher Sortierung geordnet (Ä bei A usw.).
 *
 * Jede Gruppe ist eine eigene Liste mit zugänglichem Namen
 * („Zutaten mit A“). Die Buchstaben-Überschriften sind `h3`, die
 * Überschriftenebene darüber liefert die Seite.
 */
interface Ingredient {
    id: number;
    name: string;
}

const props = defineProps<{
    ingredients: Ingredient[];
}>();

const collator = new Intl.Collator('de', { sensitivity: 'base' });

const letterOf = (name: string) => {
    const first = Array.from(name.trim())[0] ?? '#';
    const base = first.normalize('NFD').replace(/\p{M}/gu, '').toUpperCase();
    return /^[A-Z]$/.test(base) ? base : '#';
};

const groups = computed(() => {
    const map = new Map<string, Ingredient[]>();
    for (const ing of props.ingredients) {
        const letter = letterOf(ing.name);
        const list = map.get(letter);
        if (list) list.push(ing);
        else map.set(letter, [ing]);
    }
    return Array.from(map, ([letter, items]) => ({ letter, items })).sort(
        (a, b) =>
            a.letter === '#'
                ? 1
                : b.letter === '#'
                  ? -1
                  : collator.compare(a.letter, b.letter),
    );
});

const blobShapes = [
    'rounded-[58%_42%_52%_48%/46%_54%_46%_54%]',
    'rounded-[42%_58%_61%_39%/52%_38%_62%_48%]',
    'rounded-[63%_37%_45%_55%/55%_61%_39%_45%]',
    'rounded-[38%_62%_56%_44%/60%_44%_56%_40%]',
];
const blobTones = [
    'bg-forest text-matcha',
    'bg-matcha text-forest',
    'bg-berry-surface text-berry',
    'bg-tint-strong text-primary',
];
</script>

<template>
    <div class="flex flex-col gap-8">
        <section
            v-for="(group, gi) in groups"
            :key="group.letter"
            class="grid min-w-0 gap-4 sm:grid-cols-[5rem_1fr] sm:gap-6"
            :aria-labelledby="`ingredient-letter-${group.letter === '#' ? 'other' : group.letter}`"
        >
            <h3
                :id="`ingredient-letter-${group.letter === '#' ? 'other' : group.letter}`"
                class="flex items-center gap-3 sm:block"
            >
                <span
                    class="inline-flex size-14 items-center justify-center font-display-wonk text-3xl font-semibold shadow-card sm:size-20 sm:text-5xl"
                    :class="[
                        blobShapes[gi % blobShapes.length],
                        blobTones[gi % blobTones.length],
                    ]"
                    aria-hidden="true"
                    >{{ group.letter }}</span
                >
                <span class="sr-only">{{
                    group.letter === '#'
                        ? 'Weitere Zutaten'
                        : `Zutaten mit ${group.letter}`
                }}</span>
                <span
                    class="text-sm font-semibold text-ink-muted sm:mt-2 sm:block sm:text-center"
                    aria-hidden="true"
                    >{{ group.items.length }}</span
                >
            </h3>
            <ul
                class="grid min-w-0 grid-cols-1 content-start gap-3 self-start min-[420px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                :aria-labelledby="`ingredient-letter-${group.letter === '#' ? 'other' : group.letter}`"
            >
                <li
                    v-for="ingredient in group.items"
                    :key="ingredient.id"
                    class="min-w-0"
                    data-ingredient
                >
                    <IngredientCard :ingredient="ingredient" />
                </li>
            </ul>
        </section>
    </div>
</template>
