<script setup>
import BrandMark from '../components/BrandMark.vue';
import { BRAND } from '../lib/brand';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-sand-50">
        <header class="px-6 py-6">
            <router-link :to="{ name: 'landing' }" class="flex items-center gap-2.5 self-start">
                <BrandMark :size="44" />
                <span class="font-display text-xl font-semibold tracking-tight text-brand-950">{{ BRAND.wordmark }}</span>
            </router-link>
        </header>

        <main class="mx-auto flex max-w-md flex-1 flex-col items-center justify-center px-6 pb-24 text-center">
            <img :src="'/img/robot_pensando_duda.png'" alt="" class="w-40 select-none" draggable="false">
            <h1 class="mt-6 font-display text-3xl font-semibold tracking-tight text-brand-950">Esta página no existe</h1>
            <p class="mt-3 text-sand-600">
                Hasta {{ BRAND.name }} se quedó pensando. Revisa la dirección o vuelve al inicio, tranquilo.
            </p>
            <router-link
                :to="{ name: 'landing' }"
                class="mt-8 rounded-xl bg-brand-800 px-7 py-3 text-sm font-semibold text-white transition hover:bg-brand-900"
            >
                Volver al inicio
            </router-link>
        </main>
    </div>
</template>
