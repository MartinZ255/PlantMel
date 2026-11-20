import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Gate C2: Host-Recht aus der global geteilten Prop `auth.user.is_host`
 * (HandleInertiaRequests). Damit zeigen Kopfnavigation, Mobilmenü und
 * Nutzermenü „Rezept erstellen“ auf JEDER Seite für Hosts und nie für
 * normale Nutzer oder Gäste. Die seitenbezogene Prop `isHost` der
 * Rezeptseiten wird für die Shell nicht mehr verwendet.
 *
 * Nur Anzeige: Die Routen schützt weiterhin die Middleware `is_host`.
 */
export function useIsHost() {
    const page = usePage<AppPageProps>();
    return computed(() => page.props.auth?.user?.is_host === true);
}
