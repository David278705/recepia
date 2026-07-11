<script setup>
import { HomeIcon, ChatBubbleLeftRightIcon, CalendarDaysIcon, Cog6ToothIcon, CreditCardIcon, LifebuoyIcon } from '@heroicons/vue/24/outline';
import SidebarLayout from './SidebarLayout.vue';

const navItems = [
    { name: 'dashboard', label: 'Panel principal', icon: HomeIcon },
    { name: 'conversations', label: 'Conversaciones', icon: ChatBubbleLeftRightIcon },
    { name: 'appointments', label: 'Citas', icon: CalendarDaysIcon },
    { name: 'settings', label: 'Configuración', icon: Cog6ToothIcon },
    { name: 'subscription', label: 'Suscripción', icon: CreditCardIcon },
    { name: 'support', label: 'Soporte', icon: LifebuoyIcon },
];
</script>

<template>
    <SidebarLayout :nav-items="navItems" home-route="dashboard">
        <slot />
    </SidebarLayout>
</template>
