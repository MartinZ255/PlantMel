<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    // is_host kannst du vorerst auf false lassen oder optionales Feld machen
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
        <form @submit.prevent="submit" class="auth-form">
            <h1 class="auth-form-title">Registrieren</h1>
            <div class="form-group">
                <label for="name">Name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
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
                    required
                />
            </div>

            <button type="submit" :disabled="form.processing">
                Konto erstellen
            </button>

            <div class="auth-links">
                <a :href="route('login')">
                    Bereits registriert? Zum Login
                </a>
            </div>
        </form>
    </div>
</template>
