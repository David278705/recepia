<script setup>
import { onMounted, ref } from 'vue';
import { QueueListIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import api from '../../lib/api';
import Card from '../../components/Card.vue';
import Badge from '../../components/Badge.vue';
import Spinner from '../../components/Spinner.vue';

const loading = ref(true);
const businesses = ref([]);
const failedJobs = ref([]);
const queue = ref(null);

const connectionTone = (status) => ({ conectado: 'brand', error: 'urgent', pendiente: 'amber' }[status] ?? 'sand');

const relativeTime = (iso) => {
    if (!iso) return 'Sin actividad';
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (minutes < 1) return 'hace un momento';
    if (minutes < 60) return `hace ${minutes} min`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `hace ${hours} h`;
    return new Date(iso).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
};

onMounted(async () => {
    const { data } = await api.get('/admin/system-health');
    businesses.value = data.data.businesses;
    failedJobs.value = data.data.failed_jobs;
    queue.value = data.data.queue;
    loading.value = false;
});
</script>

<template>
    <div>
        <h1 class="mb-1 font-display text-2xl font-semibold text-brand-900">Salud del sistema</h1>
        <p class="mb-6 text-sm text-sand-500">Estado de la cola, la conexión de WhatsApp de cada negocio y los jobs fallidos.</p>

        <Spinner v-if="loading">Cargando…</Spinner>

        <div v-else class="flex flex-col gap-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <QueueListIcon class="h-4 w-4" /> Jobs en cola
                    </div>
                    <p class="mt-2 font-mono text-3xl text-brand-800">{{ queue.pending_jobs }}</p>
                    <p class="mt-1 text-xs text-sand-400">Si este número crece sin bajar, el worker de la cola no está corriendo.</p>
                </Card>
                <Card>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <ExclamationTriangleIcon class="h-4 w-4" /> Jobs fallidos (total)
                    </div>
                    <p class="mt-2 font-mono text-3xl" :class="queue.failed_jobs_total > 0 ? 'text-amber-700' : 'text-brand-800'">
                        {{ queue.failed_jobs_total }}
                    </p>
                    <p class="mt-1 text-xs text-sand-400">Reintenta con <span class="font-mono">php artisan queue:retry all</span>.</p>
                </Card>
            </div>

            <Card>
                <h3 class="mb-3 font-display text-sm font-semibold text-brand-900">Negocios</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs tracking-wide text-sand-500 uppercase">
                                <th class="pb-2 pr-4">Negocio</th>
                                <th class="pb-2 pr-4">Estado</th>
                                <th class="pb-2 pr-4">WhatsApp</th>
                                <th class="pb-2 pr-4">Conexión</th>
                                <th class="pb-2 pr-4">Última actividad</th>
                                <th class="pb-2 text-right">Escaladas pendientes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="business in businesses" :key="business.id" class="border-t border-sand-100">
                                <td class="py-2 pr-4 font-medium text-sand-800">{{ business.name }}</td>
                                <td class="py-2 pr-4"><Badge :tone="business.status === 'activo' ? 'brand' : 'sand'">{{ business.status }}</Badge></td>
                                <td class="py-2 pr-4 font-mono text-xs text-sand-600">
                                    <template v-if="business.whatsapp_phone">{{ business.whatsapp_phone }} <span class="text-sand-400">({{ business.whatsapp_mode }})</span></template>
                                    <span v-else class="font-sans text-sand-400">Sin conectar</span>
                                </td>
                                <td class="py-2 pr-4">
                                    <Badge :tone="connectionTone(business.whatsapp_connection)">{{ business.whatsapp_connection || 'sin cuenta' }}</Badge>
                                </td>
                                <td class="py-2 pr-4 text-sand-500">{{ relativeTime(business.last_activity_at) }}</td>
                                <td class="py-2 text-right font-mono" :class="business.pending_escalations > 0 ? 'text-amber-700' : 'text-sand-700'">
                                    {{ business.pending_escalations }}
                                </td>
                            </tr>
                            <tr v-if="!businesses.length"><td colspan="6" class="py-4 text-sand-500">No hay negocios registrados.</td></tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card>
                <h3 class="mb-3 font-display text-sm font-semibold text-brand-900">Jobs fallidos recientes</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs tracking-wide text-sand-500 uppercase">
                                <th class="pb-2 pr-4">Cuándo</th>
                                <th class="pb-2 pr-4">Cola</th>
                                <th class="pb-2 pr-4">Excepción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="job in failedJobs" :key="job.id" class="border-t border-sand-100 align-top">
                                <td class="py-2 pr-4 whitespace-nowrap text-sand-500">{{ new Date(job.failed_at).toLocaleString('es-CO') }}</td>
                                <td class="py-2 pr-4 text-sand-500">{{ job.queue }}</td>
                                <td class="py-2 pr-4 font-mono text-xs text-sand-500">{{ job.exception.slice(0, 200) }}</td>
                            </tr>
                            <tr v-if="!failedJobs.length"><td colspan="3" class="py-4 text-sand-500">Sin fallos recientes.</td></tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </div>
</template>
