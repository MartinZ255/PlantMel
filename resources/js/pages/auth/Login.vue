<script setup lang="ts">
import type { AppPageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

defineProps<{
    canResetPassword: boolean;
    canRegister: boolean;
    status: string | null;
}>();

// z. B. "Sitzung abgelaufen" nach 419 (Seite ohne AppLayout)
const page = usePage<AppPageProps>();
const flashError = computed(() => page.props.flash?.error ?? null);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Anmelden" />

    <div class="auth-container">
        <Link :href="route('landing')" class="auth-brand">PlantMel</Link>

        <p
            v-if="flashError"
            class="flash-message flash-message--error"
            role="alert"
        >
            {{ flashError }}
        </p>

        <p v-if="status" class="status-message" role="status">
            {{ status }}
        </p>

        <form @submit.prevent="submit" class="auth-form">
            <h1 class="auth-form-title">Login</h1>
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
                    autocomplete="current-password"
                    required
                />
                <div v-if="form.errors.password" class="error">
                    {{ form.errors.password }}
                </div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" v-model="form.remember" />
                    Angemeldet bleiben
                </label>
            </div>

            <button type="submit" :disabled="form.processing">Einloggen</button>

            <div class="auth-links">
                <Link v-if="canResetPassword" :href="route('password.request')">
                    Passwort vergessen?
                </Link>

                <Link v-if="canRegister" :href="route('register')">
                    Noch kein Konto? Registrieren
                </Link>
            </div>
        </form>
    </div>
</template>
