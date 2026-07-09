<script setup>
import { useRoute } from 'vue-router';
import AppLayout from './components/AppLayout.vue';
import AdminLayout from './components/AdminLayout.vue';

const route = useRoute();
</script>

<template>
    <AdminLayout v-if="route.meta.requiresAdmin">
        <router-view v-slot="{ Component }">
            <Transition name="page" mode="out-in">
                <component :is="Component" />
            </Transition>
        </router-view>
    </AdminLayout>
    <AppLayout v-else-if="route.meta.requiresAuth">
        <router-view v-slot="{ Component }">
            <Transition name="page" mode="out-in">
                <component :is="Component" />
            </Transition>
        </router-view>
    </AppLayout>
    <router-view v-else />
</template>
