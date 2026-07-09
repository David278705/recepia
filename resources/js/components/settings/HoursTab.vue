<script setup>
import { onMounted, ref } from 'vue';
import api from '../../lib/api';
import Card from '../Card.vue';
import Button from '../Button.vue';
import Spinner from '../Spinner.vue';

const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

const loading = ref(true);
const hours = ref([]);
const saving = ref(false);
const errors = ref({});

const form = ref({ day_of_week: 1, opens_at: '09:00', closes_at: '18:00', active: true });

async function load() {
    loading.value = true;
    const { data } = await api.get('/business-hours');
    hours.value = data.data;
    loading.value = false;
}

function hoursForDay(day) {
    return hours.value.filter((h) => h.day_of_week === day);
}

async function handleSubmit() {
    saving.value = true;
    errors.value = {};
    try {
        await api.post('/business-hours', form.value);
        form.value = { ...form.value, opens_at: '09:00', closes_at: '18:00' };
        await load();
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
    } finally {
        saving.value = false;
    }
}

async function toggleActive(hour) {
    await api.put(`/business-hours/${hour.id}`, { active: !hour.active });
    await load();
}

async function remove(hour) {
    if (!confirm('¿Eliminar esta franja horaria?')) return;
    await api.delete(`/business-hours/${hour.id}`);
    await load();
}

onMounted(load);
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <div>
            <Spinner v-if="loading">Cargando horarios…</Spinner>

            <div v-else class="flex flex-col gap-3">
                <Card v-for="(day, i) in dayNames" :key="i">
                    <p class="mb-2 font-medium text-sand-800">{{ day }}</p>
                    <p v-if="!hoursForDay(i).length" class="text-sm text-sand-400">Cerrado</p>
                    <div v-for="hour in hoursForDay(i)" :key="hour.id" class="flex items-center justify-between border-t border-sand-100 py-1.5 text-sm first:border-t-0">
                        <span :class="hour.active ? 'text-sand-700' : 'text-sand-400 line-through'">{{ hour.opens_at.slice(0, 5) }} – {{ hour.closes_at.slice(0, 5) }}</span>
                        <div class="flex items-center gap-2">
                            <button type="button" class="text-xs text-brand-700 hover:underline" @click="toggleActive(hour)">
                                {{ hour.active ? 'Desactivar' : 'Activar' }}
                            </button>
                            <button type="button" class="text-xs text-amber-700 hover:underline" @click="remove(hour)">Eliminar</button>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <Card>
            <h3 class="mb-3 font-display text-sm font-semibold text-brand-900">Agregar franja</h3>
            <form class="flex flex-col gap-3" @submit.prevent="handleSubmit">
                <div>
                    <label class="mb-1 block text-xs text-sand-500">Día</label>
                    <select v-model.number="form.day_of_week" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <option v-for="(day, i) in dayNames" :key="i" :value="i">{{ day }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-sand-500">Abre</label>
                    <input v-model="form.opens_at" type="time" required class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-sand-500">Cierra</label>
                    <input v-model="form.closes_at" type="time" required class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p v-if="errors.closes_at" class="mt-1 text-xs text-amber-700">{{ errors.closes_at[0] }}</p>
                </div>
                <Button type="submit" :disabled="saving">{{ saving ? 'Guardando…' : 'Agregar franja' }}</Button>
                <p class="text-xs text-sand-400">Puedes agregar varias franjas por día (ej. 9am-1pm y 2pm-6pm).</p>
            </form>
        </Card>
    </div>
</template>
