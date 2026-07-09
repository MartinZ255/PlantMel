<template>
    <AppLayout page-title="Rezept anlegen">
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">Neues Rezept</div>
                <div class="section-header__meta">
                    Entwurf · noch nicht gespeichert
                </div>
            </div>

            <!-- TikTok-Import -->
            <div class="tiktok-import">
                <div class="tiktok-import__label">
                    Rezept aus TikTok importieren
                    <span class="inline-badge">optional</span>
                </div>

                <div class="tiktok-import__row">
                    <input
                        v-model="importUrl"
                        type="url"
                        class="form-field__input tiktok-import__input"
                        placeholder="https://www.tiktok.com/@…/video/…"
                        :disabled="importRunning"
                        @keydown.enter.prevent="startImport"
                    />
                    <button
                        type="button"
                        class="primary-pill-button tiktok-import__button"
                        :disabled="importRunning || !importUrl.trim()"
                        @click="startImport"
                    >
                        {{ importRunning ? 'Import läuft …' : 'Importieren' }}
                    </button>
                </div>

                <p v-if="importRunning" class="tiktok-import__status">
                    {{ importStatusText }} – das kann bis zu zwei Minuten dauern.
                </p>
                <p v-else-if="importError" class="form-field__error">
                    {{ importError }}
                </p>
                <p v-else-if="importedRecipe" class="tiktok-import__status tiktok-import__status--success">
                    Import übernommen – bitte unten prüfen und speichern.
                </p>
            </div>

            <RecipeForm
                mode="create"
                :rating-dimensions="ratingDimensions"
                :categories="categories"
                :ingredients="ingredients"
                :recipe="importedRecipe ?? null"
                :submit-route="route('recipes.store')"
                submit-method="post"
            />
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { route } from 'ziggy-js';
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';

interface ImportedRecipe {
    name?: string;
    description?: string;
    duration_minutes?: number | null;
    servings?: number | null;
    source?: string;
    tags?: string[];
    ingredients?: { amount: string; name: string }[];
    steps?: string[];
    notes?: string;
}

const props = defineProps<{
    ratingDimensions: {
        id: number;
        name: string;
        description?: string | null;
    }[];
    categories: {
        id: number;
        name: string;
    }[];
    ingredients: {
        id: number;
        name: string;
    }[];
    importedRecipe?: ImportedRecipe | null;
}>();

const importUrl = ref('');
const importRunning = ref(false);
const importError = ref<string | null>(null);
const importStatusText = ref('Import wird gestartet');

let pollTimer: ReturnType<typeof setInterval> | null = null;

const statusLabels: Record<string, string> = {
    pending: 'Import wird gestartet',
    downloading: 'Video wird geladen',
    analyzing: 'Rezept wird erkannt',
};

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const startImport = async () => {
    const url = importUrl.value.trim();
    if (!url || importRunning.value) return;

    importError.value = null;
    importRunning.value = true;
    importStatusText.value = statusLabels.pending;

    try {
        const { data } = await axios.post(route('recipeImports.store'), { url });
        const importId: number = data.id;

        pollTimer = setInterval(async () => {
            try {
                const { data: status } = await axios.get(
                    route('recipeImports.show', importId),
                );

                if (status.status === 'done') {
                    stopPolling();
                    // Seite mit Vorbelegung neu laden – das Formular
                    // initialisiert sich aus dem Import-Ergebnis
                    router.visit(route('recipes.create', { import: importId }));
                } else if (status.status === 'failed') {
                    stopPolling();
                    importRunning.value = false;
                    importError.value =
                        status.error ?? 'Import fehlgeschlagen. Bitte erneut versuchen.';
                } else {
                    importStatusText.value =
                        statusLabels[status.status] ?? 'Import läuft';
                }
            } catch {
                stopPolling();
                importRunning.value = false;
                importError.value = 'Verbindung zum Import-Status verloren.';
            }
        }, 3000);
    } catch (error: any) {
        importRunning.value = false;
        importError.value =
            error?.response?.data?.message ?? 'Import konnte nicht gestartet werden.';
    }
};

onBeforeUnmount(stopPolling);
</script>
