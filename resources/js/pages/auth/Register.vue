<script setup lang="ts">
import AppFooter from '@/components/layout/AppFooter.vue';
import type { AppPageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const page = usePage<AppPageProps>();
const flashError = computed(() => page.props.flash?.error ?? null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Registrieren" />

    <div class="auth-container">
        <Link :href="route('landing')" class="auth-brand">PlantMel</Link>

        <p
            v-if="flashError"
            class="flash-message flash-message--error"
            role="alert"
        >
            {{ flashError }}
        </p>

        <form @submit.prevent="submit" class="auth-form">
            <h1 class="auth-form-title">Registrieren</h1>
            <div class="form-group">
                <label for="name">Name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    required
                />
                <div v-if="form.errors.name" class="error">
                    {{ form.errors.name }}
                </div>
            </div>

            <div class="form-group">
                <label for="email">E-Mail</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                />
                <div v-if="form.errors.email" class="error">
                    {{ form.errors.email }}
                </div>
            </div>

            <div class="form-group">
                <label for="password">Passwort</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                />
                <div v-if="form.errors.password" class="error">
                    {{ form.errors.password }}
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Passwort bestätigen</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
                <div v-if="form.errors.password_confirmation" class="error">
                    {{ form.errors.password_confirmation }}
                </div>
            </div>

            <!-- Slice 4 (F-10): Informationspflicht, keine Einwilligung (keine Checkbox) -->
            <p class="auth-privacy-note">
                Mit der Registrierung gilt unsere
                <a :href="route('legal.privacy')">Datenschutzerklärung</a>.
            </p>

            <button type="submit" :disabled="form.processing">
                Konto erstellen
            </button>

            <div class="auth-links">
                <a :href="route('login')"> Bereits registriert? Zum Login </a>
            </div>
        </form>

        <AppFooter variant="auth" />
    </div>
</template>
