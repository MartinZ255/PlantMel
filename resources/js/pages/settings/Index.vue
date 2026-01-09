<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import { route } from 'ziggy-js';
</script>

<template>
    <AppLayout page-title="Einstellungen">
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">
                    Einstellungen
                </div>
                <div class="section-header__meta">
                    Verwalte dein Konto und deine Präferenzen
                </div>
            </div>

            <div class="settings-grid">
                <a
                    :href="route('profile.edit')"
                    class="settings-card"
                >
                    <div class="settings-card__icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </div>
                    <div class="settings-card__content">
                        <h3 class="settings-card__title">Profil</h3>
                        <p class="settings-card__description">
                            Name und E-Mail-Adresse ändern
                        </p>
                    </div>
                    <div class="settings-card__arrow">→</div>
                </a>

                <a
                    :href="route('user-password.edit')"
                    class="settings-card"
                >
                    <div class="settings-card__icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </div>
                    <div class="settings-card__content">
                        <h3 class="settings-card__title">Passwort</h3>
                        <p class="settings-card__description">
                            Passwort ändern oder zurücksetzen
                        </p>
                    </div>
                    <div class="settings-card__arrow">→</div>
                </a>
            </div>
        </section>
    </AppLayout>
</template>

