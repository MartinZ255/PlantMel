<script setup lang="ts">
import { ref } from 'vue';

/**
 * Gate C: einzeiliges Eingabefeld. 16 px Schrift (kein iOS-Zoom, F-35),
 * Rahmen `line-strong` (≥ 3:1), Fehlerzustand über `aria-invalid`.
 * Weitere Attribute (id, type, placeholder, aria-*) fallen durch.
 */
const model = defineModel<string | number | null>({ default: '' });

const el = ref<HTMLInputElement | null>(null);
defineExpose({ focus: () => el.value?.focus(), el });
</script>

<template>
    <input
        ref="el"
        v-model="model"
        class="block min-h-11 w-full min-w-0 rounded-md border border-line-strong bg-surface px-3.5 py-2 text-base text-ink placeholder:text-ink-muted focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus disabled:cursor-not-allowed disabled:bg-canvas aria-[invalid=true]:border-danger"
    />
</template>
