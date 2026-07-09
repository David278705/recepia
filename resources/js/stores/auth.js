import { defineStore } from 'pinia';
import api, { ensureCsrfCookie } from '../lib/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        ready: false,
    }),
    getters: {
        isAuthenticated: (state) => state.user !== null,
        isImpersonating: (state) => Boolean(state.user?.impersonating),
    },
    actions: {
        async fetchUser() {
            try {
                const { data } = await api.get('/user');
                this.user = data.user;
            } catch {
                this.user = null;
            } finally {
                this.ready = true;
            }
        },
        async login(credentials) {
            await ensureCsrfCookie();
            const { data } = await api.post('/login', credentials);
            this.user = data.user;
        },
        async register(payload) {
            await ensureCsrfCookie();
            const { data } = await api.post('/register', payload);
            this.user = data.user;
        },
        async logout() {
            await api.post('/logout');
            this.user = null;
        },
        async stopImpersonating() {
            const { data } = await api.post('/stop-impersonating');
            this.user = data.user;
        },
    },
});
