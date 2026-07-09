<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import BrandMark from '../components/BrandMark.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '' });
const errors = ref({});
const submitting = ref(false);

async function handleSubmit() {
    submitting.value = true;
    errors.value = {};
    try {
        await auth.login(form);
        if (auth.user?.role === 'super_admin') {
            router.push({ name: 'admin.businesses' });
            return;
        }
        router.push(route.query.redirect || { name: 'dashboard' });
    } catch (error) {
        errors.value = error.response?.data?.errors ?? { email: [error.response?.data?.message ?? 'Error al iniciar sesión.'] };
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="grid min-h-screen bg-white lg:grid-cols-2">
        <!-- Formulario -->
        <div class="flex flex-col px-6 py-8 sm:px-12">
            <router-link :to="{ name: 'landing' }" class="flex items-center gap-2.5 self-start">
                <BrandMark :size="30" />
                <span class="font-display text-xl font-semibold tracking-tight text-brand-950">recepia</span>
            </router-link>

            <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-12">
                <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-950">Inicia sesión</h1>
                <p class="mt-2 text-sm text-sand-500">Entra al panel de tu negocio.</p>

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

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-sand-700">Contraseña</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-lg border border-sand-300 px-3.5 py-2.5 text-sm text-sand-900 transition focus:border-brand-600 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                        >
                        <p v-if="errors.password" class="mt-1.5 text-xs text-amber-800">{{ errors.password[0] }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="mt-2 w-full rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-900 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ submitting ? 'Entrando…' : 'Entrar' }}
                    </button>
                </form>

                <p class="mt-8 text-sm text-sand-500">
                    ¿Aún no tienes cuenta?
                    <a href="mailto:hola@recepia.app?subject=Quiero%20conocer%20Recepia" class="font-medium text-brand-700 hover:underline">Escríbenos</a>
                    y te acompañamos en el alta.
                </p>
            </div>

            <router-link :to="{ name: 'landing' }" class="self-start text-sm text-sand-400 transition hover:text-sand-600">
                ← Volver al inicio
            </router-link>
        </div>

        <!-- Panel de marca -->
        <div class="relative hidden overflow-hidden bg-brand-950 lg:flex lg:flex-col lg:justify-between lg:p-14">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-40 -right-40 h-[32rem] w-[32rem] rounded-full bg-brand-800/60 blur-3xl" />
                <div class="absolute -bottom-24 -left-24 h-80 w-80 rounded-full bg-brand-900 blur-2xl" />
            </div>

            <div class="relative" />

            <blockquote class="relative max-w-md">
                <p class="font-display text-3xl leading-snug font-medium tracking-tight text-white">
                    Cada mensaje contestado a tiempo es un cliente que no se fue a otro lado.
                </p>
                <div class="mt-10 rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
                    <div class="flex items-start gap-3">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-400" />
                        <div>
                            <p class="text-sm font-medium text-white">Cita confirmada · 3:00 pm</p>
                            <p class="mt-1 text-sm text-brand-100/70">Recepia atendió, propuso horarios y agendó — mientras el dueño trabajaba.</p>
                        </div>
                    </div>
                </div>
            </blockquote>

            <div class="relative flex items-center gap-2.5">
                <BrandMark :size="26" variant="inverse" />
                <span class="font-display text-lg font-semibold tracking-tight text-white">recepia</span>
            </div>
        </div>
    </div>
</template>
