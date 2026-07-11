import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/',
        name: 'landing',
        component: () => import('../pages/Landing.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('../pages/Login.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/terminos',
        name: 'terms',
        component: () => import('../pages/Terms.vue'),
    },
    {
        path: '/privacidad',
        name: 'privacy',
        component: () => import('../pages/Privacy.vue'),
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('../pages/ForgotPassword.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('../pages/ResetPassword.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('../pages/DashboardHome.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/conversations',
        name: 'conversations',
        component: () => import('../pages/ConversationsInbox.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/appointments',
        name: 'appointments',
        component: () => import('../pages/AppointmentsCalendar.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('../pages/Settings.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/support',
        name: 'support',
        component: () => import('../pages/Support.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/subscription',
        name: 'subscription',
        component: () => import('../pages/Subscription.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/admin/businesses',
        name: 'admin.businesses',
        component: () => import('../pages/admin/AdminBusinessesIndex.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/businesses/create',
        name: 'admin.businesses.create',
        component: () => import('../pages/admin/AdminBusinessesIndex.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/businesses/:id/edit',
        name: 'admin.businesses.edit',
        component: () => import('../pages/admin/AdminBusinessesIndex.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/support',
        name: 'admin.support',
        component: () => import('../pages/admin/AdminSupport.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/metrics',
        name: 'admin.metrics',
        component: () => import('../pages/admin/AdminMetrics.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/system-health',
        name: 'admin.system-health',
        component: () => import('../pages/admin/AdminSystemHealth.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    { path: '/:pathMatch(.*)*', redirect: { name: 'landing' } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.ready) {
        await auth.fetchUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return auth.user?.role === 'super_admin' ? { name: 'admin.businesses' } : { name: 'dashboard' };
    }

    if (to.meta.requiresAdmin && auth.user?.role !== 'super_admin') {
        return { name: 'dashboard' };
    }

    if (to.meta.requiresAuth && !to.meta.requiresAdmin && auth.user?.role === 'super_admin') {
        return { name: 'admin.businesses' };
    }

    // Paywall: un owner sin suscripción activa solo puede ver la página de
    // suscripción hasta completar el pago.
    // Soporte queda accesible incluso sin suscripción activa: un usuario con
    // problemas de pago debe poder reportarlos.
    if (to.meta.requiresAuth && auth.user?.subscription_required && !['subscription', 'support'].includes(to.name)) {
        return { name: 'subscription' };
    }

    return true;
});

export default router;
