<template>
    <!-- Slice 4 (F-10): Links zu Impressum und Datenschutz auf allen Seiten -->
    <footer
        class="app-footer"
        :class="{ 'app-footer--auth': variant === 'auth' }"
    >
        <nav class="app-footer__nav" aria-label="Rechtliches">
            <a
                :href="route('legal.imprint')"
                class="app-footer__link"
                :aria-current="isCurrent('legal.imprint') ? 'page' : undefined"
            >
                Impressum
            </a>
            <a
                :href="route('legal.privacy')"
                class="app-footer__link"
                :aria-current="isCurrent('legal.privacy') ? 'page' : undefined"
            >
                Datenschutz
            </a>
        </nav>
    </footer>
</template>

<script setup lang="ts">
import { route } from 'ziggy-js';

withDefaults(defineProps<{ variant?: 'app' | 'auth' }>(), {
    variant: 'app',
});

const isCurrent = (name: string) => {
    try {
        return route().current(name);
    } catch {
        return false;
    }
};
</script>
