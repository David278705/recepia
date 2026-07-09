<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { ChevronLeftIcon, ChevronRightIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline';
import api from '../lib/api';
import Badge from '../components/Badge.vue';
import Button from '../components/Button.vue';
import Spinner from '../components/Spinner.vue';
import EmptyState from '../components/EmptyState.vue';
import SlideOver from '../components/SlideOver.vue';
import AppointmentFormFields from '../components/AppointmentFormFields.vue';

const loading = ref(true);
const appointments = ref([]);
const weekStart = ref(startOfWeek(new Date()));
const slideOpen = ref(false);
const editingAppointment = ref(null);

const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const statusTones = { propuesta: 'amber', confirmada: 'brand', cancelada: 'sand', completada: 'sand', no_asistio: 'sand' };

function startOfWeek(date) {
    const d = new Date(date);
    const day = d.getDay();
    d.setDate(d.getDate() - day);
    d.setHours(0, 0, 0, 0);
    return d;
}

function toDateParam(date) {
    return date.toISOString().slice(0, 10);
}

const days = computed(() => Array.from({ length: 7 }, (_, i) => {
    const d = new Date(weekStart.value);
    d.setDate(d.getDate() + i);
    return d;
}));

const weekLabel = computed(() => {
    const end = new Date(weekStart.value);
    end.setDate(end.getDate() + 6);
    return `${weekStart.value.toLocaleDateString('es-CO', { day: 'numeric', month: 'short' })} – ${end.toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' })}`;
});

function appointmentsFor(date) {
    const key = toDateParam(date);
    return appointments.value
        .filter((a) => a.starts_at.slice(0, 10) === key)
        .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
}

async function load() {
    loading.value = true;
    const { data } = await api.get('/appointments', { params: { week_start: toDateParam(weekStart.value) } });
    appointments.value = data.data;
    loading.value = false;
}

function previousWeek() {
    const d = new Date(weekStart.value);
    d.setDate(d.getDate() - 7);
    weekStart.value = d;
}

function nextWeek() {
    const d = new Date(weekStart.value);
    d.setDate(d.getDate() + 7);
    weekStart.value = d;
}

function today() {
    weekStart.value = startOfWeek(new Date());
}

function openCreate() {
    editingAppointment.value = null;
    slideOpen.value = true;
}

function openEdit(appointment) {
    editingAppointment.value = appointment;
    slideOpen.value = true;
}

function closeSlide() {
    slideOpen.value = false;
    editingAppointment.value = null;
}

function handleSaved() {
    closeSlide();
    load();
}

function formatTime(iso) {
    return iso.slice(11, 16);
}

onMounted(load);
watch(weekStart, load);
</script>

<template>
    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-2xl font-semibold text-brand-900">Citas</h1>
            <Button @click="openCreate">+ Nueva cita</Button>
        </div>

        <div class="mb-6 flex items-center gap-3">
            <button type="button" class="rounded-lg border border-sand-200 p-1.5 hover:bg-sand-50" @click="previousWeek">
                <ChevronLeftIcon class="h-4 w-4" />
            </button>
            <button type="button" class="rounded-lg border border-sand-200 p-1.5 hover:bg-sand-50" @click="nextWeek">
                <ChevronRightIcon class="h-4 w-4" />
            </button>
            <button type="button" class="text-sm text-brand-700 hover:underline" @click="today">Hoy</button>
            <span class="text-sm font-medium text-sand-600">{{ weekLabel }}</span>
        </div>

        <Spinner v-if="loading">Cargando citas…</Spinner>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
            <div v-for="(day, i) in days" :key="i" class="rounded-xl border border-sand-200 bg-white p-2">
                <p class="mb-2 px-1 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                    {{ dayNames[day.getDay()] }} {{ day.getDate() }}
                </p>

                <EmptyState v-if="!appointmentsFor(day).length" title="" description="Sin citas" class="py-4" />

                <button
                    v-for="appt in appointmentsFor(day)"
                    :key="appt.id"
                    type="button"
                    class="mb-1.5 flex w-full flex-col rounded-lg border border-sand-100 px-2 py-1.5 text-left text-xs transition hover:border-brand-300 hover:bg-brand-50"
                    @click="openEdit(appt)"
                >
                    <div class="flex items-center justify-between gap-1">
                        <span class="font-mono font-semibold text-brand-800">{{ formatTime(appt.starts_at) }}</span>
                        <Badge :tone="statusTones[appt.status]">{{ appt.status }}</Badge>
                    </div>
                    <span class="truncate text-sand-700">{{ appt.contact?.name || appt.contact?.wa_id }}</span>
                    <span v-if="appt.service" class="truncate text-sand-500">{{ appt.service.name }}</span>
                </button>
            </div>
        </div>

        <SlideOver :open="slideOpen" :title="editingAppointment ? 'Editar cita' : 'Nueva cita'" @close="closeSlide">
            <AppointmentFormFields
                v-if="slideOpen"
                :appointment="editingAppointment"
                @saved="handleSaved"
                @cancelled="handleSaved"
            />
        </SlideOver>
    </div>
</template>
