<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Bars3Icon, XMarkIcon, ArrowRightStartOnRectangleIcon } from '@heroicons/vue/24/outline';
import { useAuthStore } from '../stores/auth';
import BrandMark from './BrandMark.vue';

const props = defineProps({
    navItems: { type: Array, required: true },
    homeRoute: { type: String, required: true },
    brandSuffix: { type: String, default: '' },
});

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const mobileOpen = ref(false);

const initials = computed(() => {
    const name = auth.user?.name ?? '';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');
});

watch(() => route.fullPath, () => {
    mobileOpen.value = false;
});

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}

async function stopImpersonating() {
    await auth.stopImpersonating();
    router.push({ name: 'admin.businesses' });
}
</script>

<template>
    <div class="flex min-h-screen bg-sand-50">
        <Transition name="slideover-backdrop">
            <div v-if="mobileOpen" class="fixed inset-0 z-30 bg-sand-950/40 md:hidden" @click="mobileOpen = false" />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 -translate-x-full flex-col border-r border-sand-200 bg-white transition-transform duration-300 ease-in-out md:relative md:z-auto md:translate-x-0"
            :class="{ 'translate-x-0': mobileOpen }"
        >
            <div class="flex items-center justify-between px-5 py-5">
                <router-link :to="{ name: props.homeRoute }" class="flex items-center gap-2.5">
                    <BrandMark :size="44" />
                    <span class="font-display text-xl font-semibold tracking-tight text-brand-950">recepia</span>
                    <span
                        v-if="brandSuffix"
                        class="rounded-md bg-sand-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wider text-sand-500 uppercase"
                    >{{ brandSuffix }}</span>
                </router-link>
                <button type="button" aria-label="Cerrar menú" class="text-sand-400 md:hidden" @click="mobileOpen = false">
                    <XMarkIcon class="h-6 w-6" />
                </button>
            </div>

            <nav class="flex flex-1 flex-col gap-0.5 px-3 pt-2">
                <router-link
                    v-for="item in navItems"
                    :key="item.name"
                    :to="{ name: item.name }"
                    class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-sand-600 transition hover:bg-sand-100 hover:text-sand-900"
                    active-class="!bg-brand-50 !text-brand-800"
                >
                    <component :is="item.icon" v-if="item.icon" class="h-5 w-5 shrink-0" />
                    {{ item.label }}
                </router-link>
            </nav>

            <div class="border-t border-sand-100 px-4 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800">
                        {{ initials }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-sand-800">{{ auth.user?.name }}</p>
                        <p class="truncate text-xs text-sand-400">{{ auth.user?.email }}</p>
                    </div>
                    <button
                        type="button"
                        aria-label="Cerrar sesión"
                        title="Cerrar sesión"
                        class="rounded-lg p-2 text-sand-400 transition hover:bg-sand-100 hover:text-sand-700"
                        @click="handleLogout"
                    >
                        <ArrowRightStartOnRectangleIcon class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex items-center gap-3 border-b border-sand-200 bg-white px-4 py-3 md:hidden">
                <button type="button" aria-label="Abrir menú" class="text-brand-900" @click="mobileOpen = true">
                    <Bars3Icon class="h-6 w-6" />
                </button>
                <span class="flex items-center gap-2">
                    <BrandMark :size="36" />
                    <span class="font-display text-lg font-semibold tracking-tight text-brand-950">recepia</span>
                </span>
            </div>

            <div
                v-if="auth.isImpersonating"
                class="flex items-center justify-between border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 sm:px-8"
            >
                <span>Estás viendo el panel como <strong>{{ auth.user?.name }}</strong>.</span>
                <button
                    type="button"
                    class="rounded-lg border border-amber-300 px-3 py-1 text-xs font-semibold text-amber-900 transition hover:bg-amber-100"
                    @click="stopImpersonating"
                >
                    Volver a admin
                </button>
            </div>

            <main class="flex-1 px-4 py-6 sm:px-8 sm:py-8">
                <slot />
            </main>
        </div>
    </div>
</template>
