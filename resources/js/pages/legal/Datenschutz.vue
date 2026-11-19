<template>
    <AppLayout page-title="Datenschutzerklärung">
        <article class="content-section legal-page" aria-labelledby="legal-h1">
            <h1 id="legal-h1" class="legal-page__title">
                Datenschutzerklärung
            </h1>

            <p v-if="!legal.filled" class="legal-draft-notice" role="note">
                <strong>Entwurf – Angaben des Betreibers fehlen noch.</strong>
                Markierte Stellen in eckigen Klammern sind Platzhalter.
            </p>

            <p>
                Diese Erklärung beschreibt, welche personenbezogenen Daten
                PlantMel verarbeitet, wofür und auf welcher Rechtsgrundlage.
                PlantMel verwendet keine Analyse- oder Tracking-Dienste, keine
                Werbung und keine Social-Media-Plugins.
            </p>

            <section aria-labelledby="privacy-controller">
                <h2 id="privacy-controller">1. Verantwortlicher</h2>
                <address class="legal-page__address">
                    <LegalValue :value="legal.operator_name" /><br />
                    <LegalValue :value="legal.operator_street" /><br />
                    <LegalValue :value="legal.operator_postal_city" />
                    <template v-if="legal.operator_country">
                        <br /><LegalValue :value="legal.operator_country" />
                    </template>
                    <br />E-Mail:
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
                </address>
            </section>

            <section aria-labelledby="privacy-hosting">
                <h2 id="privacy-hosting">2. Hosting und Server-Logfiles</h2>
                <p>
                    PlantMel läuft auf einem Server von
                    <LegalValue :value="legal.hosting_provider_name" />
                    (<LegalValue :value="legal.hosting_provider_address" />).
                    Beim Aufruf jeder Seite verarbeitet der Server technisch
                    notwendig Ihre IP-Adresse sowie die Angaben Ihres Browsers
                    zur Anfrage.
                </p>
                <p class="legal-check">
                    [PRÜFEN: Welche Daten der Webserver protokolliert (üblich:
                    IP-Adresse, Datum und Uhrzeit, aufgerufene Adresse,
                    Referrer, Browserkennung, Statuscode) und ob mit dem
                    Hosting-Anbieter ein Vertrag zur Auftragsverarbeitung nach
                    Art. 28 DSGVO besteht.]
                </p>
                <p>
                    Zweck ist der sichere und stabile Betrieb der Website.
                    Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO; unser
                    berechtigtes Interesse liegt in der Bereitstellung und
                    Absicherung des Angebots. Speicherdauer der Logfiles:
                    <LegalValue :value="legal.server_log_retention" />.
                </p>
            </section>

            <section aria-labelledby="privacy-cookies">
                <h2 id="privacy-cookies">3. Cookies</h2>
                <p>
                    PlantMel setzt ausschließlich technisch notwendige Cookies.
                    Dafür ist keine Einwilligung erforderlich (§ 25 Abs. 2 Nr. 2
                    TDDDG). Tracking-, Analyse- oder Werbe-Cookies werden nicht
                    verwendet.
                </p>
                <ul>
                    <li>
                        <code>{{ session.cookie }}</code> – hält Ihre Sitzung
                        aufrecht, z. B. die Anmeldung. Das Cookie läuft nach
                        {{ session.lifetime_minutes }} Minuten ohne Aktivität ab
                        und ist für Skripte nicht lesbar.
                    </li>
                    <li>
                        <code>XSRF-TOKEN</code> – schützt Formulare vor
                        gefälschten Anfragen (CSRF). Gleiche Laufzeit wie das
                        Sitzungs-Cookie.
                    </li>
                    <li>
                        <code>remember_web_…</code> – nur, wenn Sie bei der
                        Anmeldung „Angemeldet bleiben“ wählen. Hält Sie bis zu
                        400 Tage angemeldet; das Abmelden löscht es.
                    </li>
                </ul>
                <p>
                    Rechtsgrundlage für die damit verbundene Verarbeitung ist
                    Art. 6 Abs. 1 lit. f DSGVO (sicherer Betrieb) bzw. lit. b
                    DSGVO, soweit Sie angemeldet sind.
                </p>
            </section>

            <section aria-labelledby="privacy-sessions">
                <h2 id="privacy-sessions">4. Sitzungsdaten auf dem Server</h2>
                <p v-if="session.database">
                    Zu jeder Sitzung speichert der Server in seiner Datenbank
                    die Sitzungskennung, bei angemeldeten Personen die
                    Konto-Nummer, die IP-Adresse, die Browserkennung
                    (User-Agent), den Zeitpunkt der letzten Aktivität und den
                    Sitzungsinhalt (z. B. Anmeldestatus). Abgelaufene Sitzungen
                    werden automatisch gelöscht.
                </p>
                <p v-else>
                    Zu jeder Sitzung speichert der Server die Sitzungskennung
                    und den Sitzungsinhalt (z. B. Anmeldestatus). Abgelaufene
                    Sitzungen werden automatisch gelöscht.
                </p>
                <p>
                    Zum Schutz vor dem Ausprobieren von Passwörtern zählt der
                    Server fehlgeschlagene Anmeldeversuche für eine Minute
                    anhand von E-Mail-Adresse und IP-Adresse. Rechtsgrundlage
                    ist Art. 6 Abs. 1 lit. f DSGVO (Schutz der Konten).
                </p>
            </section>

            <section aria-labelledby="privacy-account">
                <h2 id="privacy-account">5. Registrierung und Benutzerkonto</h2>
                <p>
                    Für ein Konto speichern wir Ihren Namen, Ihre E-Mail-Adresse
                    und Ihr Passwort (nur als nicht umkehrbarer Hash) sowie die
                    Zeitpunkte von Anlage und letzter Änderung. Ohne diese
                    Angaben ist keine Registrierung möglich. Ohne Konto können
                    Sie Rezepte und die Zutatensuche trotzdem nutzen.
                </p>
                <p>
                    Zweck ist die Bereitstellung des Kontos und seiner
                    Funktionen. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO
                    (Nutzungsvertrag). Die Daten bleiben gespeichert, bis Sie
                    das Konto löschen. Das können Sie jederzeit selbst unter
                    „Einstellungen → Profil → Konto löschen“ tun.
                </p>
            </section>

            <section aria-labelledby="privacy-collections">
                <h2 id="privacy-collections">6. Sammlungen</h2>
                <p>
                    Angemeldete Personen können Sammlungen anlegen. Gespeichert
                    werden Name und Beschreibung der Sammlung und die
                    enthaltenen Rezepte, verknüpft mit Ihrem Konto. Sammlungen
                    sind nur für Sie selbst sichtbar und werden mit dem Konto
                    gelöscht. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO.
                </p>
            </section>

            <section aria-labelledby="privacy-mail">
                <h2 id="privacy-mail">7. E-Mail-Versand</h2>
                <p>
                    Wenn Sie „Passwort vergessen“ nutzen, senden wir Ihnen einen
                    Link zum Zurücksetzen an Ihre E-Mail-Adresse. Dafür wird die
                    Adresse zusammen mit einem Einmal-Code (nur als Hash)
                    gespeichert; der Link ist 60 Minuten gültig. Der Versand
                    erfolgt über
                    <LegalValue :value="legal.mail_provider" />. Weitere E-Mails
                    (z. B. Newsletter) versenden wir nicht. Rechtsgrundlage ist
                    Art. 6 Abs. 1 lit. b DSGVO.
                </p>
                <p class="legal-check">
                    [PRÜFEN: Vertrag zur Auftragsverarbeitung mit dem
                    Mailanbieter.]
                </p>
                <p class="legal-check">
                    [PRÜFEN: Nur wenn im Betrieb die Bestätigung der
                    E-Mail-Adresse eingeschaltet ist
                    (REQUIRE_EMAIL_VERIFICATION=true), diesen Satz übernehmen,
                    sonst löschen: „Nach der Registrierung und nach einer
                    Änderung der E-Mail-Adresse senden wir eine E-Mail mit einem
                    Bestätigungslink an die angegebene Adresse. Bis zur
                    Bestätigung sind einzelne Funktionen gesperrt. Gespeichert
                    wird der Zeitpunkt der Bestätigung.“]
                </p>
            </section>

            <section aria-labelledby="privacy-import">
                <h2 id="privacy-import">
                    8. Rezept-Import aus TikTok (nur für Rezept-Verwaltung)
                </h2>
                <p>
                    Personen mit der Rolle „Host“ können ein Rezept aus einem
                    TikTok-Video übernehmen. Dafür ruft unser Server die
                    Beschreibung des Videos und bei Bedarf das Video selbst bei
                    TikTok ab (Programm yt-dlp). Die Anfrage stellt der Server;
                    Daten von Besucherinnen und Besuchern werden dabei nicht an
                    TikTok übermittelt.
                </p>
                <p>
                    Zur Auswertung senden wir die Beschreibung und, falls sie
                    nicht ausreicht, das Video an die Gemini API von Google.
                    Videos können personenbezogene Daten der darin gezeigten
                    Personen enthalten (Bild, Stimme). Angaben zu Ihrem Konto
                    werden nicht an Google übermittelt. Ein hochgeladenes Video
                    lassen wir unmittelbar nach der Auswertung bei Google
                    löschen, auch wenn die Auswertung fehlschlägt; die
                    vorübergehende Kopie auf unserem Server wird ebenfalls
                    entfernt.
                </p>
                <p>
                    Google verarbeitet die Daten auch in den USA (Drittland).
                    <span class="legal-check"
                        >[PRÜFEN: Vertragspartner bei Google, Grundlage der
                        Übermittlung in die USA (z. B. EU-US Data Privacy
                        Framework oder Standardvertragsklauseln), ob die
                        Bedingungen der Gemini API eine Nutzung der Inhalte
                        durch Google ausschließen (bezahlte oder kostenlose
                        Nutzung) und wie lange Google Anfragen speichert.]</span
                    >
                </p>
                <p>
                    Gespeichert werden zu jedem Import das Konto, der Link, der
                    Status, das Ergebnis und gegebenenfalls eine Fehlermeldung.
                    Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO; unser
                    berechtigtes Interesse ist das einfache Erfassen von
                    Rezepten.
                    <span class="legal-check"
                        >[PRÜFEN: Rechtsgrundlage und Speicherdauer der
                        Import-Einträge; derzeit werden sie erst mit dem Konto
                        gelöscht.]</span
                    >
                </p>
            </section>

            <section aria-labelledby="privacy-fonts">
                <h2 id="privacy-fonts">9. Schriftarten</h2>
                <p>
                    Die Schrift „Instrument Sans“ wird von unserem eigenen
                    Server geladen. Es besteht dabei keine Verbindung zu Servern
                    Dritter.
                </p>
            </section>

            <section aria-labelledby="privacy-recipients">
                <h2 id="privacy-recipients">
                    10. Empfänger und automatisierte Entscheidungen
                </h2>
                <p>
                    Empfänger personenbezogener Daten sind nur der
                    Hosting-Anbieter, der Mailanbieter und – beim Rezept-Import
                    – Google, jeweils im oben beschriebenen Umfang. Eine
                    automatisierte Entscheidungsfindung einschließlich Profiling
                    findet nicht statt.
                </p>
            </section>

            <section aria-labelledby="privacy-rights">
                <h2 id="privacy-rights">11. Ihre Rechte</h2>
                <p>Sie haben das Recht auf</p>
                <ul>
                    <li>Auskunft über Ihre Daten (Art. 15 DSGVO),</li>
                    <li>Berichtigung (Art. 16 DSGVO),</li>
                    <li>Löschung (Art. 17 DSGVO),</li>
                    <li>Einschränkung der Verarbeitung (Art. 18 DSGVO),</li>
                    <li>Datenübertragbarkeit (Art. 20 DSGVO),</li>
                    <li>
                        Widerspruch gegen Verarbeitungen auf Grundlage von Art.
                        6 Abs. 1 lit. f DSGVO (Art. 21 DSGVO),
                    </li>
                    <li>
                        Beschwerde bei einer Datenschutz-Aufsichtsbehörde (Art.
                        77 DSGVO).
                    </li>
                </ul>
                <p>
                    Für Anfragen genügt eine E-Mail an die oben genannte
                    Adresse.
                </p>
            </section>

            <p class="legal-page__updated">
                Stand: <LegalValue :value="legal.last_updated" />
            </p>
        </article>
    </AppLayout>
</template>

<script setup lang="ts">
// Slice 4 (F-10): ENTWURF einer Datenschutzerklaerung, keine Rechtsberatung.
// Beschreibt nur Verarbeitungen, die im Code belegt sind; Belege und
// Pruefstellen: docs/modernization/RECHTSTEXTE.md
import AppLayout from '@/components/layout/AppLayout.vue';
import { defineComponent, h, type PropType } from 'vue';

interface LegalInfo {
    operator_name: string | null;
    operator_street: string | null;
    operator_postal_city: string | null;
    operator_country: string | null;
    operator_email: string | null;
    operator_phone: string | null;
    hosting_provider_name: string | null;
    hosting_provider_address: string | null;
    server_log_retention: string | null;
    mail_provider: string | null;
    last_updated: string | null;
    filled: boolean;
}

interface SessionInfo {
    cookie: string;
    lifetime_minutes: number;
    database: boolean;
}

defineProps<{ legal: LegalInfo; session: SessionInfo }>();

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
