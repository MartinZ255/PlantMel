import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

/** Geteilter Nutzer aus HandleInertiaRequests::share (nur id, name, email). */
export interface SharedUser {
    id: number;
    name: string;
    email: string;
    /** wird derzeit NICHT geteilt (Profil-Hinweis zur Verifizierung erscheint daher nie, vgl. F-08) */
    email_verified_at?: string | null;
}

export interface SharedCollection {
    id: number | string;
    name: string;
}

export interface Auth {
    user: SharedUser | null;
    collections: SharedCollection[];
}

/** F-17: Meldungen aus `->with('success'|'error', …)`; null, wenn nichts gesetzt ist. */
export interface Flash {
    success: string | null;
    error: string | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    flash: Flash;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
