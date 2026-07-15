<script setup>
import { onMounted, ref } from 'vue';
import api from '../../lib/api';
import Card from '../Card.vue';
import Button from '../Button.vue';
import Badge from '../Badge.vue';
import Spinner from '../Spinner.vue';

const loading = ref(true);
const services = ref([]);
const saving = ref(false);
const errors = ref({});
const editingId = ref(null);

const emptyForm = () => ({ name: '', description: '', duration_minutes: 30, price: '', price_note: '', active: true });
const form = ref(emptyForm());

async function load() {
    loading.value = true;
    const { data } = await api.get('/services');
    services.value = data.data;
    loading.value = false;
}

function edit(service) {
    editingId.value = service.id;
    form.value = {
        name: service.name,
        description: service.description ?? '',
        duration_minutes: service.duration_minutes ?? '',
        price: service.price ?? '',
        price_note: service.price_note ?? '',
        active: service.active,
    };
}

function resetForm() {
    editingId.value = null;
    form.value = emptyForm();
    errors.value = {};
}

async function handleSubmit() {
    saving.value = true;
    errors.value = {};
    const payload = {
        ...form.value,
        price: form.value.price === '' ? null : form.value.price,
        duration_minutes: form.value.duration_minutes === '' || form.value.duration_minutes === null ? null : form.value.duration_minutes,
        price_note: form.value.price_note === '' ? null : form.value.price_note,
    };

    try {
        if (editingId.value) {
            await api.put(`/services/${editingId.value}`, payload);
        } else {
            await api.post('/services', payload);
        }
        resetForm();
        await load();
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
    } finally {
        saving.value = false;
    }
}

async function remove(service) {
    if (!confirm(`¿Eliminar "${service.name}"?`)) return;
    await api.delete(`/services/${service.id}`);
    if (editingId.value === service.id) resetForm();
    await load();
}

onMounted(load);
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <div>
            <Spinner v-if="loading">Cargando servicios…</Spinner>
            <p v-else-if="!services.length" class="text-sm text-sand-500">Todavía no tienes servicios configurados.</p>

            <div v-else class="flex flex-col gap-2">
                <Card v-for="service in services" :key="service.id" class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-medium text-sand-800">{{ service.name }}</p>
                        <p class="text-xs text-sand-500">
                            {{ service.duration_minutes ? `${service.duration_minutes} min` : 'Solo informativo' }} ·
                            {{ service.price ? `$${Number(service.price).toLocaleString('es-CO')}` : (service.price_note || 'Precio a confirmar') }}<template v-if="service.price && service.price_note"> ({{ service.price_note }})</template>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge :tone="service.active ? 'brand' : 'sand'">{{ service.active ? 'Activo' : 'Inactivo' }}</Badge>
                        <button type="button" class="text-xs text-brand-700 hover:underline" @click="edit(service)">Editar</button>
                        <button type="button" class="text-xs text-amber-700 hover:underline" @click="remove(service)">Eliminar</button>
                    </div>
                </Card>
            </div>
        </div>

        <Card>
            <h3 class="mb-3 font-display text-sm font-semibold text-brand-900">{{ editingId ? 'Editar servicio' : 'Nuevo servicio' }}</h3>
            <form class="flex flex-col gap-3" @submit.prevent="handleSubmit">
                <div>
                    <input v-model="form.name" type="text" placeholder="Nombre" required class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p v-if="errors.name" class="mt-1 text-xs text-amber-700">{{ errors.name[0] }}</p>
                </div>
                <textarea v-model="form.description" placeholder="Descripción (opcional)" rows="2" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                <div>
                    <label class="mb-1 block text-xs text-sand-500">Duración (min) — vacío = solo informativo, no se agenda</label>
                    <input v-model.number="form.duration_minutes" type="number" min="5" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p v-if="errors.duration_minutes" class="mt-1 text-xs text-amber-700">{{ errors.duration_minutes[0] }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-sand-500">Precio (vacío = "el precio te lo confirma el equipo")</label>
                    <input v-model="form.price" type="number" min="0" step="1000" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-sand-500">Nota de precio (opcional: "desde", "según tamaño"…)</label>
                    <input v-model="form.price_note" type="text" maxlength="100" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p v-if="errors.price_note" class="mt-1 text-xs text-amber-700">{{ errors.price_note[0] }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-sand-700">
                    <input v-model="form.active" type="checkbox" class="rounded border-sand-300 text-brand-700 focus:ring-brand-500">
                    Activo
                </label>
                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="saving">{{ saving ? 'Guardando…' : (editingId ? 'Guardar' : 'Agregar') }}</Button>
                    <button v-if="editingId" type="button" class="text-xs text-sand-500 hover:underline" @click="resetForm">Cancelar</button>
                </div>
            </form>
        </Card>
    </div>
</template>
