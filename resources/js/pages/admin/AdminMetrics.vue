<script setup>
import { onMounted, ref } from 'vue';
import { Squares2X2Icon, ChatBubbleLeftRightIcon, CpuChipIcon, BanknotesIcon, CalendarDaysIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import api from '../../lib/api';
import Card from '../../components/Card.vue';
import Badge from '../../components/Badge.vue';
import Spinner from '../../components/Spinner.vue';

const loading = ref(true);
const metrics = ref(null);

const money = (value) => '$' + Number(value ?? 0).toFixed(2) + ' USD';
const num = (value) => Number(value ?? 0).toLocaleString('es-CO');

onMounted(async () => {
    const { data } = await api.get('/admin/metrics');
    metrics.value = data.data;
    loading.value = false;
});
</script>

<template>
    <div>
        <h1 class="mb-1 font-display text-2xl font-semibold text-brand-900">Métricas y costos</h1>
        <p class="mb-6 text-sm text-sand-500">Actividad de la plataforma y costo estimado de Claude por negocio, del mes en curso.</p>

        <Spinner v-if="loading">Cargando métricas…</Spinner>

        <div v-else class="flex flex-col gap-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <Squares2X2Icon class="h-4 w-4" /> Negocios activos
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ metrics.active_businesses }} / {{ metrics.total_businesses }}</p>
                </Card>
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <ChatBubbleLeftRightIcon class="h-4 w-4" /> Conversaciones (mes)
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ num(metrics.conversations_this_month) }}</p>
                </Card>
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <CpuChipIcon class="h-4 w-4" /> Tokens de Claude (mes)
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ num(metrics.tokens_this_month) }}</p>
                </Card>
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <BanknotesIcon class="h-4 w-4" /> Costo estimado (mes)
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ money(metrics.estimated_cost_this_month) }}</p>
                </Card>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <CalendarDaysIcon class="h-4 w-4" /> Citas agendadas por Pilo (mes)
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ num(metrics.bot_appointments_this_month) }}</p>
                </Card>
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <ExclamationTriangleIcon class="h-4 w-4" /> Escalaciones sin resolver
                    </div>
                    <p class="mt-2 font-mono text-3xl" :class="metrics.pending_escalations > 0 ? 'text-amber-700' : 'text-brand-800'">
                        {{ num(metrics.pending_escalations) }}
                    </p>
                </Card>
            </div>

            <Card>
                <h3 class="mb-3 font-display text-sm font-semibold text-brand-900">Detalle por negocio — mes en curso</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs tracking-wide text-sand-500 uppercase">
                                <th class="pb-2 pr-4">Negocio</th>
                                <th class="pb-2 pr-4">Estado</th>
                                <th class="pb-2 pr-4 text-right">Conversaciones</th>
                                <th class="pb-2 pr-4 text-right">Respuestas de Pilo</th>
                                <th class="pb-2 pr-4 text-right">Tokens</th>
                                <th class="pb-2 pr-4 text-right">Costo est.</th>
                                <th class="pb-2 pr-4 text-right">Citas de Pilo</th>
                                <th class="pb-2 text-right">Escaladas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="business in metrics.businesses" :key="business.id" class="border-t border-sand-100">
                                <td class="py-2 pr-4 font-medium text-sand-800">{{ business.name }}</td>
                                <td class="py-2 pr-4"><Badge :tone="business.status === 'activo' ? 'brand' : 'sand'">{{ business.status }}</Badge></td>
                                <td class="py-2 pr-4 text-right font-mono text-sand-700">{{ num(business.conversations_this_month) }}</td>
                                <td class="py-2 pr-4 text-right font-mono text-sand-700">{{ num(business.bot_messages_this_month) }}</td>
                                <td class="py-2 pr-4 text-right font-mono text-sand-700">{{ num(business.tokens_this_month) }}</td>
                                <td class="py-2 pr-4 text-right font-mono text-sand-700">{{ money(business.estimated_cost_this_month) }}</td>
                                <td class="py-2 pr-4 text-right font-mono text-sand-700">{{ num(business.bot_appointments_this_month) }}</td>
                                <td class="py-2 text-right font-mono" :class="business.pending_escalations > 0 ? 'text-amber-700' : 'text-sand-700'">
                                    {{ num(business.pending_escalations) }}
                                </td>
                            </tr>
                            <tr v-if="!metrics.businesses.length"><td colspan="8" class="py-4 text-sand-500">No hay negocios registrados.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-sand-400">
                    El costo es una estimación calculada con los tokens de cada respuesta de Pilo y la tarifa configurada del modelo (config/pilo.php).
                </p>
            </Card>
        </div>
    </div>
</template>
