<script setup>
import { reactive, ref } from 'vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../lib/api';
import Card from '../Card.vue';
import Button from '../Button.vue';

const auth = useAuthStore();

const form = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const errors = ref({});
const saving = ref(false);
const saved = ref(false);

async function handleSubmit() {
    saving.value = true;
    saved.value = false;
    errors.value = {};
    try {
        await api.put('/password', form);
        saved.value = true;
        form.current_password = '';
        form.password = '';
        form.password_confirmation = '';
    } catch (error) {
        errors.value = error.response?.data?.errors ?? {};
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="flex max-w-xl flex-col gap-6">
        <Card>
            <h2 class="mb-1 text-sm font-semibold text-brand-900">Tu cuenta</h2>
            <p class="text-sm text-sand-600">{{ auth.user?.name }} · {{ auth.user?.email }}</p>
        </Card>

        <Card>
            <h2 class="mb-4 text-sm font-semibold text-brand-900">Cambiar contraseña</h2>
            <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Contraseña actual</label>
                    <input
                        v-model="form.current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                    <p v-if="errors.current_password" class="mt-1 text-xs text-amber-700">{{ errors.current_password[0] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Nueva contraseña</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                    <p v-if="errors.password" class="mt-1 text-xs text-amber-700">{{ errors.password[0] }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-sand-700">Confirma la nueva contraseña</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                    >
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="saving">{{ saving ? 'Guardando…' : 'Cambiar contraseña' }}</Button>
                    <span v-if="saved" class="text-sm text-brand-700">Contraseña actualizada.</span>
                </div>
            </form>
        </Card>
    </div>
</template>
