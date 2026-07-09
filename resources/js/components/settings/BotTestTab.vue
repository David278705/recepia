<script setup>
import { nextTick, ref } from 'vue';
import { PaperAirplaneIcon } from '@heroicons/vue/24/outline';
import api from '../../lib/api';
import Card from '../Card.vue';

const messages = ref([]);
const draft = ref('');
const sending = ref(false);
const scrollArea = ref(null);

async function send() {
    const text = draft.value.trim();
    if (!text || sending.value) return;

    messages.value.push({ role: 'user', content: text });
    draft.value = '';
    sending.value = true;

    await scrollToBottom();

    try {
        const { data } = await api.post('/bot-test', { messages: messages.value });
        messages.value.push({ role: 'assistant', content: data.data.reply });
    } catch (error) {
        messages.value.push({ role: 'assistant', content: `⚠️ ${error.response?.data?.message ?? 'El agente no pudo responder.'}` });
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
}

async function scrollToBottom() {
    await nextTick();
    scrollArea.value?.scrollTo({ top: scrollArea.value.scrollHeight });
}

function restart() {
    messages.value = [];
}
</script>

<template>
    <div class="max-w-xl">
        <p class="mb-3 text-sm text-sand-500">
            Chatea con tu bot como si fueras un cliente. Usa el mismo prompt y las mismas herramientas que en
            WhatsApp, pero nada de esto se envía de verdad ni agenda citas reales.
        </p>

        <Card class="flex h-[28rem] flex-col p-0">
            <div ref="scrollArea" class="flex-1 space-y-3 overflow-y-auto p-4">
                <p v-if="!messages.length" class="text-sm text-sand-400">Escribe algo como lo haría un cliente para empezar la prueba.</p>
                <div
                    v-for="(message, i) in messages"
                    :key="i"
                    class="flex flex-col"
                    :class="message.role === 'user' ? 'items-end' : 'items-start'"
                >
                    <div
                        class="max-w-[80%] rounded-2xl px-3 py-2 text-sm"
                        :class="message.role === 'user' ? 'bg-brand-700 text-white' : 'bg-sand-100 text-sand-800'"
                    >
                        {{ message.content }}
                    </div>
                </div>
                <p v-if="sending" class="text-xs text-sand-400">El bot está escribiendo…</p>
            </div>

            <form class="flex items-center gap-2 border-t border-sand-100 p-3" @submit.prevent="send">
                <input
                    v-model="draft"
                    type="text"
                    placeholder="Escribe como si fueras un cliente…"
                    class="flex-1 rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                >
                <button
                    type="submit"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-700 text-white transition hover:bg-brand-800 disabled:opacity-50"
                    :disabled="sending || !draft.trim()"
                >
                    <PaperAirplaneIcon class="h-4 w-4" />
                </button>
            </form>
        </Card>

        <button type="button" class="mt-3 text-xs text-sand-500 hover:underline" @click="restart">Reiniciar conversación de prueba</button>
    </div>
</template>
