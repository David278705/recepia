<script setup>
import { BuildingStorefrontIcon, ChartBarIcon, HeartIcon, LifebuoyIcon } from '@heroicons/vue/24/outline';
import SidebarLayout from './SidebarLayout.vue';

const navItems = [
    { name: 'admin.businesses', label: 'Panel de negocios', icon: BuildingStorefrontIcon },
    { name: 'admin.metrics', label: 'Métricas y costos', icon: ChartBarIcon },
    { name: 'admin.system-health', label: 'Salud del sistema', icon: HeartIcon },
    { name: 'admin.support', label: 'Soporte', icon: LifebuoyIcon },
];
</script>

<template>
    <SidebarLayout :nav-items="navItems" home-route="admin.businesses" brand-suffix="admin">
        <slot />
    </SidebarLayout>
</template>
