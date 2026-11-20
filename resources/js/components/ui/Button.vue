<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, useAttrs } from 'vue';

/**
 * Gate C: Schaltfläche bzw. Link in Button-Optik (C1b: Kiesel-Form).
 *
 * - ohne `href`: `<button>` (Standard `type="button"`)
 * - mit `href`: Inertia-`Link` (interne Navigation ohne Neuladen, F-54);
 *   `external` erzwingt ein normales `<a>` (z. B. Downloads)
 * - `loading`: deaktiviert, `aria-busy="true"`, Spinner; der Text bleibt
 *   lesbar (Name ändert sich nicht)
 * - zusätzliche Klassen werden per tailwind-merge eingemischt (überschreiben
 *   die Variante gezielt, z. B. `text-danger` an einem ghost-Button)
 */
defineOptions({ inheritAttrs: false });
export type ButtonVariant =
    | 'primary'
    | 'secondary'
    | 'ghost'
    | 'danger'
    | 'lime'
    | 'berry'
    | 'outline-light';
export type ButtonSize = 'sm' | 'md' | 'lg';

interface Props {
    variant?: ButtonVariant;
    size?: ButtonSize;
    href?: string;
    external?: boolean;
    type?: 'button' | 'submit' | 'reset';
    disabled?: boolean;
    loading?: boolean;
    /** volle Breite (mobil oft sinnvoll) */
    block?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'md',
    href: undefined,
    external: false,
    type: 'button',
    disabled: false,
    loading: false,
    block: false,
});

/*
 * Gate C1b: „Kiesel“-Form (leicht unregelmäßige Rundung, die beim Hover
 * atmet), weiche waldgrüne Schatten, kleines Anheben. Varianten:
 * - primary: Waldgrün, Hauptaktion auf hellem Grund
 * - secondary: Kontur Waldgrün auf Naturgrund
 * - ghost: ohne Fläche (Abbrechen, Nebenwege)
 * - danger: bestätigende Löschaktion im Dialog
 * - lime: Matcha mit Waldgrün-Text – Hauptaktion AUF Waldgrün-Flächen
 * - berry: Rote Bete – sparsam für einen einzelnen warmen Aufruf
 * - outline-light: Kontur hell – Nebenaktion auf Waldgrün-Flächen
 */
const variants: Record<ButtonVariant, string> = {
    primary:
        'bg-primary text-on-primary shadow-card hover:-translate-y-0.5 hover:bg-primary-hover hover:shadow-raised active:translate-y-0 active:bg-primary-active',
    secondary:
        'border-[1.5px] border-primary bg-surface/70 text-primary hover:-translate-y-0.5 hover:bg-tint active:translate-y-0 active:bg-tint-strong',
    ghost: 'text-ink hover:bg-tint active:bg-tint-strong',
    danger: 'bg-danger text-white shadow-card hover:bg-danger-hover active:bg-danger-hover',
    lime: 'bg-matcha text-forest shadow-card hover:-translate-y-0.5 hover:bg-matcha-hover hover:shadow-raised active:translate-y-0',
    berry: 'bg-berry text-white shadow-berry hover:-translate-y-0.5 hover:bg-berry-hover active:translate-y-0',
    'outline-light':
        'border-[1.5px] border-on-forest-muted text-on-forest hover:-translate-y-0.5 hover:border-on-forest hover:bg-forest-soft active:translate-y-0',
};

const sizes: Record<ButtonSize, string> = {
    sm: 'min-h-9 gap-1.5 px-4 text-sm',
    md: 'min-h-11 gap-2 px-5.5 text-base',
    lg: 'min-h-13 gap-2.5 px-7 text-lg',
};

const attrs = useAttrs();
const passthrough = computed(() => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { class: _class, ...rest } = attrs;
    return rest;
});

const classes = computed(() =>
    cn(
        'pebble-morph inline-flex items-center justify-center text-center font-semibold no-underline select-none',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
        'disabled:cursor-not-allowed disabled:opacity-60 aria-disabled:cursor-not-allowed aria-disabled:opacity-60',
        variants[props.variant],
        sizes[props.size],
        props.block && 'w-full',
        attrs.class as string | undefined,
    ),
);

const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <Link
        v-if="href && !external"
        v-bind="passthrough"
        :href="href"
        :class="classes"
        :aria-disabled="isDisabled ? 'true' : undefined"
        :aria-busy="loading ? 'true' : undefined"
    >
        <slot />
    </Link>
    <a
        v-else-if="href"
        v-bind="passthrough"
        :href="href"
        :class="classes"
        :aria-disabled="isDisabled ? 'true' : undefined"
    >
        <slot />
    </a>
    <button
        v-else
        v-bind="passthrough"
        :type="type"
        :class="classes"
        :disabled="isDisabled"
        :aria-busy="loading ? 'true' : undefined"
    >
        <LoaderCircle
            v-if="loading"
            class="size-4 shrink-0 animate-spin motion-reduce:animate-none"
            aria-hidden="true"
        />
        <slot />
    </button>
</template>
