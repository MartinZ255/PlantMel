<script setup lang="ts">
/**
 * Gate C: mehrzeiliges Eingabefeld, gleiche Optik wie `TextInput`.
 */
withDefaults(defineProps<{ rows?: number }>(), { rows: 4 });

const model = defineModel<string | null>({ default: '' });
</script>

<template>
    <textarea
        v-model="model"
        :rows="rows"
        class="block w-full min-w-0 resize-y rounded-md border border-line-strong bg-surface px-3.5 py-2.5 text-base text-ink placeholder:text-ink-muted focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus disabled:cursor-not-allowed disabled:bg-canvas aria-[invalid=true]:border-danger"
    />
</template>
