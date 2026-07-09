<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/api';
import Card from '../../components/Card.vue';
import Button from '../../components/Button.vue';
import Spinner from '../../components/Spinner.vue';

const props = defineProps({ id: { type: [String, Number], default: null } });
const emit = defineEmits(['saved', 'deleted']);

const isEdit = computed(() => props.id !== null);

const loading = ref(true);
const saving = ref(false);
const deleting = ref(false);
const errors = ref({});

const currentOwner = ref(null);
const changingOwner = ref(!isEdit.value);
const ownerMode = ref('new');
const availableOwners = ref([]);

const emptyForm = () => ({
    name: '',
    type: 'otro',
    address: '',
    phone: '',
    timezone: 'America/Bogota',
    status: 'piloto',
    monthly_price: '',
    tone: 'cercano',
    agent_model: '',
    extra_instructions: '',
    whatsapp_phone_number_id: '',
    whatsapp_waba_id: '',
    whatsapp_phone_e164: '',
    whatsapp_access_token: '',
    whatsapp_mode: 'coexistence',
});

const currentConnectionStatus = ref(null);

const form = ref(emptyForm());

const ownerForm = ref({ owner_name: '', owner_email: '', owner_password: '', owner_id: '' });

async function loadAvailableOwners() {
    const { data } = await api.get('/admin/available-owners', {
        params: isEdit.value ? { exclude_business: props.id } : {},
    });
    availableOwners.value = data.data;
}

async function load() {
    loading.value = true;
    changingOwner.value = !isEdit.value;
    ownerMode.value = 'new';
    errors.value = {};
    form.value = emptyForm();
    ownerForm.value = { owner_name: '', owner_email: '', owner_password: '', owner_id: '' };

    await loadAvailableOwners();

    if (isEdit.value) {
        const { data } = await api.get(`/admin/businesses/${props.id}`);
        const b = data.data;
        form.value = {
            name: b.name,
            type: b.type ?? 'otro',
            address: b.address ?? '',
            phone: b.phone ?? '',
            timezone: b.timezone ?? '',
            status: b.status ?? 'piloto',
            monthly_price: b.monthly_price ?? '',
            tone: b.tone ?? 'cercano',
            agent_model: b.agent_model ?? '',
            extra_instructions: b.extra_instructions ?? '',
            whatsapp_phone_number_id: b.whatsapp_account?.phone_number_id ?? '',
            whatsapp_waba_id: b.whatsapp_account?.waba_id ?? '',
            whatsapp_phone_e164: b.whatsapp_account?.phone_e164 ?? '',
            whatsapp_access_token: '',
            whatsapp_mode: b.whatsapp_account?.mode ?? 'coexistence',
        };
        currentOwner.value = b.owner;
        currentConnectionStatus.value = b.whatsapp_account?.connection_status ?? null;
    }

    loading.value = false;
}

defineExpose({ load });
onMounted(load);

async function handleSubmit() {
    saving.value = true;
    errors.value = {};

    const payload = { ...form.value };

    payload.monthly_price = payload.monthly_price === '' ? null : Number(payload.monthly_price);

    // No pisar el token guardado si el admin no escribió uno nuevo.
    if (!payload.whatsapp_access_token) {
        delete payload.whatsapp_access_token;
    }

    if (changingOwner.value) {
        payload.owner_mode = ownerMode.value;
        if (ownerMode.value === 'new') {
            payload.owner_name = ownerForm.value.owner_name;
            payload.owner_email = ownerForm.value.owner_email;
            payload.owner_password = ownerForm.value.owner_password;
        } else {
            payload.owner_id = ownerForm.value.owner_id;
        }
    }

    try {
        if (isEdit.value) {
            await api.put(`/admin/businesses/${props.id}`, payload);
        } else {
            await api.post('/admin/businesses', payload);
        }
        emit('saved');
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
    } finally {
        saving.value = false;
    }
}

async function handleDelete() {
    if (!confirm('¿Eliminar este negocio?')) return;
    deleting.value = true;
    try {
        await api.delete(`/admin/businesses/${props.id}`);
        emit('deleted');
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <Spinner v-if="loading">Cargando negocio…</Spinner>

    <form v-else class="flex flex-col gap-6" @submit.prevent="handleSubmit">
        <Card>
            <h3 class="mb-1 font-display text-sm font-semibold text-brand-900">Cuenta del dueño</h3>
            <p class="mb-3 text-xs text-sand-500">Alta de clientes: crea o selecciona el usuario que será dueño de este negocio.</p>

            <div v-if="isEdit && !changingOwner" class="flex items-center justify-between rounded-lg bg-sand-100 px-3 py-2">
                <div class="text-sm">
                    <p class="font-medium text-sand-800">{{ currentOwner?.name }}</p>
                    <p class="text-sand-500">{{ currentOwner?.email }}</p>
                </div>
                <button type="button" class="text-sm text-brand-700 hover:underline" @click="changingOwner = true">
                    Cambiar dueño
                </button>
            </div>

            <div v-else class="flex flex-col gap-3">
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-1.5">
                        <input v-model="ownerMode" type="radio" value="new"> Crear dueño nuevo
                    </label>
                    <label class="flex items-center gap-1.5">
                        <input v-model="ownerMode" type="radio" value="existing"> Asignar dueño existente
                    </label>
                </div>

                <template v-if="ownerMode === 'new'">
                    <input
                        v-model="ownerForm.owner_name" type="text" placeholder="Nombre"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                    <p v-if="errors.owner_name" class="text-xs text-amber-700">{{ errors.owner_name[0] }}</p>
                    <input
                        v-model="ownerForm.owner_email" type="email" placeholder="Correo"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                    <p v-if="errors.owner_email" class="text-xs text-amber-700">{{ errors.owner_email[0] }}</p>
                    <input
                        v-model="ownerForm.owner_password" type="text" placeholder="Contraseña"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                    <p v-if="errors.owner_password" class="text-xs text-amber-700">{{ errors.owner_password[0] }}</p>
                </template>

                <template v-else>
                    <select
                        v-model="ownerForm.owner_id"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                        <option value="" disabled>Selecciona un usuario</option>
                        <option v-for="owner in availableOwners" :key="owner.id" :value="owner.id">
                            {{ owner.name }} ({{ owner.email }})
                        </option>
                    </select>
                    <p v-if="errors.owner_id" class="text-xs text-amber-700">{{ errors.owner_id[0] }}</p>
                </template>

                <button v-if="isEdit" type="button" class="self-start text-xs text-sand-500 hover:underline" @click="changingOwner = false">
                    Cancelar cambio de dueño
                </button>
            </div>
        </Card>

        <Card>
            <h3 class="mb-3 font-display text-sm font-semibold text-brand-900">Datos del negocio</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-sand-700">Nombre</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p v-if="errors.name" class="mt-1 text-xs text-amber-700">{{ errors.name[0] }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Tipo de negocio</label>
                    <select v-model="form.type" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <option value="barberia">Barbería</option>
                        <option value="clinica">Clínica estética</option>
                        <option value="restaurante">Restaurante</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Zona horaria</label>
                    <input v-model="form.timezone" type="text" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Dirección</label>
                    <input v-model="form.address" type="text" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Teléfono</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Estado</label>
                    <select v-model="form.status" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <option value="piloto">Piloto</option>
                        <option value="activo">Activo</option>
                        <option value="pausado">Pausado</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Precio mensual (COP)</label>
                    <input
                        v-model="form.monthly_price" type="number" min="1500" step="1000" placeholder="Vacío = sin cobro"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                    <p class="mt-1 text-xs text-sand-400">Lo que el dueño pagará al mes por Wompi. Déjalo vacío para no cobrarle (piloto).</p>
                    <p v-if="errors.monthly_price" class="mt-1 text-xs text-amber-700">{{ errors.monthly_price[0] }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Tono del bot</label>
                    <select v-model="form.tone" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <option value="cercano">Cercano</option>
                        <option value="formal">Formal</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Modelo del agente</label>
                    <select v-model="form.agent_model" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <option value="">Por defecto (Haiku)</option>
                        <option value="claude-sonnet-5">Sonnet (más capaz, más costoso)</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-sand-700">Instrucciones extra para el bot</label>
                    <textarea v-model="form.extra_instructions" rows="2" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                </div>
            </div>
        </Card>

        <Card>
            <h3 class="mb-1 font-display text-sm font-semibold text-brand-900">Conexión de WhatsApp</h3>
            <p class="mb-3 text-xs text-sand-500">
                Datos de la WhatsApp Business Cloud API del negocio (modo Coexistence). Se registran manualmente aquí
                hasta que exista Embedded Signup automatizado.
                <span v-if="currentConnectionStatus" class="font-medium text-brand-700">Estado actual: {{ currentConnectionStatus }}</span>
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Phone Number ID</label>
                    <input v-model="form.whatsapp_phone_number_id" type="text" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">WABA ID</label>
                    <input v-model="form.whatsapp_waba_id" type="text" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Número (E.164)</label>
                    <input v-model="form.whatsapp_phone_e164" type="text" placeholder="+573001234567" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Modo</label>
                    <select v-model="form.whatsapp_mode" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <option value="coexistence">Coexistence</option>
                        <option value="dedicado">Dedicado</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-sand-700">Token de acceso</label>
                    <input
                        v-model="form.whatsapp_access_token" type="password"
                        :placeholder="isEdit ? 'Dejar vacío para no cambiarlo' : ''"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                </div>
            </div>
        </Card>

        <div class="flex items-center justify-between">
            <Button v-if="isEdit" type="button" variant="danger" :disabled="deleting" @click="handleDelete">
                {{ deleting ? 'Eliminando…' : 'Eliminar negocio' }}
            </Button>
            <span v-else />
            <Button type="submit" :disabled="saving">{{ saving ? 'Guardando…' : (isEdit ? 'Guardar cambios' : 'Crear negocio') }}</Button>
        </div>
    </form>
</template>
