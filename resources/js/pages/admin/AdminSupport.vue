<script setup>
import { onMounted, ref, watch } from 'vue';
import api from '../../lib/api';
import Card from '../../components/Card.vue';
import Button from '../../components/Button.vue';
import Spinner from '../../components/Spinner.vue';

const TYPE_LABELS = { error: 'Error', queja: 'Queja', sugerencia: 'Sugerencia' };
const STATUS_STYLES = {
    abierto: 'bg-amber-100 text-amber-800',
    respondido: 'bg-brand-100 text-brand-800',
    cerrado: 'bg-sand-200 text-sand-600',
};

const loading = ref(true);
const tickets = ref([]);
const statusFilter = ref('');
const typeFilter = ref('');

const selected = ref(null);
const loadingDetail = ref(false);
const replyText = ref('');
const replying = ref(false);
const closing = ref(false);

onMounted(loadTickets);
watch([statusFilter, typeFilter], loadTickets);

async function loadTickets() {
    loading.value = true;
    const { data } = await api.get('/admin/support-tickets', {
        params: {
            ...(statusFilter.value ? { status: statusFilter.value } : {}),
            ...(typeFilter.value ? { type: typeFilter.value } : {}),
        },
    });
    tickets.value = data.data;
    loading.value = false;
}

async function openTicket(ticket) {
    loadingDetail.value = true;
    selected.value = ticket;
    const { data } = await api.get(`/admin/support-tickets/${ticket.id}`);
    selected.value = data.data;
    loadingDetail.value = false;
}

async function sendReply() {
    if (!replyText.value.trim()) return;
    replying.value = true;
    try {
        const { data } = await api.post(`/admin/support-tickets/${selected.value.id}/replies`, { message: replyText.value });
        selected.value.replies.push(data.data);
        setStatusLocal('respondido');
        replyText.value = '';
    } finally {
        replying.value = false;
    }
}

async function setStatus(status) {
    closing.value = true;
    try {
        await api.put(`/admin/support-tickets/${selected.value.id}/status`, { status });
        setStatusLocal(status);
    } finally {
        closing.value = false;
    }
}

function setStatusLocal(status) {
    selected.value.status = status;
    const row = tickets.value.find((t) => t.id === selected.value.id);
    if (row) row.status = status;
}

function fmt(dt) {
    return new Date(dt).toLocaleString('es-CO', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
    <div>
        <h1 class="mb-1 font-display text-2xl font-semibold text-brand-900">Soporte</h1>
        <p class="mb-6 text-sm text-sand-500">Tickets enviados por los dueños de negocio.</p>

        <!-- Detalle -->
        <div v-if="selected" class="max-w-2xl">
            <button type="button" class="mb-4 text-sm text-sand-500 hover:text-sand-700" @click="selected = null">← Volver a la lista</button>

            <Card>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-display text-lg font-semibold text-brand-900">{{ selected.subject }}</h2>
                        <p class="text-xs text-sand-400">
                            {{ TYPE_LABELS[selected.type] }} · {{ selected.user?.name }} ({{ selected.user?.email }}) · {{ fmt(selected.created_at) }}
                        </p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-medium capitalize" :class="STATUS_STYLES[selected.status]">{{ selected.status }}</span>
                </div>

                <Spinner v-if="loadingDetail">Cargando…</Spinner>
                <template v-else>
                    <div class="flex flex-col gap-3">
                        <div class="rounded-xl bg-sand-50 p-4">
                            <p class="whitespace-pre-wrap text-sm text-sand-700">{{ selected.message }}</p>
                        </div>
                        <div
                            v-for="reply in selected.replies"
                            :key="reply.id"
                            class="rounded-xl p-4"
                            :class="reply.user?.role === 'super_admin' ? 'bg-brand-50' : 'bg-sand-50'"
                        >
                            <p class="mb-1 text-xs font-medium" :class="reply.user?.role === 'super_admin' ? 'text-brand-700' : 'text-sand-500'">
                                {{ reply.user?.role === 'super_admin' ? 'Tú (soporte)' : reply.user?.name }} · {{ fmt(reply.created_at) }}
                            </p>
                            <p class="whitespace-pre-wrap text-sm text-sand-700">{{ reply.message }}</p>
                        </div>
                    </div>

                    <form class="mt-4 flex flex-col gap-2" @submit.prevent="sendReply">
                        <textarea
                            v-model="replyText"
                            rows="3"
                            maxlength="5000"
                            placeholder="Escribe la respuesta para el usuario…"
                            class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                        />
                        <div class="flex flex-wrap items-center gap-3">
                            <Button type="submit" :disabled="replying || !replyText.trim()">
                                {{ replying ? 'Enviando…' : 'Responder' }}
                            </Button>
                            <button
                                v-if="selected.status !== 'cerrado'"
                                type="button"
                                :disabled="closing"
                                class="text-sm font-medium text-sand-500 hover:text-sand-700"
                                @click="setStatus('cerrado')"
                            >
                                {{ closing ? 'Cerrando…' : 'Cerrar ticket' }}
                            </button>
                            <button
                                v-else
                                type="button"
                                :disabled="closing"
                                class="text-sm font-medium text-sand-500 hover:text-sand-700"
                                @click="setStatus('abierto')"
                            >
                                Reabrir ticket
                            </button>
                        </div>
                    </form>
                </template>
            </Card>
        </div>

        <!-- Lista -->
        <template v-else>
            <div class="mb-4 flex flex-wrap gap-3">
                <select v-model="statusFilter" class="rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <option value="">Todos los estados</option>
                    <option value="abierto">Abiertos</option>
                    <option value="respondido">Respondidos</option>
                    <option value="cerrado">Cerrados</option>
                </select>
                <select v-model="typeFilter" class="rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <option value="">Todos los tipos</option>
                    <option value="error">Errores</option>
                    <option value="queja">Quejas</option>
                    <option value="sugerencia">Sugerencias</option>
                </select>
            </div>

            <Spinner v-if="loading">Cargando…</Spinner>

            <div v-else-if="tickets.length" class="flex max-w-3xl flex-col gap-3">
                <Card
                    v-for="ticket in tickets"
                    :key="ticket.id"
                    class="cursor-pointer transition hover:border-brand-300"
                    @click="openTicket(ticket)"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-brand-900">{{ ticket.subject }}</p>
                            <p class="mt-0.5 text-xs text-sand-400">
                                {{ TYPE_LABELS[ticket.type] }} · {{ ticket.user?.name }} · {{ fmt(ticket.created_at) }}
                                <span v-if="ticket.replies_count"> · {{ ticket.replies_count }} respuesta{{ ticket.replies_count === 1 ? '' : 's' }}</span>
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-medium capitalize" :class="STATUS_STYLES[ticket.status]">{{ ticket.status }}</span>
                    </div>
                </Card>
            </div>

            <Card v-else class="max-w-3xl text-center">
                <p class="text-sm text-sand-500">No hay tickets con esos filtros.</p>
            </Card>
        </template>
    </div>
</template>
