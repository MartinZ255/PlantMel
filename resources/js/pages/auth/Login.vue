<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps<{
    canResetPassword: boolean;
    canRegister: boolean;
    status: string | null;
}>();

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
    <Head title="Login" />

    <div class="auth-container">

        <p v-if="status" class="status-message">
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

            <button type="submit" :disabled="form.processing">
                Einloggen
            </button>

            <div class="auth-links">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                >
                    Passwort vergessen?
                </Link>

                <Link
                    v-if="canRegister"
                    :href="route('register')"
                >
                    Noch kein Konto? Registrieren
                </Link>
            </div>
        </form>
    </div>
</template>
