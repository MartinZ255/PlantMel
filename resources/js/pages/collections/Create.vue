<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import Button from '@/components/ui/Button.vue';
import Field from '@/components/ui/Field.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FolderHeart } from 'lucide-vue-next';
import { route } from 'ziggy-js';

const form = useForm({
    name: '',
    description: '',
});

const submit = () => {
    form.post(route('collections.store'), {
        // Server-Fehler: Fokus auf das erste fehlerhafte Feld
        onError: (errors) => {
            const id = errors.name
                ? 'collection-name'
                : errors.description
                  ? 'collection-description'
                  : null;
            if (id) document.getElementById(id)?.focus();
        },
    });
};

// Beispiele, die beim Benennen helfen (nur Text, keine Funktion)
const examples = ['Unter der Woche', 'Besuch', 'Meal Prep', 'Sonntagskuchen'];
</script>

<template>
    <AppLayout page-title="Neue Sammlung" bleed>
        <section class="relative overflow-hidden">
            <BlobBackdrop tone="light" />
            <div
                class="relative mx-auto w-full max-w-(--content-max) px-4 pt-6 pb-16 sm:px-6 lg:px-8 lg:pt-10"
            >
                <Link
                    :href="route('collections.index')"
                    class="mb-5 inline-flex min-h-10 items-center gap-1.5 rounded-pebble px-2 text-sm font-semibold text-primary hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Alle Sammlungen
                </Link>

                <PageHeader
                    title="Neue Sammlung anlegen"
                    eyebrow="Sammlungen"
                    description="Erstelle eine Sammlung, um deine Rezepte in thematischen Listen zu organisieren."
                >
                    <template #title>
                        Neue Sammlung
                        <span class="accent-word text-primary">anlegen</span>
                    </template>
                </PageHeader>

                <div
                    class="mt-10 grid items-start gap-8 lg:grid-cols-[1.4fr_1fr] lg:gap-12"
                >
                    <!-- Formular als Papierblatt -->
                    <form
                        class="relative min-w-0 overflow-hidden rounded-[2.25rem_1.75rem_2.5rem_2rem] bg-surface p-5 shadow-float sm:p-8"
                        novalidate
                        @submit.prevent="submit"
                    >
                        <LeafOrnament
                            variant="sprig"
                            class="pointer-events-none absolute -top-10 -right-10 h-24 rotate-[24deg] text-tint"
                        />
                        <h2
                            class="relative font-display text-2xl font-semibold text-ink"
                        >
                            Basis-Informationen
                        </h2>
                        <p class="relative mt-1.5 text-ink-muted">
                            Gib deiner Sammlung einen klaren Namen und
                            beschreibe kurz, welche Rezepte hineingehören.
                        </p>

                        <div class="relative mt-6 flex flex-col gap-5">
                            <Field
                                id="collection-name"
                                label="Name der Sammlung"
                                required
                                :error="form.errors.name"
                                v-slot="field"
                            >
                                <TextInput
                                    v-model="form.name"
                                    v-bind="field.inputAttrs"
                                    type="text"
                                    maxlength="255"
                                    autocomplete="off"
                                    placeholder="z. B. Sonntagskuchen, Mealprep unter der Woche"
                                />
                            </Field>

                            <Field
                                id="collection-description"
                                label="Beschreibung (optional)"
                                :error="form.errors.description"
                                v-slot="field"
                            >
                                <Textarea
                                    v-model="form.description"
                                    v-bind="field.inputAttrs"
                                    :rows="3"
                                    placeholder="Kurze Beschreibung der Sammlung – wofür ist sie gedacht?"
                                />
                            </Field>
                        </div>

                        <div
                            class="relative mt-8 flex flex-col gap-4 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-sm text-ink-muted">
                                Du kannst später Rezepte zu dieser Sammlung
                                hinzufügen oder entfernen.
                            </p>
                            <div
                                class="flex shrink-0 flex-col-reverse gap-2 sm:flex-row"
                            >
                                <Button
                                    :href="route('collections.index')"
                                    variant="ghost"
                                >
                                    Abbrechen
                                </Button>
                                <Button
                                    type="submit"
                                    :loading="form.processing"
                                >
                                    Sammlung erstellen
                                </Button>
                            </div>
                        </div>
                    </form>

                    <!-- Seitenblatt: Waldgrün mit Beispielen -->
                    <aside
                        class="relative overflow-hidden rounded-[2rem_2.5rem_1.75rem_2.25rem] bg-forest grain-light p-6 on-forest sm:p-8 lg:mt-10"
                        aria-labelledby="collection-ideas-title"
                    >
                        <BlobBackdrop tone="forest" />
                        <LeafOrnament
                            variant="eucalyptus"
                            class="pointer-events-none absolute -right-4 -bottom-8 h-40 rotate-[-22deg] text-forest-soft"
                        />
                        <span
                            class="relative blob-mask inline-flex size-16 items-center justify-center bg-matcha text-forest shadow-card blob-2"
                            aria-hidden="true"
                        >
                            <FolderHeart class="size-7" />
                        </span>
                        <h2
                            id="collection-ideas-title"
                            class="relative mt-5 font-display text-2xl font-semibold text-on-forest"
                        >
                            Ideen für deine
                            <span class="accent-word text-matcha"
                                >Sammlung</span
                            >
                        </h2>
                        <ul class="relative mt-4 flex flex-wrap gap-2">
                            <li
                                v-for="example in examples"
                                :key="example"
                                class="rounded-seed bg-forest-deep/55 px-3 py-1 text-sm font-semibold text-on-forest ring-1 ring-forest-soft"
                            >
                                {{ example }}
                            </li>
                        </ul>
                        <p class="relative mt-5 text-on-forest-muted">
                            Rezepte fügst du später auf der Rezeptseite über
                            „Zur Sammlung hinzufügen“ hinzu.
                        </p>
                    </aside>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
