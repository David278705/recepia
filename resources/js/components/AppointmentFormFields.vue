<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../lib/api';
import Button from '../components/Button.vue';

const props = defineProps({
    appointment: { type: Object, default: null },
});
const emit = defineEmits(['saved', 'cancelled']);

const isEdit = computed(() => !!props.appointment);

const services = ref([]);
const contacts = ref([]);
const contactSearch = ref('');
const contactMode = ref('new');
const saving = ref(false);
const cancelling = ref(false);
const errors = ref({});

const form = ref({
    service_id: '',
    starts_at: '',
    notes: '',
    status: 'confirmada',
    contact_id: '',
    contact_name: '',
    contact_wa_id: '',
});

function toLocalInputValue(iso) {
    return iso ? iso.slice(0, 16) : '';
}

function resetForm() {
    if (props.appointment) {
        form.value = {
            service_id: props.appointment.service?.id ?? '',
            starts_at: toLocalInputValue(props.appointment.starts_at),
            notes: props.appointment.notes ?? '',
            status: props.appointment.status,
            contact_id: '',
            contact_name: '',
            contact_wa_id: '',
        };
    } else {
        form.value = { service_id: '', starts_at: '', notes: '', status: 'confirmada', contact_id: '', contact_name: '', contact_wa_id: '' };
        contactMode.value = 'new';
    }
}

async function loadServices() {
    const { data } = await api.get('/services');
    services.value = data.data;
}

async function searchContacts() {
    const { data } = await api.get('/contacts', { params: { search: contactSearch.value } });
    contacts.value = data.data;
}

onMounted(() => {
    loadServices();
    searchContacts();
    resetForm();
});

watch(() => props.appointment, resetForm);
watch(contactSearch, searchContacts);

async function handleSubmit() {
    saving.value = true;
    errors.value = {};

    try {
        if (isEdit.value) {
            await api.put(`/appointments/${props.appointment.id}`, {
                service_id: form.value.service_id || null,
                starts_at: form.value.starts_at,
                notes: form.value.notes,
                status: form.value.status,
            });
        } else {
            await api.post('/appointments', {
                service_id: form.value.service_id || null,
                starts_at: form.value.starts_at,
                notes: form.value.notes,
                contact_mode: contactMode.value,
                contact_id: form.value.contact_id || null,
                contact_name: form.value.contact_name,
                contact_wa_id: form.value.contact_wa_id,
            });
        }
        emit('saved');
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
    } finally {
        saving.value = false;
    }
}

async function handleCancel() {
    if (!confirm('¿Cancelar esta cita?')) return;
    cancelling.value = true;
    try {
        await api.post(`/appointments/${props.appointment.id}/cancel`);
        emit('cancelled');
    } finally {
        cancelling.value = false;
    }
}
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
        <div v-if="!isEdit" class="flex flex-col gap-3 rounded-xl border border-sand-200 p-3">
            <p class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Cliente</p>
            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-1.5">
                    <input v-model="contactMode" type="radio" value="new"> Cliente nuevo
                </label>
                <label class="flex items-center gap-1.5">
                    <input v-model="contactMode" type="radio" value="existing"> Cliente existente
                </label>
            </div>

            <template v-if="contactMode === 'new'">
                <input v-model="form.contact_name" type="text" placeholder="Nombre" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                <p v-if="errors.contact_name" class="text-xs text-amber-700">{{ errors.contact_name[0] }}</p>
                <input v-model="form.contact_wa_id" type="text" placeholder="WhatsApp (ej. 573001234567)" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                <p v-if="errors.contact_wa_id" class="text-xs text-amber-700">{{ errors.contact_wa_id[0] }}</p>
            </template>

            <template v-else>
                <input v-model="contactSearch" type="text" placeholder="Buscar por nombre o número…" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                <select v-model="form.contact_id" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <option value="" disabled>Selecciona un cliente</option>
                    <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name || c.wa_id }} ({{ c.wa_id }})</option>
                </select>
                <p v-if="errors.contact_id" class="text-xs text-amber-700">{{ errors.contact_id[0] }}</p>
            </template>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-sand-700">Servicio</label>
            <select v-model="form.service_id" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                <option value="">Sin servicio específico</option>
                <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }} ({{ s.duration_minutes }} min)</option>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-sand-700">Fecha y hora</label>
            <input v-model="form.starts_at" type="datetime-local" required class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
            <p v-if="errors.starts_at" class="mt-1 text-xs text-amber-700">{{ errors.starts_at[0] }}</p>
        </div>

        <div v-if="isEdit">
            <label class="mb-1 block text-sm font-medium text-sand-700">Estado</label>
            <select v-model="form.status" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                <option value="propuesta">Propuesta</option>
                <option value="confirmada">Confirmada</option>
                <option value="completada">Completada</option>
                <option value="no_asistio">No asistió</option>
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-sand-700">Notas</label>
            <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
        </div>

        <div class="flex items-center justify-between">
            <Button v-if="isEdit" type="button" variant="danger" :disabled="cancelling" @click="handleCancel">
                {{ cancelling ? 'Cancelando…' : 'Cancelar cita' }}
            </Button>
            <span v-else />
            <Button type="submit" :disabled="saving">{{ saving ? 'Guardando…' : (isEdit ? 'Guardar cambios' : 'Crear cita') }}</Button>
        </div>
    </form>
</template>
