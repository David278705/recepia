<script setup>
import { onMounted, ref } from 'vue';
import api from '../../lib/api';
import Card from '../Card.vue';
import Button from '../Button.vue';
import Spinner from '../Spinner.vue';

const loading = ref(true);
const faqs = ref([]);
const saving = ref(false);
const errors = ref({});
const editingId = ref(null);

const emptyForm = () => ({ question: '', answer: '', active: true });
const form = ref(emptyForm());

async function load() {
    loading.value = true;
    const { data } = await api.get('/faqs');
    faqs.value = data.data;
    loading.value = false;
}

function edit(faq) {
    editingId.value = faq.id;
    form.value = { question: faq.question, answer: faq.answer, active: faq.active };
}

function resetForm() {
    editingId.value = null;
    form.value = emptyForm();
    errors.value = {};
}

async function handleSubmit() {
    saving.value = true;
    errors.value = {};
    try {
        if (editingId.value) {
            await api.put(`/faqs/${editingId.value}`, form.value);
        } else {
            await api.post('/faqs', form.value);
        }
        resetForm();
        await load();
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
    } finally {
        saving.value = false;
    }
}

async function remove(faq) {
    if (!confirm('¿Eliminar esta pregunta frecuente?')) return;
    await api.delete(`/faqs/${faq.id}`);
    if (editingId.value === faq.id) resetForm();
    await load();
}

onMounted(load);
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
        <div>
            <Spinner v-if="loading">Cargando…</Spinner>
            <p v-else-if="!faqs.length" class="text-sm text-sand-500">Todavía no tienes preguntas frecuentes configuradas.</p>

            <div v-else class="flex flex-col gap-2">
                <Card v-for="faq in faqs" :key="faq.id">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-sand-800">{{ faq.question }}</p>
                            <p class="mt-1 text-sm text-sand-500">{{ faq.answer }}</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <button type="button" class="text-xs text-brand-700 hover:underline" @click="edit(faq)">Editar</button>
                            <button type="button" class="text-xs text-amber-700 hover:underline" @click="remove(faq)">Eliminar</button>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <Card>
            <h3 class="mb-3 font-display text-sm font-semibold text-brand-900">{{ editingId ? 'Editar pregunta' : 'Nueva pregunta' }}</h3>
            <form class="flex flex-col gap-3" @submit.prevent="handleSubmit">
                <div>
                    <input v-model="form.question" type="text" placeholder="Pregunta" required class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p v-if="errors.question" class="mt-1 text-xs text-amber-700">{{ errors.question[0] }}</p>
                </div>
                <div>
                    <textarea v-model="form.answer" placeholder="Respuesta" rows="3" required class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                    <p v-if="errors.answer" class="mt-1 text-xs text-amber-700">{{ errors.answer[0] }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="saving">{{ saving ? 'Guardando…' : (editingId ? 'Guardar' : 'Agregar') }}</Button>
                    <button v-if="editingId" type="button" class="text-xs text-sand-500 hover:underline" @click="resetForm">Cancelar</button>
                </div>
            </form>
        </Card>
    </div>
</template>
