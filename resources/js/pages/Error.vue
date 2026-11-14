<template>
    <Head :title="heading" />

    <AppLayout :page-title="heading">
        <section class="content-section" aria-labelledby="error-heading">
            <div class="section-header">
                <h1 id="error-heading" class="section-header__title">
                    {{ heading }}
                </h1>
                <span class="section-header__meta">Fehler {{ status }}</span>
            </div>

            <p>{{ message }}</p>

            <div class="mt-4 flex flex-wrap gap-2">
                <Link :href="route('landing')" class="primary-pill-button">
                    Zur Startseite
                </Link>
                <Link
                    :href="route('recipes.index')"
                    class="secondary-pill-button"
                >
                    Zu den Rezepten
                </Link>
            </div>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
// F-68 (Slice 3): Fehlerseite fuer Inertia-Antworten mit Status 403, 404,
// 429, 500 und 503 (gerendert in bootstrap/app.php, withExceptions).
import AppLayout from '@/components/layout/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    status: number;
    message: string;
}>();

const headings: Record<number, string> = {
    403: 'Kein Zugriff',
    404: 'Seite nicht gefunden',
    419: 'Sitzung abgelaufen',
    429: 'Zu viele Anfragen',
    500: 'Serverfehler',
    503: 'Wartungsarbeiten',
};

const heading = computed(() => headings[props.status] ?? 'Fehler');
</script>
