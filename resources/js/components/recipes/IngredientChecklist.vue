<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { Check, RotateCcw } from 'lucide-vue-next';
import { computed, ref, useId, watch } from 'vue';

/**
 * Gate C1b: Zutatenliste zum Abhaken beim Kochen.
 *
 * - echte Checkboxen mit Label (Menge + Name + Alternativen) – per Tastatur
 *   mit Tab/Leertaste bedienbar, sichtbarer Fokus
 * - rein clientseitig: nichts wird gespeichert; beim Wechsel des Rezepts
 *   (andere Zutaten) beginnt die Liste neu
 * - Fortschritt als Text („2 von 6 abgehakt“) in einer höflichen Live-Region
 * - Abgehaktes wird durchgestrichen und gedämpft (zusätzlich zum Häkchen,
 *   nicht nur über Farbe)
 */
const props = defineProps<{
    ingredients: {
        amount: string | null;
        name: string | null;
        alternatives?: string[];
    }[];
    /** ID der Überschrift (Name der Liste) */
    labelledBy: string;
}>();

const uid = useId();
const checked = ref<boolean[]>([]);

watch(
    () => props.ingredients.map((i) => `${i.amount}|${i.name}`).join('\n'),
    () => {
        checked.value = props.ingredients.map(() => false);
    },
    { immediate: true },
);

const doneCount = computed(() => checked.value.filter(Boolean).length);

const reset = () => {
    checked.value = props.ingredients.map(() => false);
};
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-ink-muted" aria-live="polite">
                {{ doneCount }} von {{ ingredients.length }} abgehakt
            </p>
            <button
                v-if="doneCount > 0"
                type="button"
                class="inline-flex min-h-9 items-center gap-1.5 rounded-seed px-2.5 text-sm font-semibold text-primary hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                @click="reset"
            >
                <RotateCcw class="size-4" aria-hidden="true" />
                Häkchen zurücksetzen
            </button>
        </div>

        <ul class="mt-3 flex flex-col gap-1" :aria-labelledby="labelledBy">
            <li
                v-for="(ingredient, index) in ingredients"
                :key="`${index}-${ingredient.name}`"
            >
                <label
                    :for="`${uid}-ing-${index}`"
                    class="group/ing flex cursor-pointer items-start gap-3 rounded-[1rem_0.8rem_1.1rem_0.85rem] px-2.5 py-2.5 transition-colors hover:bg-tint/70"
                >
                    <span class="relative mt-0.5 inline-flex shrink-0">
                        <input
                            :id="`${uid}-ing-${index}`"
                            v-model="checked[index]"
                            type="checkbox"
                            class="peer size-6 cursor-pointer appearance-none rounded-seed border-[1.5px] border-line-strong bg-surface transition-colors checked:border-primary checked:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        />
                        <Check
                            class="pointer-events-none absolute inset-0 m-auto size-4 text-on-primary opacity-0 peer-checked:opacity-100"
                            :stroke-width="3"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="grid min-w-0 flex-1 grid-cols-[minmax(4.25rem,auto)_minmax(0,1fr)] gap-x-3"
                    >
                        <span
                            class="font-semibold text-wrap-anywhere"
                            :class="
                                checked[index]
                                    ? 'text-ink-muted line-through'
                                    : 'text-primary'
                            "
                        >
                            {{ ingredient.amount || '–' }}
                        </span>
                        <span
                            class="min-w-0 text-wrap-anywhere"
                            :class="
                                checked[index]
                                    ? 'text-ink-muted line-through'
                                    : 'text-ink'
                            "
                        >
                            {{ ingredient.name }}
                            <span
                                v-if="ingredient.alternatives?.length"
                                class="mt-0.5 flex items-start gap-1 text-sm text-ink-muted"
                            >
                                <LeafOrnament
                                    variant="sprout"
                                    class="mt-0.5 size-3.5 shrink-0 text-moss"
                                />
                                <span class="min-w-0"
                                    >oder:
                                    {{
                                        ingredient.alternatives.join(' / ')
                                    }}</span
                                >
                            </span>
                        </span>
                    </span>
                </label>
            </li>
        </ul>
    </div>
</template>
