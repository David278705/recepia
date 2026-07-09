import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

// Paywall: si el backend responde 402 (suscripción vencida a mitad de sesión),
// llevamos al usuario a la página de suscripción.
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 402 && window.location.pathname !== '/subscription') {
            window.location.assign('/subscription');
        }

        return Promise.reject(error);
    }
);

export function ensureCsrfCookie() {
    return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

export default api;
