<script setup>
import { reactive, ref } from 'vue';
import api, { ensureCsrfCookie } from '../lib/api';
import BrandMark from '../components/BrandMark.vue';

const form = reactive({ email: '' });
const errors = ref({});
const message = ref('');
const submitting = ref(false);

async function handleSubmit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';
    try {
        await ensureCsrfCookie();
        const { data } = await api.post('/forgot-password', form);
        message.value = data.message;
    } catch (error) {
        errors.value = error.response?.data?.errors ?? { email: [error.response?.data?.message ?? 'No pudimos enviar el enlace. Intenta de nuevo.'] };
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
            <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-950">¿Olvidaste tu contraseña?</h1>
            <p class="mt-2 text-sm text-sand-500">Escribe tu correo y te enviamos un enlace para restablecerla.</p>

            <form class="mt-8 flex flex-col gap-5" @submit.prevent="handleSubmit">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-sand-700">Correo</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full rounded-lg border border-sand-300 px-3.5 py-2.5 text-sm text-sand-900 placeholder-sand-400 transition focus:border-brand-600 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                    >
                    <p v-if="errors.email" class="mt-1.5 text-xs text-amber-800">{{ errors.email[0] }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="submitting"
                    class="mt-2 w-full rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-900 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ submitting ? 'Enviando…' : 'Enviar enlace' }}
                </button>

                <p v-if="message" class="rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800">{{ message }}</p>
            </form>

            <p class="mt-8 text-sm text-sand-500">
                <router-link :to="{ name: 'login' }" class="font-medium text-brand-700 hover:underline">← Volver a iniciar sesión</router-link>
            </p>
        </div>
    </div>
</template>
