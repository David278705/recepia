<script setup>
import { ref } from 'vue';
import ProfileTab from '../components/settings/ProfileTab.vue';
import ServicesTab from '../components/settings/ServicesTab.vue';
import HoursTab from '../components/settings/HoursTab.vue';
import FaqsTab from '../components/settings/FaqsTab.vue';
import BotTestTab from '../components/settings/BotTestTab.vue';
import AccountTab from '../components/settings/AccountTab.vue';

const tabs = [
    { key: 'profile', label: 'Perfil del negocio', component: ProfileTab },
    { key: 'services', label: 'Servicios', component: ServicesTab },
    { key: 'hours', label: 'Horarios', component: HoursTab },
    { key: 'faqs', label: 'Preguntas frecuentes', component: FaqsTab },
    { key: 'bot-test', label: 'Probar a Pilo', component: BotTestTab },
    { key: 'account', label: 'Cuenta', component: AccountTab },
];

const active = ref('profile');
</script>

<template>
    <div>
        <h1 class="mb-1 font-display text-2xl font-semibold text-brand-900">Configuración</h1>
        <p class="mb-6 text-sm text-sand-500">Todo lo que tu recepcionista de IA necesita saber sobre tu negocio.</p>

        <div class="mb-6 flex flex-wrap gap-1 border-b border-sand-200">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-t-lg px-4 py-2 text-sm font-medium transition"
                :class="active === tab.key
                    ? 'border-b-2 border-brand-700 text-brand-800'
                    : 'text-sand-500 hover:text-sand-700'"
                @click="active = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <component :is="tabs.find((t) => t.key === active).component" />
    </div>
</template>
