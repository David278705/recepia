<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ChatBubbleLeftRightIcon, PaperAirplaneIcon, ChevronLeftIcon, ArrowDownIcon } from '@heroicons/vue/24/outline';
import api from '../lib/api';
import Badge from '../components/Badge.vue';
import Spinner from '../components/Spinner.vue';
import EmptyState from '../components/EmptyState.vue';

const route = useRoute();

const POLL_MS = 5000;

const loading = ref(true);
const conversations = ref([]);
const selectedId = ref(null);
const detail = ref(null);
const messages = ref([]);
const loadingDetail = ref(false);
const draft = ref('');
const sending = ref(false);
const sendError = ref('');
const actionBusy = ref(false);
const newBelow = ref(false);

const messagesEl = ref(null);
const composerEl = ref(null);
let pollTimer = null;

const statusLabels = { bot_activo: 'Bot activo', escalada: 'Escalada', cerrada: 'Cerrada' };
const statusTones = { bot_activo: 'brand', escalada: 'urgent', cerrada: 'sand' };
const originLabels = { cliente: '', bot: 'Recepia', dueno_app: 'Tú · WhatsApp', dueno_panel: 'Tú · panel' };

const isDesktop = () => window.matchMedia('(min-width: 768px)').matches;

const contactInitials = computed(() => {
    const name = detail.value?.contact?.name ?? '';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('') || '#';
});

/* ---------- helpers de tiempo ---------- */

function listTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toDateString() === new Date().toDateString()
        ? d.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString('es-CO', { day: 'numeric', month: 'short' });
}

function messageTime(iso) {
    return iso ? new Date(iso).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }) : '';
}

function dayLabel(iso) {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Hoy';
    if (d.toDateString() === yesterday.toDateString()) return 'Ayer';
    return d.toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' });
}

function showDayDivider(index) {
    if (index === 0) return true;
    return new Date(messages.value[index].created_at).toDateString()
        !== new Date(messages.value[index - 1].created_at).toDateString();
}

/* ---------- scroll ---------- */

function isNearBottom() {
    const el = messagesEl.value;
    if (!el) return true;
    return el.scrollHeight - el.scrollTop - el.clientHeight < 120;
}

async function scrollToBottom(behavior = 'auto') {
    await nextTick();
    const el = messagesEl.value;
    if (el) el.scrollTo({ top: el.scrollHeight, behavior });
    newBelow.value = false;
}

function onMessagesScroll() {
    if (isNearBottom()) newBelow.value = false;
}

/* ---------- carga ---------- */

async function loadConversations() {
    loading.value = true;
    await refreshConversations();
    loading.value = false;

    if (!selectedId.value && conversations.value.length && isDesktop()) {
        selectConversation(conversations.value[0].id);
    }
}

async function refreshConversations() {
    const params = route.query.status ? { status: route.query.status } : {};
    const { data } = await api.get('/conversations', { params });
    conversations.value = data.data;
}

async function selectConversation(id) {
    selectedId.value = id;
    loadingDetail.value = true;
    sendError.value = '';
    newBelow.value = false;

    const { data } = await api.get(`/conversations/${id}`);
    if (selectedId.value !== id) return; // el usuario cambió de chat mientras cargaba

    detail.value = data.data;
    messages.value = data.messages;
    loadingDetail.value = false;

    await scrollToBottom('auto');
    if (isDesktop()) composerEl.value?.focus();
}

function backToList() {
    selectedId.value = null;
    detail.value = null;
    messages.value = [];
}

/* ---------- actualización en vivo ---------- */

async function pollTick() {
    if (document.hidden) return;

    try {
        const id = selectedId.value;
        const requests = [refreshConversations()];

        if (id) {
            requests.push(api.get(`/conversations/${id}`).then(({ data }) => {
                if (selectedId.value !== id) return;

                const previousLastId = messages.value[messages.value.length - 1]?.id ?? null;
                const incomingLastId = data.messages[data.messages.length - 1]?.id ?? null;
                const hasNew = incomingLastId !== previousLastId || data.messages.length !== messages.value.length;

                const wasNearBottom = isNearBottom();
                detail.value = data.data;

                if (hasNew) {
                    messages.value = data.messages;
                    if (wasNearBottom) {
                        scrollToBottom('smooth');
                    } else {
                        newBelow.value = true;
                    }
                }
            }));
        }

        await Promise.all(requests);
    } catch {
        // Silencioso: el siguiente tick reintenta. Sin red no hay nada que hacer.
    }
}

function onVisibilityChange() {
    if (!document.hidden) pollTick();
}

/* ---------- acciones ---------- */

async function sendMessage() {
    const content = draft.value.trim();
    if (!content || !selectedId.value || sending.value) return;

    sending.value = true;
    sendError.value = '';

    try {
        const { data } = await api.post(`/conversations/${selectedId.value}/messages`, { content });
        draft.value = '';
        if (!messages.value.some((m) => m.id === data.data.id)) {
            messages.value.push(data.data);
        }
        await scrollToBottom('smooth');
    } catch (error) {
        sendError.value = error.response?.data?.message ?? 'No se pudo enviar el mensaje.';
    } finally {
        sending.value = false;
        composerEl.value?.focus();
    }
}

async function takeOver() {
    actionBusy.value = true;
    try {
        const { data } = await api.post(`/conversations/${selectedId.value}/take-over`);
        detail.value = { ...detail.value, ...data.data };
        refreshConversations();
    } finally {
        actionBusy.value = false;
    }
}

async function returnToBot() {
    actionBusy.value = true;
    try {
        const { data } = await api.post(`/conversations/${selectedId.value}/return-to-bot`);
        detail.value = { ...detail.value, ...data.data };
        refreshConversations();
    } finally {
        actionBusy.value = false;
    }
}

/* ---------- ciclo de vida ---------- */

onMounted(() => {
    loadConversations();
    pollTimer = setInterval(pollTick, POLL_MS);
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
});

watch(() => route.query.status, () => {
    backToList();
    loadConversations();
});
</script>

<template>
    <div class="flex h-[calc(100dvh-6.25rem)] flex-col sm:h-[calc(100dvh-7.25rem)] md:h-[calc(100dvh-4.25rem)]">
        <div class="mb-4 shrink-0" :class="selectedId ? 'hidden md:block' : ''">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-brand-950">Conversaciones</h1>
            <p class="mt-0.5 text-sm text-sand-500">Se actualizan solas — no necesitas refrescar.</p>
        </div>

        <div class="flex min-h-0 flex-1 gap-4">
            <!-- ============ LISTA ============ -->
            <section
                class="w-full flex-col overflow-hidden rounded-2xl border border-sand-200 bg-white md:w-80 md:shrink-0"
                :class="selectedId ? 'hidden md:flex' : 'flex'"
            >
                <Spinner v-if="loading" class="p-6">Cargando…</Spinner>

                <EmptyState
                    v-else-if="!conversations.length"
                    :icon="ChatBubbleLeftRightIcon"
                    title="Sin conversaciones"
                    description="Cuando un cliente escriba por WhatsApp, aparecerá aquí."
                    class="m-4"
                />

                <ul v-else class="flex-1 divide-y divide-sand-100 overflow-y-auto overscroll-contain">
                    <li v-for="conv in conversations" :key="conv.id">
                        <button
                            type="button"
                            class="flex w-full flex-col gap-1 px-4 py-3 text-left transition hover:bg-sand-50"
                            :class="{ 'bg-brand-50/60': conv.id === selectedId }"
                            @click="selectConversation(conv.id)"
                        >
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate font-medium text-sand-900">{{ conv.contact?.name || conv.contact?.wa_id }}</span>
                                <span class="shrink-0 text-[11px] text-sand-400">{{ listTime(conv.last_activity_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm text-sand-500">{{ conv.last_message?.content || 'Sin mensajes' }}</p>
                                <Badge :tone="statusTones[conv.status]" class="shrink-0">{{ statusLabels[conv.status] }}</Badge>
                            </div>
                        </button>
                    </li>
                </ul>
            </section>

            <!-- ============ CHAT ============ -->
            <section
                class="min-w-0 flex-1 flex-col overflow-hidden rounded-2xl border border-sand-200 bg-white"
                :class="selectedId ? 'flex' : 'hidden md:flex'"
            >
                <Spinner v-if="loadingDetail" class="p-6">Cargando conversación…</Spinner>

                <EmptyState
                    v-else-if="!detail"
                    image="/img/robot_tablet_sonriendo.png"
                    title="Selecciona una conversación"
                    description="Elige un chat de la lista para ver el hilo completo."
                    class="m-auto border-0"
                />

                <template v-else>
                    <!-- Cabecera del chat -->
                    <div class="flex items-center gap-3 border-b border-sand-100 px-3 py-2.5 sm:px-4">
                        <button
                            type="button"
                            aria-label="Volver a la lista"
                            class="-ml-1 rounded-lg p-1.5 text-sand-500 transition hover:bg-sand-100 md:hidden"
                            @click="backToList"
                        >
                            <ChevronLeftIcon class="h-5 w-5" />
                        </button>

                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800">
                            {{ contactInitials }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-sand-900">{{ detail.contact?.name || detail.contact?.wa_id }}</p>
                            <p class="truncate text-xs text-sand-400">{{ detail.contact?.wa_id }}</p>
                        </div>

                        <span class="hidden sm:inline-flex">
                            <Badge :tone="statusTones[detail.status]">{{ statusLabels[detail.status] }}</Badge>
                        </span>

                        <button
                            v-if="detail.status !== 'escalada'"
                            type="button"
                            class="shrink-0 rounded-lg border border-sand-200 px-2.5 py-1.5 text-xs font-medium text-sand-700 transition hover:bg-sand-50 disabled:opacity-50"
                            :disabled="actionBusy"
                            @click="takeOver"
                        >
                            Tomar conversación
                        </button>
                        <button
                            v-else
                            type="button"
                            class="shrink-0 rounded-lg bg-brand-800 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-900 disabled:opacity-50"
                            :disabled="actionBusy"
                            @click="returnToBot"
                        >
                            Devolver al bot
                        </button>
                    </div>

                    <!-- Hilo de mensajes -->
                    <div class="relative min-h-0 flex-1">
                        <div
                            ref="messagesEl"
                            class="flex h-full flex-col gap-1.5 overflow-y-auto overscroll-contain px-3 py-4 sm:px-5"
                            @scroll.passive="onMessagesScroll"
                        >
                            <template v-for="(message, i) in messages" :key="message.id">
                                <div v-if="showDayDivider(i)" class="my-3 flex items-center gap-3">
                                    <span class="h-px flex-1 bg-sand-100" />
                                    <span class="rounded-full bg-sand-100 px-2.5 py-0.5 text-[11px] font-medium text-sand-500">
                                        {{ dayLabel(message.created_at) }}
                                    </span>
                                    <span class="h-px flex-1 bg-sand-100" />
                                </div>

                                <div class="flex flex-col" :class="message.direction === 'in' ? 'items-start' : 'items-end'">
                                    <div
                                        class="max-w-[85%] rounded-2xl px-3.5 py-2 text-sm break-words sm:max-w-[70%]"
                                        :class="message.direction === 'in'
                                            ? 'rounded-tl-sm bg-sand-100 text-sand-800'
                                            : 'rounded-tr-sm bg-brand-800 text-white'"
                                    >
                                        {{ message.content || `[${message.type}]` }}
                                    </div>
                                    <span class="mt-0.5 px-1 text-[10px] text-sand-400">
                                        {{ originLabels[message.origin] }}{{ originLabels[message.origin] ? ' · ' : '' }}{{ messageTime(message.created_at) }}
                                    </span>
                                </div>
                            </template>
                        </div>

                        <!-- Aviso de mensajes nuevos abajo -->
                        <Transition name="page">
                            <button
                                v-if="newBelow"
                                type="button"
                                class="absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-brand-800 px-3.5 py-1.5 text-xs font-semibold text-white shadow-lg transition hover:bg-brand-900"
                                @click="scrollToBottom('smooth')"
                            >
                                <ArrowDownIcon class="h-3.5 w-3.5" /> Mensajes nuevos
                            </button>
                        </Transition>
                    </div>

                    <!-- Redacción -->
                    <div class="shrink-0 border-t border-sand-100 p-3">
                        <div v-if="!detail.window_open" class="rounded-lg bg-sand-100 px-3.5 py-2.5 text-xs leading-relaxed text-sand-600">
                            La ventana de 24 horas de WhatsApp expiró — el cliente debe escribirte de nuevo antes de que puedas responderle desde aquí.
                        </div>
                        <template v-else>
                            <p v-if="sendError" class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ sendError }}</p>
                            <form class="flex items-center gap-2" @submit.prevent="sendMessage">
                                <input
                                    ref="composerEl"
                                    v-model="draft"
                                    type="text"
                                    placeholder="Escribe un mensaje…"
                                    autocomplete="off"
                                    enterkeyhint="send"
                                    class="min-w-0 flex-1 rounded-xl border border-sand-200 px-3.5 py-2.5 text-sm transition focus:border-brand-600 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                                >
                                <button
                                    type="submit"
                                    aria-label="Enviar mensaje"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-800 text-white transition hover:bg-brand-900 disabled:opacity-50"
                                    :disabled="sending || !draft.trim()"
                                >
                                    <PaperAirplaneIcon class="h-4.5 w-4.5" />
                                </button>
                            </form>
                        </template>
                    </div>
                </template>
            </section>
        </div>
    </div>
</template>
