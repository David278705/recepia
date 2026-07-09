<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { MagnifyingGlassIcon, BuildingStorefrontIcon } from '@heroicons/vue/24/outline';
import api from '../../lib/api';
import { useAuthStore } from '../../stores/auth';
import Card from '../../components/Card.vue';
import Badge from '../../components/Badge.vue';
import Spinner from '../../components/Spinner.vue';
import EmptyState from '../../components/EmptyState.vue';
import SlideOver from '../../components/SlideOver.vue';
import AdminBusinessFormFields from './AdminBusinessFormFields.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const businesses = ref([]);
const loading = ref(true);
const search = ref('');
const activeOnly = ref(false); // "activos" = negocios con status distinto de pausado
const formRef = ref(null);
const impersonatingId = ref(null);

const slideOpen = computed(() => ['admin.businesses.create', 'admin.businesses.edit'].includes(route.name));
const slideTitle = computed(() => (route.name === 'admin.businesses.edit' ? 'Editar negocio' : 'Nuevo negocio'));
const editId = computed(() => (route.name === 'admin.businesses.edit' ? route.params.id : null));

const statusLabels = { piloto: 'Piloto', activo: 'Activo', pausado: 'Pausado' };
const statusTones = { piloto: 'amber', activo: 'brand', pausado: 'sand' };
const connectionLabels = { conectado: 'WhatsApp conectado', pendiente: 'WhatsApp pendiente', error: 'Error de conexión' };
const connectionTones = { conectado: 'brand', pendiente: 'amber', error: 'urgent' };

async function load() {
    loading.value = true;
    const { data } = await api.get('/admin/businesses');
    businesses.value = data.data;
    loading.value = false;
}

onMounted(load);

watch(editId, () => {
    if (slideOpen.value) formRef.value?.load();
});

function closeSlide() {
    router.push({ name: 'admin.businesses' });
}

function handleSaved() {
    closeSlide();
    load();
}

async function impersonate(business) {
    impersonatingId.value = business.id;
    try {
        await api.post(`/admin/businesses/${business.id}/impersonate`);
        await auth.fetchUser();
        router.push({ name: 'dashboard' });
    } finally {
        impersonatingId.value = null;
    }
}

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    return businesses.value.filter((business) => {
        if (activeOnly.value && business.status === 'pausado') return false;
        if (!term) return true;

        return business.name.toLowerCase().includes(term) || business.owner?.name?.toLowerCase().includes(term);
    });
});
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between">
            <h1 class="font-display text-2xl font-semibold text-brand-900">Panel de negocios</h1>
            <router-link
                :to="{ name: 'admin.businesses.create' }"
                class="rounded-lg bg-brand-800 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-900"
            >
                + Nuevo negocio
            </router-link>
        </div>
        <p class="mb-6 text-sm text-sand-500">
            Todos los negocios dados de alta. Da de alta un cliente nuevo o entra a uno para editarlo.
        </p>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="relative">
                <MagnifyingGlassIcon class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-sand-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por negocio o dueño…"
                    class="w-64 rounded-lg border border-sand-200 py-2 pr-3 pl-9 text-sm focus:border-brand-500 focus:outline-none"
                >
            </div>
            <label class="flex items-center gap-1.5 text-sm text-sand-600">
                <input v-model="activeOnly" type="checkbox" class="rounded border-sand-300 text-brand-700 focus:ring-brand-500">
                Solo activos
            </label>
        </div>

        <Spinner v-if="loading">Cargando negocios…</Spinner>

        <EmptyState
            v-else-if="!businesses.length"
            :icon="BuildingStorefrontIcon"
            title="Todavía no hay negocios dados de alta"
            description="Crea el primero con '+ Nuevo negocio' — llenas sus datos y creas la cuenta de su dueño en el mismo formulario."
        />

        <EmptyState
            v-else-if="!filtered.length"
            :icon="MagnifyingGlassIcon"
            title="Sin resultados"
            description="Ningún negocio coincide con la búsqueda o los filtros aplicados."
        />

        <div v-else class="flex flex-col gap-3">
            <Card v-for="business in filtered" :key="business.id" class="transition hover:border-brand-300 hover:shadow-md">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <router-link :to="{ name: 'admin.businesses.edit', params: { id: business.id } }" class="min-w-0">
                        <h2 class="font-display font-semibold text-brand-900">{{ business.name }}</h2>
                        <p class="text-sm text-sand-500">
                            {{ business.owner?.name ?? 'Sin dueño' }}
                            <span v-if="business.type">· {{ business.type }}</span>
                        </p>
                    </router-link>

                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :tone="statusTones[business.status] ?? 'sand'">{{ statusLabels[business.status] ?? business.status }}</Badge>
                        <Badge :tone="connectionTones[business.whatsapp_account?.connection_status] ?? 'sand'">
                            {{ connectionLabels[business.whatsapp_account?.connection_status] ?? 'Sin WhatsApp' }}
                        </Badge>
                        <Badge v-if="business.pending_escalations_count" tone="urgent">{{ business.pending_escalations_count }} escalada{{ business.pending_escalations_count === 1 ? '' : 's' }}</Badge>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-sand-100 pt-3">
                    <p class="text-xs text-sand-500">
                        {{ business.messages_this_month_count ?? 0 }} mensajes este mes ·
                        costo API ${{ Number(business.cost_this_month ?? 0).toFixed(2) }}
                    </p>
                    <button
                        type="button"
                        class="text-xs font-medium text-brand-700 hover:underline disabled:opacity-50"
                        :disabled="!business.owner || impersonatingId === business.id"
                        @click="impersonate(business)"
                    >
                        {{ impersonatingId === business.id ? 'Entrando…' : 'Impersonar dueño' }}
                    </button>
                </div>
            </Card>
        </div>

        <SlideOver :open="slideOpen" :title="slideTitle" @close="closeSlide">
            <AdminBusinessFormFields
                v-if="slideOpen"
                ref="formRef"
                :id="editId"
                @saved="handleSaved"
                @deleted="handleSaved"
            />
        </SlideOver>
    </div>
</template>
