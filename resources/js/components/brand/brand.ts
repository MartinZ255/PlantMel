import config from './brand.config.json';

/**
 * Zentrale Markenkonfiguration (Logo-Vorbereitung, siehe docs/modernization/LOGO.md).
 *
 * Der einzige Schalter ist `brand.config.json` → `logo`:
 * - `builtin`: heutiges Signet (Keimling im Waldgrün-Blob) + Wortmarke „PlantMel“ als Text.
 * - `custom`:  Dateien aus `public/brand/` (logo-full.svg, logo-mark.svg, optional
 *              logo-full-light.svg / logo-mark-light.svg für Waldgrün-Flächen).
 *
 * Die Datei schreibt `npm run brand:apply`; von Hand ändern geht auch.
 */
export type BrandLogoMode = 'builtin' | 'custom';
export type BrandLogoVariant = 'full' | 'mark';
/** `dark` = dunkles Logo für helle Flächen, `light` = helles Logo für Waldgrün-Flächen */
export type BrandLogoTone = 'dark' | 'light';
export type BrandLogoSize = 'sm' | 'md' | 'lg';

export interface BrandConfig {
    logo: BrandLogoMode;
    /** Liegen logo-full-light.svg / logo-mark-light.svg vor? Sonst wird die dunkle Datei genutzt. */
    lightVariants: boolean;
    /** Enthält logo-full.svg den Schriftzug? Wenn nicht: Bildmarke + Text-Wortmarke. */
    fullHasWordmark: boolean;
    /** Cache-Buster für die Logo-Dateien (z. B. Datum), leer = keiner */
    version: string;
}

export const BRAND_NAME = 'PlantMel';
export const BRAND_HOME_LABEL = `${BRAND_NAME} – Startseite`;
export const BRAND_ASSET_BASE = '/brand';

export const brandConfig: BrandConfig = {
    logo: config.logo === 'custom' ? 'custom' : 'builtin',
    lightVariants: config.lightVariants === true,
    fullHasWordmark: config.fullHasWordmark !== false,
    version: typeof config.version === 'string' ? config.version : '',
};

/** Pfad der Logo-Datei in `public/brand/` für Variante und Ton */
export function brandAssetUrl(
    variant: BrandLogoVariant,
    tone: BrandLogoTone,
    cfg: BrandConfig = brandConfig,
): string {
    const base = variant === 'full' ? 'logo-full' : 'logo-mark';
    const suffix = tone === 'light' && cfg.lightVariants ? '-light' : '';
    const query = cfg.version ? `?v=${encodeURIComponent(cfg.version)}` : '';
    return `${BRAND_ASSET_BASE}/${base}${suffix}.svg${query}`;
}
