<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../lib/api';
import Card from '../components/Card.vue';
import Button from '../components/Button.vue';
import Spinner from '../components/Spinner.vue';

const TYPE_LABELS = { error: 'Error', queja: 'Queja', sugerencia: 'Sugerencia' };
const STATUS_STYLES = {
    abierto: 'bg-amber-100 text-amber-800',
    respondido: 'bg-brand-100 text-brand-800',
    cerrado: 'bg-sand-200 text-sand-600',
};

const loading = ref(true);
const tickets = ref([]);
const creating = ref(false);
const submitting = ref(false);
const errors = ref({});

const selected = ref(null);
const loadingDetail = ref(false);
const replyText = ref('');
const replying = ref(false);

const form = reactive({ type: 'error', subject: '', message: '' });

const sorted = computed(() => tickets.value);

onMounted(loadTickets);

async function loadTickets() {
    const { data } = await api.get('/support-tickets');
    tickets.value = data.data;
    loading.value = false;
}

async function createTicket() {
    submitting.value = true;
    errors.value = {};
    try {
        const { data } = await api.post('/support-tickets', form);
        tickets.value.unshift(data.data);
        creating.value = false;
        form.type = 'error';
        form.subject = '';
        form.message = '';
        openTicket(data.data);
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
    } finally {
        submitting.value = false;
    }
}

async function openTicket(ticket) {
    loadingDetail.value = true;
    selected.value = ticket;
    const { data } = await api.get(`/support-tickets/${ticket.id}`);
    selected.value = data.data;
    loadingDetail.value = false;
}

async function sendReply() {
    if (!replyText.value.trim()) return;
    replying.value = true;
    try {
        const { data } = await api.post(`/support-tickets/${selected.value.id}/replies`, { message: replyText.value });
        selected.value.replies.push(data.data);
        selected.value.status = 'abierto';
        const row = tickets.value.find((t) => t.id === selected.value.id);
        if (row) row.status = 'abierto';
        replyText.value = '';
    } finally {
        replying.value = false;
    }
}

function fmt(dt) {
    return new Date(dt).toLocaleString('es-CO', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="mb-1 font-display text-2xl font-semibold text-brand-900">Soporte</h1>
                <p class="text-sm text-sand-500">Reporta un error, envía una queja o comparte una sugerencia.</p>
            </div>
            <Button v-if="!creating && !selected" @click="creating = true">Nuevo ticket</Button>
        </div>

        <Spinner v-if="loading">Cargando…</Spinner>

        <!-- Formulario de creación -->
        <Card v-else-if="creating" class="max-w-xl">
            <form class="flex flex-col gap-4" @submit.prevent="createTicket">
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Tipo</label>
                    <div class="flex gap-2">
                        <button
                            v-for="(label, key) in TYPE_LABELS"
                            :key="key"
                            type="button"
                            class="rounded-lg border px-4 py-2 text-sm font-medium transition"
                            :class="form.type === key
                                ? 'border-brand-600 bg-brand-50 text-brand-800'
                                : 'border-sand-200 text-sand-600 hover:border-sand-300'"
                            @click="form.type = key"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Asunto</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        required
                        maxlength="150"
                        placeholder="Resumen corto del tema"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                    <p v-if="errors.subject" class="mt-1 text-xs text-amber-700">{{ errors.subject[0] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Descripción</label>
                    <textarea
                        v-model="form.message"
                        rows="5"
                        required
                        maxlength="5000"
                        placeholder="Cuéntanos con detalle qué pasó o qué propones…"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    />
                    <p v-if="errors.message" class="mt-1 text-xs text-amber-700">{{ errors.message[0] }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="submitting">{{ submitting ? 'Enviando…' : 'Enviar ticket' }}</Button>
                    <button type="button" class="text-sm text-sand-500 hover:text-sand-700" @click="creating = false">Cancelar</button>
                </div>
            </form>
        </Card>

        <!-- Detalle de un ticket -->
        <div v-else-if="selected" class="max-w-2xl">
            <button type="button" class="mb-4 text-sm text-sand-500 hover:text-sand-700" @click="selected = null">← Volver a mis tickets</button>

            <Card>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-display text-lg font-semibold text-brand-900">{{ selected.subject }}</h2>
                        <p class="text-xs text-sand-400">{{ TYPE_LABELS[selected.type] }} · {{ fmt(selected.created_at) }}</p>
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
                                {{ reply.user?.role === 'super_admin' ? 'Soporte Pilo' : 'Tú' }} · {{ fmt(reply.created_at) }}
                            </p>
                            <p class="whitespace-pre-wrap text-sm text-sand-700">{{ reply.message }}</p>
                        </div>
                    </div>

                    <form v-if="selected.status !== 'cerrado'" class="mt-4 flex flex-col gap-2" @submit.prevent="sendReply">
                        <textarea
                            v-model="replyText"
                            rows="3"
                            maxlength="5000"
                            placeholder="Escribe una respuesta…"
                            class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                        />
                        <Button type="submit" :disabled="replying || !replyText.trim()" class="self-start">
                            {{ replying ? 'Enviando…' : 'Responder' }}
                        </Button>
                    </form>
                    <p v-else class="mt-4 text-sm text-sand-400">Este ticket está cerrado. Si necesitas algo más, abre uno nuevo.</p>
                </template>
            </Card>
        </div>

        <!-- Lista -->
        <div v-else-if="sorted.length" class="flex max-w-2xl flex-col gap-3">
            <Card
                v-for="ticket in sorted"
                :key="ticket.id"
                class="cursor-pointer transition hover:border-brand-300"
                @click="openTicket(ticket)"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-brand-900">{{ ticket.subject }}</p>
                        <p class="mt-0.5 text-xs text-sand-400">
                            {{ TYPE_LABELS[ticket.type] }} · {{ fmt(ticket.created_at) }}
                            <span v-if="ticket.replies_count"> · {{ ticket.replies_count }} respuesta{{ ticket.replies_count === 1 ? '' : 's' }}</span>
                        </p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-medium capitalize" :class="STATUS_STYLES[ticket.status]">{{ ticket.status }}</span>
                </div>
            </Card>
        </div>

        <Card v-else class="max-w-2xl text-center">
            <p class="text-sm text-sand-500">Aún no tienes tickets. Si algo no funciona o tienes una idea, cuéntanos.</p>
            <Button class="mt-4" @click="creating = true">Crear mi primer ticket</Button>
        </Card>
    </div>
</template>
