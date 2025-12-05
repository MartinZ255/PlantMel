<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import { usePage, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const page = usePage();
const user = page.props.auth?.user ?? null;

</script>

<template>
    <AppLayout page-title="Rezeptübersicht">

    <header>
        <nav>
            <a :href="route('home')">Start</a>
            <h1> </h1>
            <template v-if="user">
                <span>Hallo, {{ user.name }}</span>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="btn btn-secondary"
                >
                    Logout
                </Link>
            </template>

            <template v-else>
                <a :href="route('login')">Login</a>
                <h1> </h1>
                <a :href="route('register')">Registrieren</a>
            </template>
        </nav>
    </header>

    </AppLayout>
</template>
