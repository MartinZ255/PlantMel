<script setup lang="ts">
import { computed } from 'vue';

/**
 * Gate C: Formularfeld-Rahmen nach dem Muster des Rezeptformulars (F-41):
 * Label mit `for`, sichtbare Pflicht-Markierung (Stern, für Screenreader
 * durch `required`/`aria-required` am Feld), Hinweis und Fehler mit festen
 * IDs, die das Feld über `aria-describedby` referenziert.
 *
 * Das eigentliche Eingabeelement kommt in den Slot und bindet die
 * Slot-Props:
 *
 *   <Field id="name" label="Name" required :error="form.errors.name" v-slot="field">
 *       <TextInput v-model="form.name" v-bind="field.inputAttrs" />
 *   </Field>
 */
interface Props {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string | string[] | null;
    /** Label nur für Screenreader (z. B. Suchfeld mit sichtbarem Platzhalter) */
    labelHidden?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    required: false,
    hint: undefined,
    error: undefined,
    labelHidden: false,
});

const errorText = computed(() => {
    const e = props.error;
    if (!e) return '';
    return Array.isArray(e) ? e.filter(Boolean).join(' ') : e;
});

const hintId = computed(() => `${props.id}-hint`);
const errorId = computed(() => `${props.id}-error`);

const describedBy = computed(
    () =>
        [props.hint ? hintId.value : null, errorText.value ? errorId.value : null]
            .filter(Boolean)
            .join(' ') || undefined,
);

const inputAttrs = computed(() => ({
    id: props.id,
    required: props.required || undefined,
    'aria-required': props.required ? ('true' as const) : undefined,
    'aria-invalid': errorText.value ? ('true' as const) : undefined,
    'aria-describedby': describedBy.value,
}));
</script>

<template>
    <div class="flex min-w-0 flex-col gap-1.5">
        <label
            :for="id"
            class="text-sm font-semibold text-ink"
            :class="labelHidden && 'sr-only'"
        >
            {{ label }}
            <span v-if="required" class="text-danger" aria-hidden="true">*</span>
        </label>
        <p v-if="hint" :id="hintId" class="text-sm text-ink-muted">
            {{ hint }}
        </p>
        <slot
            :input-attrs="inputAttrs"
            :invalid="!!errorText"
            :described-by="describedBy"
        />
        <p
            v-if="errorText"
            :id="errorId"
            class="text-sm font-medium text-danger"
        >
            {{ errorText }}
        </p>
    </div>
</template>
