<script setup lang="ts">
import SettingsShell from '@/components/settings/SettingsShell.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Field from '@/components/ui/Field.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { KeyRound, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';

/**
 * E18: Einstellungsseite „KI-Import“. Der Schlüssel wird nie wieder
 * angezeigt – der Server liefert nur `hasKey` und die letzten vier Zeichen.
 */
const props = defineProps<{
    hasKey: boolean;
    keyHint: string | null;
    isHost: boolean;
}>();

const form = useForm({ gemini_api_key: '' });

const save = () => {
    form.put(route('settings.ai.update'), {
        preserveScroll: true,
        onFinish: () => form.reset('gemini_api_key'),
    });
};

const checking = ref(false);
const check = () => {
    checking.value = true;
    router.post(
        route('settings.ai.check'),
        {},
        { preserveScroll: true, onFinish: () => (checking.value = false) },
    );
};

const removing = ref(false);
const remove = () => {
    removing.value = true;
    router.delete(route('settings.ai.destroy'), {
        preserveScroll: true,
        onFinish: () => (removing.value = false),
    });
};
</script>

<template>
    <SettingsShell
        page-title="KI-Import"
        title="KI-Import"
        description="Dein eigener Gemini-Schlüssel für den TikTok-Import von Community-Rezepten"
        current="ai"
    >
        <template #title>
            KI-<span class="accent-word text-primary">Import</span>
        </template>

        <!-- Status auf Waldgrün -->
        <section
            class="relative overflow-hidden rounded-[2.25rem_1.75rem_2.5rem_2rem] bg-forest grain-light px-5 py-6 on-forest shadow-float sm:px-8 sm:py-8"
            aria-labelledby="ai-status-title"
            data-ai-key-status
        >
            <span
                class="pointer-events-none absolute -top-16 -right-12 size-56 rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-forest-soft/80"
                aria-hidden="true"
            ></span>
            <div
                class="relative flex min-w-0 flex-wrap items-center gap-4 sm:gap-6"
            >
                <span
                    class="inline-flex size-16 shrink-0 items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-matcha text-forest"
                    aria-hidden="true"
                >
                    <KeyRound class="size-7" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2
                        id="ai-status-title"
                        class="font-display text-2xl font-semibold text-on-forest sm:text-3xl"
                    >
                        {{
                            props.hasKey
                                ? 'Schlüssel hinterlegt'
                                : 'Noch kein Schlüssel hinterlegt'
                        }}
                    </h2>
                    <p class="mt-1 text-wrap-anywhere text-on-forest-muted">
                        <template v-if="props.hasKey">
                            Endet auf
                            <span
                                class="font-mono font-semibold text-on-forest"
                                data-key-hint
                                >…{{ props.keyHint }}</span
                            >. Der vollständige Schlüssel wird aus
                            Sicherheitsgründen nicht mehr angezeigt.
                        </template>
                        <template v-else>
                            Ohne eigenen Schlüssel ist der TikTok-Import im
                            Community-Formular ausgeschaltet. Alles andere
                            funktioniert wie gewohnt.
                        </template>
                    </p>
                </div>
                <Badge v-if="props.hasKey" tone="matcha">aktiv</Badge>
            </div>
            <div
                v-if="props.hasKey"
                class="relative mt-6 flex flex-wrap gap-2.5"
            >
                <Button
                    variant="lime"
                    :loading="checking"
                    data-check-key
                    @click="check"
                >
                    <ShieldCheck class="size-4" aria-hidden="true" />
                    Schlüssel prüfen
                </Button>
                <Button
                    variant="ghost"
                    class="text-danger-on-forest hover:bg-forest-soft active:bg-forest-deep"
                    :loading="removing"
                    data-remove-key
                    @click="remove"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Schlüssel löschen
                </Button>
            </div>
        </section>

        <section
            class="relative overflow-hidden rounded-[2rem_1.6rem_2.25rem_1.5rem] bg-surface p-5 shadow-card sm:p-8"
            aria-labelledby="ai-key-form-title"
        >
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute -top-10 -right-4 h-36 rotate-[38deg] text-tint"
            />
            <h2
                id="ai-key-form-title"
                class="relative font-display text-3xl font-semibold text-ink"
            >
                {{
                    props.hasKey
                        ? 'Schlüssel ersetzen'
                        : 'Schlüssel hinterlegen'
                }}
            </h2>
            <p class="relative mt-1 max-w-prose text-ink-muted">
                Den Schlüssel erstellst du kostenlos in Google AI Studio. Er
                wird verschlüsselt gespeichert, nur für deine eigenen Importe
                verwendet und nie protokolliert. Kosten oder Kontingente bei
                Google laufen über dein Google-Konto.
            </p>
            <p
                v-if="props.isHost"
                class="relative mt-3 max-w-prose rounded-[1.25rem_1rem_1.4rem_1.1rem] bg-tint px-4 py-3 text-sm text-ink"
            >
                Als Host nutzt du für Importe den Server-Schlüssel; ein eigener
                Schlüssel ist dafür nicht nötig.
            </p>

            <form
                class="relative mt-6 flex max-w-xl flex-col gap-5"
                @submit.prevent="save"
            >
                <Field
                    id="gemini_api_key"
                    v-slot="field"
                    label="Gemini-API-Schlüssel"
                    hint="Beginnt mit „AIza“ und hat 39 Zeichen."
                    required
                    :error="form.errors.gemini_api_key"
                >
                    <TextInput
                        v-model="form.gemini_api_key"
                        v-bind="field.inputAttrs"
                        type="password"
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="AIza…"
                    />
                </Field>
                <div class="flex flex-wrap items-center gap-3">
                    <Button type="submit" :loading="form.processing">
                        Schlüssel speichern
                    </Button>
                    <Link
                        :href="route('community.create')"
                        class="inline-flex min-h-11 items-center rounded-pebble px-3 font-semibold text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        Zum Community-Formular
                    </Link>
                </div>
            </form>
        </section>
    </SettingsShell>
</template>
