<template>
    <AppLayout page-title="Impressum" bleed>
        <article aria-labelledby="legal-h1">
            <!-- Gate C2: ruhiger Kopf auf Naturgrund, darunter eine Lesespalte (~68 Zeichen) -->
            <header class="relative overflow-hidden">
                <BlobBackdrop tone="light" />
                <LeafOrnament
                    variant="sprig"
                    class="pointer-events-none absolute top-6 right-[5%] hidden h-32 rotate-[18deg] text-tint-strong sm:block lg:h-40"
                />
                <div
                    class="relative mx-auto w-full max-w-(--content-max) px-4 pt-10 pb-8 sm:px-6 sm:pt-14 lg:px-8"
                >
                    <p
                        class="mb-3 inline-flex items-center gap-2 rounded-seed bg-surface/80 px-3.5 py-1.5 text-sm font-semibold text-primary shadow-card"
                    >
                        <LeafOrnament
                            variant="sprout"
                            class="size-4 text-moss"
                        />
                        Rechtliches
                    </p>
                    <h1
                        id="legal-h1"
                        class="font-display-wonk text-[clamp(2.3rem,9vw,4.5rem)] leading-[1.02] font-semibold text-balance text-wrap-anywhere text-ink"
                    >
                        Impressum
                    </h1>
                    <p class="mt-4 max-w-2xl text-lg text-ink-muted sm:text-xl">
                        Wer hinter PlantMel steht und wie du uns erreichst.
                    </p>

                    <p
                        v-if="!legal.filled"
                        class="mt-6 flex max-w-[68ch] items-start gap-3 rounded-[1.25rem_1rem_1.4rem_0.9rem] border-[1.5px] border-warning/40 bg-warning-surface px-4 py-3 text-warning"
                        role="note"
                        data-legal-draft-notice
                    >
                        <TriangleAlert
                            class="mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="min-w-0">
                            <strong
                                >Entwurf – Angaben des Betreibers fehlen
                                noch.</strong
                            >
                            Markierte Stellen in eckigen Klammern sind
                            Platzhalter.
                        </span>
                    </p>
                </div>
            </header>

            <div
                class="relative mx-auto w-full max-w-(--content-max) overflow-x-clip px-4 pb-16 sm:px-6 lg:px-8"
            >
                <LeafOrnament
                    variant="eucalyptus"
                    class="pointer-events-none absolute top-16 right-[6%] hidden h-72 rotate-[14deg] text-tint-strong xl:block"
                />
                <div
                    class="relative legal-prose max-w-[calc(68ch+5rem)] rounded-[2rem_1.6rem_2.25rem_1.5rem] bg-surface px-5 py-7 shadow-card sm:px-10 sm:py-10 lg:ml-[6%]"
                >
                    <section aria-labelledby="imprint-provider">
                        <h2 id="imprint-provider">Angaben gemäß § 5 DDG</h2>
                        <address>
                            <LegalValue :value="legal.operator_name" /><br />
                            <LegalValue :value="legal.operator_street" /><br />
                            <LegalValue :value="legal.operator_postal_city" />
                            <template v-if="legal.operator_country">
                                <br /><LegalValue
                                    :value="legal.operator_country"
                                />
                            </template>
                        </address>
                    </section>

                    <section aria-labelledby="imprint-contact">
                        <h2 id="imprint-contact">Kontakt</h2>
                        <p>
                            E-Mail:
                            <a
                                v-if="isRealValue(legal.operator_email)"
                                :href="`mailto:${legal.operator_email}`"
                                >{{ legal.operator_email }}</a
                            >
                            <LegalValue v-else :value="legal.operator_email" />
                            <template v-if="legal.operator_phone">
                                <br />Telefon:
                                <LegalValue :value="legal.operator_phone" />
                            </template>
                        </p>
                    </section>

                    <section aria-labelledby="imprint-responsible">
                        <h2 id="imprint-responsible">
                            Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV
                        </h2>
                        <address v-if="legal.content_responsible_name">
                            <LegalValue
                                :value="legal.content_responsible_name"
                            />
                            <template v-if="legal.content_responsible_address">
                                <br /><LegalValue
                                    :value="legal.content_responsible_address"
                                />
                            </template>
                        </address>
                        <address v-else>
                            <LegalValue :value="legal.operator_name" /><br />
                            <LegalValue :value="legal.operator_street" /><br />
                            <LegalValue :value="legal.operator_postal_city" />
                        </address>
                        <p data-legal-check>
                            [PRÜFEN: Diese Angabe ist nur bei
                            journalistisch-redaktionell gestalteten Inhalten
                            vorgeschrieben. Klären, ob sie für PlantMel nötig
                            ist; sonst den Abschnitt entfernen.]
                        </p>
                    </section>

                    <p data-legal-updated>
                        Stand: <LegalValue :value="legal.last_updated" />
                    </p>
                </div>
            </div>
        </article>
    </AppLayout>
</template>

<script setup lang="ts">
// Slice 4 (F-10): VORLAGE fuer das Impressum, keine Rechtsberatung.
// Alle Betreiberangaben kommen aus config/legal.php (Prop `legal`).
// Pruefliste: docs/modernization/RECHTSTEXTE.md
import AppLayout from '@/components/layout/AppLayout.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { TriangleAlert } from 'lucide-vue-next';
import { defineComponent, h, type PropType } from 'vue';

interface LegalInfo {
    operator_name: string | null;
    operator_street: string | null;
    operator_postal_city: string | null;
    operator_country: string | null;
    operator_email: string | null;
    operator_phone: string | null;
    content_responsible_name: string | null;
    content_responsible_address: string | null;
    last_updated: string | null;
    filled: boolean;
}

defineProps<{ legal: LegalInfo }>();

const isPlaceholder = (value: string | null | undefined) =>
    !value || value.includes('[');
const isRealValue = (value: string | null | undefined) => !isPlaceholder(value);

/** Zeigt einen Wert; Platzhalter (leer oder mit "[") sind deutlich markiert. */
const LegalValue = defineComponent({
    props: {
        value: { type: String as PropType<string | null>, default: null },
    },
    setup(props) {
        return () =>
            isPlaceholder(props.value)
                ? h(
                      'mark',
                      { 'data-legal-placeholder': '' },
                      props.value || '[FEHLT]',
                  )
                : h('span', props.value ?? '');
    },
});
</script>
