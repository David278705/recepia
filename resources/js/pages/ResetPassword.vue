<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api, { ensureCsrfCookie } from '../lib/api';
import BrandMark from '../components/BrandMark.vue';

const route = useRoute();
const router = useRouter();

const form = reactive({
    token: route.query.token ?? '',
    email: route.query.email ?? '',
    password: '',
    password_confirmation: '',
});
const errors = ref({});
const submitting = ref(false);

async function handleSubmit() {
    submitting.value = true;
    errors.value = {};
    try {
        await ensureCsrfCookie();
        await api.post('/reset-password', form);
        router.push({ name: 'login', query: { reset: '1' } });
    } catch (error) {
        errors.value = error.response?.data?.errors ?? { email: [error.response?.data?.message ?? 'No pudimos actualizar la contraseña.'] };
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white px-6 py-8 sm:px-12">
        <router-link :to="{ name: 'landing' }" class="flex items-center gap-2.5 self-start">
            <BrandMark :size="44" />
            <span class="font-display text-xl font-semibold tracking-tight text-brand-950">recepia</span>
        </router-link>

        <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-12">
            <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-950">Nueva contraseña</h1>
            <p class="mt-2 text-sm text-sand-500">Elige una nueva contraseña para tu cuenta.</p>

            <form class="mt-8 flex flex-col gap-5" @submit.prevent="handleSubmit">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-sand-700">Correo</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full rounded-lg border border-sand-300 px-3.5 py-2.5 text-sm text-sand-900 transition focus:border-brand-600 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                    >
                    <p v-if="errors.email" class="mt-1.5 text-xs text-amber-800">{{ errors.email[0] }}</p>
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-sand-700">Nueva contraseña</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-sand-300 px-3.5 py-2.5 text-sm text-sand-900 transition focus:border-brand-600 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                    >
                    <p v-if="errors.password" class="mt-1.5 text-xs text-amber-800">{{ errors.password[0] }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-sand-700">Confirma la contraseña</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-sand-300 px-3.5 py-2.5 text-sm text-sand-900 transition focus:border-brand-600 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                    >
                </div>

                <button
                    type="submit"
                    :disabled="submitting"
                    class="mt-2 w-full rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-900 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ submitting ? 'Guardando…' : 'Guardar contraseña' }}
                </button>
            </form>

            <p class="mt-8 text-sm text-sand-500">
                <router-link :to="{ name: 'login' }" class="font-medium text-brand-700 hover:underline">← Volver a iniciar sesión</router-link>
            </p>
        </div>
    </div>
</template>
