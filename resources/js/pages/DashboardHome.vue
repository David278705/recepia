<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    ChatBubbleLeftRightIcon,
    CalendarDaysIcon,
    CalendarIcon,
    ExclamationTriangleIcon,
    BuildingStorefrontIcon,
    SparklesIcon,
    UsersIcon,
    BoltIcon,
} from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import api from '../lib/api';
import Card from '../components/Card.vue';
import Badge from '../components/Badge.vue';
import Spinner from '../components/Spinner.vue';
import EmptyState from '../components/EmptyState.vue';
import BarChart from '../components/BarChart.vue';

const router = useRouter();
const loading = ref(true);
const notConfigured = ref(false);
const business = ref(null);
const stats = ref(null);

const statusLabels = {
    bot_activo: { text: 'Bot activo', tone: 'brand' },
    escalada: { text: 'Escalada', tone: 'urgent' },
    cerrada: { text: 'Cerrada', tone: 'sand' },
    propuesta: { text: 'Propuesta', tone: 'amber' },
    confirmada: { text: 'Confirmada', tone: 'brand' },
};

const activityItems = computed(() =>
    (stats.value?.activity_7d ?? []).map((d) => ({
        label: d.label,
        value: d.conversations,
        hint: new Date(d.date + 'T12:00:00').toLocaleDateString('es-CO', { day: 'numeric', month: 'short' }),
    }))
);

const relativeTime = (iso) => {
    if (!iso) return '';
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (minutes < 1) return 'hace un momento';
    if (minutes < 60) return `hace ${minutes} min`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `hace ${hours} h`;
    return new Date(iso).toLocaleDateString('es-CO', { day: 'numeric', month: 'short' });
};

onMounted(async () => {
    try {
        const [businessRes, dashboardRes] = await Promise.all([
            api.get('/business'),
            api.get('/dashboard'),
        ]);
        business.value = businessRes.data.data;
        stats.value = dashboardRes.data.data;
    } catch (error) {
        if (error.response?.status === 404) notConfigured.value = true;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <Spinner v-if="loading">Cargando tu panel…</Spinner>

        <EmptyState
            v-else-if="notConfigured"
            :icon="BuildingStorefrontIcon"
            title="Tu negocio está siendo configurado"
            description="Te contactaremos pronto para activar tu recepcionista de WhatsApp. Si crees que esto es un error, escríbenos."
        />

        <div v-else>
            <h1 class="mb-1 font-display text-2xl font-semibold text-brand-900">{{ business.name }}</h1>
            <p class="mb-6 text-sm text-sand-500">
                Resumen de un vistazo: cómo está atendiendo tu recepcionista de IA hoy.
            </p>

            <button
                v-if="stats.pending_escalations > 0"
                type="button"
                class="mb-4 flex w-full items-center justify-between rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-left transition hover:bg-amber-100"
                @click="router.push({ name: 'conversations', query: { status: 'escalada' } })"
            >
                <span class="flex items-center gap-2 font-medium text-amber-800">
                    <ExclamationTriangleIcon class="h-5 w-5" />
                    {{ stats.pending_escalations }} conversación{{ stats.pending_escalations === 1 ? '' : 'es' }} esperando tu atención
                </span>
                <span class="text-sm text-amber-700 underline">Ver bandeja →</span>
            </button>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <ChatBubbleLeftRightIcon class="h-4 w-4" /> Conversaciones hoy
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ stats.conversations_today }}</p>
                </Card>
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <CalendarDaysIcon class="h-4 w-4" /> Citas hoy
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ stats.appointments_today }}</p>
                </Card>
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <CalendarIcon class="h-4 w-4" /> Citas mañana
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ stats.appointments_tomorrow }}</p>
                </Card>
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <SparklesIcon class="h-4 w-4" /> Citas del bot (mes)
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ stats.appointments_booked_by_bot_this_month }}</p>
                </Card>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <div class="mb-4 flex items-baseline justify-between">
                        <h3 class="font-display text-sm font-semibold text-brand-900">Conversaciones por día — últimos 7 días</h3>
                        <span class="flex items-center gap-4 text-xs text-sand-500">
                            <span class="flex items-center gap-1.5"><UsersIcon class="h-4 w-4" /> {{ stats.total_contacts }} contactos</span>
                            <span class="flex items-center gap-1.5"><BoltIcon class="h-4 w-4" /> {{ stats.bot_messages_this_month }} respuestas del bot este mes</span>
                        </span>
                    </div>
                    <BarChart :items="activityItems" unit="conversaciones" />
                </Card>

                <Card>
                    <div class="mb-3 flex items-baseline justify-between">
                        <h3 class="font-display text-sm font-semibold text-brand-900">Agenda de hoy</h3>
                        <button type="button" class="text-xs text-brand-600 underline" @click="router.push({ name: 'appointments' })">
                            Ver calendario
                        </button>
                    </div>
                    <p v-if="!stats.todays_appointments.length" class="py-4 text-sm text-sand-500">
                        No hay citas programadas para hoy.
                    </p>
                    <ul v-else class="flex flex-col divide-y divide-sand-100">
                        <li v-for="appt in stats.todays_appointments" :key="appt.id" class="flex items-center gap-3 py-2">
                            <span class="w-12 shrink-0 font-mono text-sm text-brand-800">{{ appt.time }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-sand-800">{{ appt.contact || 'Sin nombre' }}</span>
                                <span class="block truncate text-xs text-sand-500">{{ appt.service }}<template v-if="appt.origin === 'bot'"> · agendada por el bot</template></span>
                            </span>
                            <Badge :tone="statusLabels[appt.status]?.tone ?? 'sand'">{{ statusLabels[appt.status]?.text ?? appt.status }}</Badge>
                        </li>
                    </ul>
                </Card>
            </div>

            <Card class="mt-4">
                <div class="mb-3 flex items-baseline justify-between">
                    <h3 class="font-display text-sm font-semibold text-brand-900">Conversaciones recientes</h3>
                    <button type="button" class="text-xs text-brand-600 underline" @click="router.push({ name: 'conversations' })">
                        Ir a la bandeja
                    </button>
                </div>
                <p v-if="!stats.recent_conversations.length" class="py-4 text-sm text-sand-500">
                    Aún no hay conversaciones. Cuando tus clientes te escriban por WhatsApp, aparecerán aquí.
                </p>
                <ul v-else class="flex flex-col divide-y divide-sand-100">
                    <li
                        v-for="conv in stats.recent_conversations"
                        :key="conv.id"
                        class="flex cursor-pointer items-center gap-3 py-2.5 transition hover:bg-sand-50"
                        @click="router.push({ name: 'conversations', query: conv.status === 'escalada' ? { status: 'escalada' } : {} })"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-sand-800">{{ conv.contact || 'Sin nombre' }}</span>
                            <span class="block truncate text-xs text-sand-500">{{ conv.snippet || 'Sin mensajes' }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-sand-400">{{ relativeTime(conv.last_activity_at) }}</span>
                        <Badge :tone="statusLabels[conv.status]?.tone ?? 'sand'">{{ statusLabels[conv.status]?.text ?? conv.status }}</Badge>
                    </li>
                </ul>
            </Card>
        </div>
    </div>
</template>
