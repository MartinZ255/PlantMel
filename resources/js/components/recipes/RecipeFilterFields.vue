<template>
    <div class="grid gap-6 md:grid-cols-2 md:gap-8">
        <!-- Kategorien: Umschalter mit aria-pressed, wirken sofort -->
        <fieldset class="min-w-0 border-0 p-0 md:col-span-2">
            <legend class="mb-2 text-sm font-semibold text-ink">
                Kategorien
            </legend>
            <p v-if="!categories.length" class="text-sm text-ink-muted">
                Es gibt noch keine Kategorien.
            </p>
            <ul v-else class="flex flex-wrap gap-2">
                <li v-for="cat in categories" :key="cat.key" class="max-w-full">
                    <Chip
                        :pressed="cat.active"
                        @click="$emit('toggle-category', cat.key)"
                    >
                        {{ cat.label }}
                        <span
                            v-if="cat.count !== null"
                            class="ml-0.5 font-normal"
                        >
                            ({{ cat.count }})
                        </span>
                    </Chip>
                </li>
            </ul>
        </fieldset>

        <Field
            id="includeIngredients"
            label="Enthaltene Zutaten"
            hint="Mehrere Zutaten mit Komma trennen."
            v-slot="{ inputAttrs }"
        >
            <TextInput
                v-model="include"
                v-bind="inputAttrs"
                type="text"
                placeholder="z. B. Tomaten, Knoblauch"
                @keydown.enter.prevent="$emit('apply')"
            />
        </Field>

        <Field
            id="excludeIngredients"
            label="Zutaten ausschließen"
            hint="Rezepte mit diesen Zutaten werden ausgeblendet."
            v-slot="{ inputAttrs }"
        >
            <TextInput
                v-model="exclude"
                v-bind="inputAttrs"
                type="text"
                placeholder="z. B. Nüsse, Sellerie"
                @keydown.enter.prevent="$emit('apply')"
            />
        </Field>

        <div v-if="suggestions.length" class="md:col-span-2">
            <p id="ingredient-suggestions" class="mb-2 text-sm text-ink-muted">
                Häufig genutzte Zutaten hinzufügen:
            </p>
            <ul
                class="flex flex-wrap gap-2"
                aria-labelledby="ingredient-suggestions"
            >
                <li v-for="s in suggestions" :key="s">
                    <button
                        type="button"
                        class="inline-flex min-h-9 items-center gap-1 rounded-full border border-dashed border-line-strong px-3.5 text-sm font-medium text-ink hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        @click="$emit('add-suggestion', s)"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        {{ s }}
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup lang="ts">
import Chip from '@/components/ui/Chip.vue';
import Field from '@/components/ui/Field.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { Plus } from 'lucide-vue-next';

/** Gate C: Felder des Rezeptfilters (inline ab md, sonst im Dialog). */
defineProps<{
    categories: {
        key: string;
        label: string;
        count: number | null;
        active: boolean;
    }[];
    suggestions: string[];
}>();

defineEmits<{
    (e: 'toggle-category', key: string): void;
    (e: 'add-suggestion', name: string): void;
    (e: 'apply'): void;
}>();

const include = defineModel<string>('include', { required: true });
const exclude = defineModel<string>('exclude', { required: true });
</script>
