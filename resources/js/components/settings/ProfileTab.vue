<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../lib/api';
import Card from '../Card.vue';
import Button from '../Button.vue';
import Spinner from '../Spinner.vue';

const loading = ref(true);
const saving = ref(false);
const saved = ref(false);
const errors = ref({});

const form = reactive({
    name: '',
    type: '',
    description: '',
    address: '',
    phone: '',
    timezone: '',
    tone: 'cercano',
    show_brand: false,
    extra_instructions: '',
});

onMounted(async () => {
    const { data } = await api.get('/business');
    const b = data.data;
    form.name = b.name;
    form.type = b.type ?? '';
    form.description = b.description ?? '';
    form.address = b.address ?? '';
    form.phone = b.phone ?? '';
    form.timezone = b.timezone ?? '';
    form.tone = b.tone ?? 'cercano';
    form.show_brand = Boolean(b.show_brand);
    form.extra_instructions = b.extra_instructions ?? '';
    loading.value = false;
});

async function handleSubmit() {
    saving.value = true;
    saved.value = false;
    errors.value = {};
    try {
        await api.put('/business', form);
        saved.value = true;
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="max-w-xl">
        <Spinner v-if="loading">Cargando…</Spinner>

        <Card v-else>
            <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Nombre del negocio</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                    <p v-if="errors.name" class="mt-1 text-xs text-amber-700">{{ errors.name[0] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Tipo de negocio</label>
                    <input
                        v-model="form.type"
                        type="text"
                        list="profile-business-types"
                        maxlength="100"
                        required
                        placeholder="barbería, veterinaria, taller…"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                    <datalist id="profile-business-types">
                        <option value="barbería" />
                        <option value="peluquería / salón de belleza" />
                        <option value="clínica estética" />
                        <option value="consultorio médico" />
                        <option value="odontología" />
                        <option value="veterinaria" />
                        <option value="restaurante" />
                        <option value="taller mecánico" />
                        <option value="spa" />
                        <option value="estudio de tatuajes" />
                    </datalist>
                    <p v-if="errors.type" class="mt-1 text-xs text-amber-700">{{ errors.type[0] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Descripción del negocio</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        maxlength="2000"
                        placeholder="Qué hace tu negocio, qué lo distingue… Tu recepcionista usa esto para presentarte mejor."
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    />
                    <p v-if="errors.description" class="mt-1 text-xs text-amber-700">{{ errors.description[0] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Dirección</label>
                    <input
                        v-model="form.address"
                        type="text"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Teléfono</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Zona horaria</label>
                    <input
                        v-model="form.timezone"
                        type="text"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Tono de Pilo</label>
                    <select
                        v-model="form.tone"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                        <option value="cercano">Cercano</option>
                        <option value="formal">Formal</option>
                    </select>
                </div>

                <label class="flex items-start gap-2 text-sm text-sand-700">
                    <input v-model="form.show_brand" type="checkbox" class="mt-0.5 rounded border-sand-300 text-brand-700 focus:ring-brand-500">
                    <span>
                        Presentar a Pilo con su nombre ante tus clientes
                        <span class="block text-xs text-sand-400">Apagado: "Soy el asistente de {{ form.name || 'tu negocio' }}". Encendido: "Soy Pilo, el asistente de {{ form.name || 'tu negocio' }}".</span>
                    </span>
                </label>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Instrucciones extra para Pilo</label>
                    <textarea
                        v-model="form.extra_instructions"
                        rows="3"
                        placeholder="Cosas que Pilo debe saber o tener en cuenta al responder…"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar cambios' }}</Button>
                    <span v-if="saved" class="text-sm text-brand-700">Listo, Pilo ya atiende con los cambios.</span>
                </div>
            </form>
        </Card>
    </div>
</template>
