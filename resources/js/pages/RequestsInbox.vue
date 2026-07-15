<script setup>
import { onMounted, ref, watch } from 'vue';
import api from '../lib/api';
import Card from '../components/Card.vue';
import Spinner from '../components/Spinner.vue';

const TYPE_LABELS = { pedido: 'Pedido', cotizacion: 'Cotización' };
const STATUS_STYLES = {
    nueva: 'bg-amber-100 text-amber-800',
    atendida: 'bg-brand-100 text-brand-800',
    cerrada: 'bg-sand-200 text-sand-600',
};

const loading = ref(true);
const requests = ref([]);
const statusFilter = ref('');
const typeFilter = ref('');
const updating = ref(null);

onMounted(load);
watch([statusFilter, typeFilter], load);

async function load() {
    loading.value = true;
    const { data } = await api.get('/customer-requests', {
        params: {
            ...(statusFilter.value ? { status: statusFilter.value } : {}),
            ...(typeFilter.value ? { type: typeFilter.value } : {}),
        },
    });
    requests.value = data.data;
    loading.value = false;
}

async function setStatus(request, status) {
    updating.value = request.id;
    try {
        const { data } = await api.put(`/customer-requests/${request.id}/status`, { status });
        Object.assign(request, data.data);
    } finally {
        updating.value = null;
    }
}

function fmt(dt) {
    return new Date(dt).toLocaleString('es-CO', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
}

function entregaLabel(payload) {
    if (!payload?.entrega) return null;
    return payload.entrega === 'domicilio'
        ? `Domicilio${payload.direccion ? ` — ${payload.direccion}` : ''}`
        : 'Recoge en el negocio';
}
</script>

<template>
    <div>
        <h1 class="mb-1 font-display text-2xl font-semibold text-brand-900">Solicitudes</h1>
        <p class="mb-6 text-sm text-sand-500">Pedidos y cotizaciones que tu recepcionista capturó por WhatsApp.</p>

        <div class="mb-4 flex flex-wrap gap-3">
            <select v-model="statusFilter" class="rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                <option value="">Todos los estados</option>
                <option value="nueva">Nuevas</option>
                <option value="atendida">Atendidas</option>
                <option value="cerrada">Cerradas</option>
            </select>
            <select v-model="typeFilter" class="rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                <option value="">Todos los tipos</option>
                <option value="pedido">Pedidos</option>
                <option value="cotizacion">Cotizaciones</option>
            </select>
        </div>

        <Spinner v-if="loading">Cargando…</Spinner>

        <div v-else-if="requests.length" class="flex max-w-3xl flex-col gap-3">
            <Card v-for="req in requests" :key="req.id">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-brand-900">
                            {{ TYPE_LABELS[req.type] ?? req.type }} · {{ req.contact?.name ?? req.contact?.wa_id }}
                        </p>
                        <p class="mt-0.5 text-xs text-sand-400">{{ fmt(req.created_at) }} · {{ req.contact?.wa_id }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-medium capitalize" :class="STATUS_STYLES[req.status]">{{ req.status }}</span>
                </div>

                <div class="mt-3 rounded-xl bg-sand-50 p-4 text-sm text-sand-700">
                    <!-- Pedido -->
                    <template v-if="req.type === 'pedido'">
                        <ul class="flex flex-col gap-1">
                            <li v-for="(item, i) in req.payload?.items ?? []" :key="i">
                                {{ item.cantidad }} × {{ item.nombre }}<span v-if="item.nota" class="text-sand-500"> — {{ item.nota }}</span>
                            </li>
                        </ul>
                        <p v-if="entregaLabel(req.payload)" class="mt-2 text-xs text-sand-500">{{ entregaLabel(req.payload) }}</p>
                    </template>
                    <!-- Cotización -->
                    <template v-else>
                        <p class="font-medium">{{ req.payload?.resumen }}</p>
                        <p v-if="req.payload?.detalles" class="mt-1 text-sand-600">{{ req.payload.detalles }}</p>
                    </template>
                    <p v-if="req.payload?.nota" class="mt-2 text-xs text-sand-500">Nota: {{ req.payload.nota }}</p>
                </div>

                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                    <button
                        v-if="req.status === 'nueva'"
                        type="button"
                        :disabled="updating === req.id"
                        class="font-medium text-brand-700 hover:underline"
                        @click="setStatus(req, 'atendida')"
                    >
                        Marcar atendida
                    </button>
                    <button
                        v-if="req.status !== 'cerrada'"
                        type="button"
                        :disabled="updating === req.id"
                        class="font-medium text-sand-500 hover:text-sand-700"
                        @click="setStatus(req, 'cerrada')"
                    >
                        Cerrar
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="updating === req.id"
                        class="font-medium text-sand-500 hover:text-sand-700"
                        @click="setStatus(req, 'nueva')"
                    >
                        Reabrir
                    </button>
                </div>
            </Card>
        </div>

        <Card v-else class="max-w-3xl text-center">
            <p class="text-sm text-sand-500">
                Aún no hay solicitudes. Cuando tu recepcionista tome un pedido o registre una cotización, aparecerá aquí.
            </p>
        </Card>
    </div>
</template>
