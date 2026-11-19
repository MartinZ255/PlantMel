<template>
    <AppLayout page-title="Impressum">
        <article class="content-section legal-page" aria-labelledby="legal-h1">
            <h1 id="legal-h1" class="legal-page__title">Impressum</h1>

            <p v-if="!legal.filled" class="legal-draft-notice" role="note">
                <strong>Entwurf – Angaben des Betreibers fehlen noch.</strong>
                Markierte Stellen in eckigen Klammern sind Platzhalter.
            </p>

            <section aria-labelledby="imprint-provider">
                <h2 id="imprint-provider">Angaben gemäß § 5 DDG</h2>
                <address class="legal-page__address">
                    <LegalValue :value="legal.operator_name" /><br />
                    <LegalValue :value="legal.operator_street" /><br />
                    <LegalValue :value="legal.operator_postal_city" />
                    <template v-if="legal.operator_country">
                        <br /><LegalValue :value="legal.operator_country" />
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
                <address
                    v-if="legal.content_responsible_name"
                    class="legal-page__address"
                >
                    <LegalValue :value="legal.content_responsible_name" />
                    <template v-if="legal.content_responsible_address">
                        <br /><LegalValue
                            :value="legal.content_responsible_address"
                        />
                    </template>
                </address>
                <address v-else class="legal-page__address">
                    <LegalValue :value="legal.operator_name" /><br />
                    <LegalValue :value="legal.operator_street" /><br />
                    <LegalValue :value="legal.operator_postal_city" />
                </address>
                <p class="legal-check">
                    [PRÜFEN: Diese Angabe ist nur bei
                    journalistisch-redaktionell gestalteten Inhalten
                    vorgeschrieben. Klären, ob sie für PlantMel nötig ist; sonst
                    den Abschnitt entfernen.]
                </p>
            </section>

            <p class="legal-page__updated">
                Stand: <LegalValue :value="legal.last_updated" />
            </p>
        </article>
    </AppLayout>
</template>

<script setup lang="ts">
// Slice 4 (F-10): VORLAGE fuer das Impressum, keine Rechtsberatung.
// Alle Betreiberangaben kommen aus config/legal.php (Prop `legal`).
// Pruefliste: docs/modernization/RECHTSTEXTE.md
import AppLayout from '@/components/layout/AppLayout.vue';
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
                      { class: 'legal-placeholder' },
                      props.value || '[FEHLT]',
                  )
                : h('span', props.value ?? '');
    },
});
</script>
