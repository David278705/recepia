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
    type: 'otro',
    address: '',
    phone: '',
    timezone: '',
    tone: 'cercano',
    extra_instructions: '',
});

onMounted(async () => {
    const { data } = await api.get('/business');
    const b = data.data;
    form.name = b.name;
    form.type = b.type ?? 'otro';
    form.address = b.address ?? '';
    form.phone = b.phone ?? '';
    form.timezone = b.timezone ?? '';
    form.tone = b.tone ?? 'cercano';
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
                    <select
                        v-model="form.type"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                        <option value="barberia">Barbería</option>
                        <option value="clinica">Clínica estética</option>
                        <option value="restaurante">Restaurante</option>
                        <option value="otro">Otro</option>
                    </select>
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
                    <label class="mb-1 block text-sm font-medium text-sand-700">Tono del bot</label>
                    <select
                        v-model="form.tone"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                        <option value="cercano">Cercano</option>
                        <option value="formal">Formal</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Instrucciones extra para el bot</label>
                    <textarea
                        v-model="form.extra_instructions"
                        rows="3"
                        placeholder="Cosas que el bot debe saber o tener en cuenta al responder…"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar cambios' }}</Button>
                    <span v-if="saved" class="text-sm text-brand-700">Guardado.</span>
                </div>
            </form>
        </Card>
    </div>
</template>
