<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api, { ensureCsrfCookie } from '../lib/api';
import BrandMark from '../components/BrandMark.vue';

const route = useRoute();

// Autorización: token del link firmado (dueños) o business_id (super_admin
// navegando desde su panel con sesión activa).
const onboardingToken = route.query.token ?? '';
const businessId = route.query.business ?? '';

const config = ref(null);
const sdkReady = ref(false);
const launching = ref(false);

// Datos que entrega el popup.
const signupCode = ref('');
const phoneNumberId = ref('');
const wabaId = ref('');

// Máquina de estados de la pantalla.
const phase = ref('idle'); // idle | popup | processing | done | error | cancelled
const progressStep = ref('');
const result = ref(null);
const errorMessage = ref('');
const needsOverwrite = ref(false);

const canStart = computed(() => sdkReady.value && config.value?.ready && (onboardingToken || businessId));

const ELIGIBILITY_TIPS = [
    'Tu app de WhatsApp Business debe estar actualizada (versión 2.24.17 o superior).',
    'El número debe llevar al menos ~7 días de uso real en la app de WhatsApp Business.',
    'Si el número estuvo conectado antes a otra plataforma (otra WABA), Meta exige un periodo de espera de 1 a 2 meses.',
    'Revisa que el nombre de tu negocio en la app sea el definitivo: después de conectar queda bloqueado.',
    'Algunas cuentas o países aún no están habilitados por Meta para este flujo.',
];

function handleSessionInfo(event) {
    if (typeof event.data !== 'string' || !event.origin.includes('facebook.com')) return;

    let data;
    try {
        data = JSON.parse(event.data);
    } catch {
        return;
    }

    if (data.type !== 'WA_EMBEDDED_SIGNUP') return;

    // FINISH clásico o FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING (coexistencia).
    if (String(data.event).startsWith('FINISH')) {
        phoneNumberId.value = data.data?.phone_number_id ?? '';
        wabaId.value = data.data?.waba_id ?? '';
    } else if (data.event === 'CANCEL') {
        phase.value = 'cancelled';
        errorMessage.value = data.data?.current_step
            ? `El proceso se cerró en el paso "${data.data.current_step}".`
            : 'El proceso se cerró antes de terminar.';
    } else if (data.event === 'ERROR') {
        phase.value = 'error';
        errorMessage.value = data.data?.error_message ?? 'Meta reportó un error durante la conexión.';
    }
}

function launchSignup() {
    if (!window.FB || launching.value) return;

    launching.value = true;
    phase.value = 'popup';
    errorMessage.value = '';
    needsOverwrite.value = false;

    window.FB.login(
        (response) => {
            launching.value = false;

            if (response.authResponse?.code) {
                signupCode.value = response.authResponse.code;
                completeOnboarding();
            } else if (phase.value === 'popup') {
                phase.value = 'cancelled';
                errorMessage.value = 'No recibimos la autorización de Meta. Puedes volver a intentarlo.';
            }
        },
        {
            config_id: config.value.config_id,
            response_type: 'code',
            override_default_response_type: true,
            extras: {
                setup: {},
                // Habilita la variante de coexistencia para números que ya
                // viven en la app de WhatsApp Business (doc oficial:
                // "Onboarding business app users").
                featureType: 'whatsapp_business_app_onboarding',
                sessionInfoVersion: '3',
            },
        },
    );
}

async function completeOnboarding(overwrite = false) {
    if (!signupCode.value) return;

    if (!phoneNumberId.value || !wabaId.value) {
        phase.value = 'error';
        errorMessage.value = 'El popup no nos entregó los datos del número. Cierra esta página y vuelve a intentar el flujo completo.';
        return;
    }

    phase.value = 'processing';

    try {
        progressStep.value = 'Canjeando autorización con Meta…';
        await ensureCsrfCookie();

        progressStep.value = 'Suscribiendo webhooks y verificando el número…';
        const { data } = await api.post('/whatsapp/onboarding/complete', {
            code: signupCode.value,
            phone_number_id: phoneNumberId.value,
            waba_id: wabaId.value,
            ...(onboardingToken ? { onboarding_token: onboardingToken } : {}),
            ...(businessId ? { business_id: Number(businessId) } : {}),
            ...(overwrite ? { overwrite: true } : {}),
        });

        result.value = data.data;
        phase.value = 'done';
    } catch (error) {
        if (error.response?.status === 409) {
            needsOverwrite.value = true;
            phase.value = 'error';
            errorMessage.value = error.response.data.message;
            return;
        }

        phase.value = 'error';
        errorMessage.value = error.response?.data?.message ?? 'No pudimos completar la conexión. Inténtalo de nuevo.';
    }
}

onMounted(async () => {
    window.addEventListener('message', handleSessionInfo);

    // Retorno de la variante por redirect (callback OAuth): el backend ya
    // completó (o falló) el aprovisionamiento y nos manda el resultado.
    if (route.query.status === 'success') {
        result.value = { phone: route.query.phone, verified_name: '', mode: route.query.mode };
        phase.value = 'done';
        return;
    }
    if (route.query.status === 'error') {
        phase.value = 'error';
        errorMessage.value = route.query.message || 'No pudimos completar la conexión.';
        return;
    }

    const { data } = await api.get('/whatsapp/onboarding/config');
    config.value = data;

    if (!data.ready) return;

    window.fbAsyncInit = function () {
        window.FB.init({
            appId: data.app_id,
            autoLogAppEvents: true,
            xfbml: true,
            version: data.graph_version,
        });
        sdkReady.value = true;
    };

    if (!document.getElementById('facebook-jssdk')) {
        const script = document.createElement('script');
        script.id = 'facebook-jssdk';
        script.src = 'https://connect.facebook.net/en_US/sdk.js';
        script.async = true;
        script.defer = true;
        script.crossOrigin = 'anonymous';
        document.body.appendChild(script);
    } else if (window.FB) {
        sdkReady.value = true;
    }
});

onUnmounted(() => window.removeEventListener('message', handleSessionInfo));
</script>

<template>
    <div class="min-h-screen bg-sand-50">
        <header class="border-b border-sand-200 bg-white">
            <div class="mx-auto flex max-w-2xl items-center gap-2.5 px-5 py-5 sm:px-8">
                <BrandMark :size="40" />
                <span class="font-display text-lg font-semibold tracking-tight text-brand-950">pilo</span>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-5 py-12 sm:px-8">
            <h1 class="font-display text-3xl font-semibold tracking-tight text-brand-950">Conecta tu WhatsApp</h1>
            <p class="mt-3 text-sand-600">
                Vas a conectar el número de WhatsApp de tu negocio con Pilo usando el proceso oficial de Meta.
                Sigues usando tu app de WhatsApp Business con normalidad — Pilo atiende en paralelo, en el mismo número.
            </p>

            <!-- Sin autorización -->
            <div v-if="!onboardingToken && !businessId" class="mt-8 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Este enlace no es válido. Pide a tu asesor de Pilo un enlace de conexión nuevo.
            </div>

            <!-- Config incompleta -->
            <div v-else-if="config && !config.ready" class="mt-8 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                La conexión automática aún no está habilitada en la plataforma (falta configuración de Meta). Contáctanos para hacer la conexión asistida.
            </div>

            <template v-else>
                <!-- Antes de empezar -->
                <div v-if="phase === 'idle' || phase === 'popup' || phase === 'cancelled'" class="mt-8">
                    <div class="rounded-2xl border border-sand-200 bg-white p-6">
                        <h2 class="font-display text-lg font-semibold text-brand-950">Antes de empezar, ten a mano:</h2>
                        <ul class="mt-3 flex list-disc flex-col gap-1.5 pl-5 text-sm text-sand-600">
                            <li>Tu cuenta de Facebook (con acceso al negocio).</li>
                            <li>Tu celular con la app <strong>WhatsApp Business</strong> abierta y actualizada — al final escanearás un código QR.</li>
                            <li>5 minutos sin interrupciones.</li>
                        </ul>

                        <p v-if="phase === 'cancelled'" class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ errorMessage }}</p>

                        <button
                            type="button"
                            :disabled="!canStart || launching"
                            class="mt-6 inline-flex items-center justify-center rounded-xl bg-brand-800 px-7 py-3.5 text-base font-semibold text-white shadow-md transition hover:bg-brand-900 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="launchSignup"
                        >
                            {{ launching || phase === 'popup' ? 'Esperando a Meta…' : (phase === 'cancelled' ? 'Volver a intentar' : 'Conectar con Facebook') }}
                        </button>
                        <p v-if="!sdkReady && config?.ready" class="mt-2 text-xs text-sand-400">Cargando el módulo de Meta…</p>
                    </div>
                </div>

                <!-- Progreso -->
                <div v-else-if="phase === 'processing'" class="mt-8 rounded-2xl border border-sand-200 bg-white p-6">
                    <div class="flex items-center gap-3">
                        <span class="h-5 w-5 animate-spin rounded-full border-2 border-brand-200 border-t-brand-700" />
                        <p class="text-sm font-medium text-brand-900">{{ progressStep }}</p>
                    </div>
                    <p class="mt-3 text-xs text-sand-400">No cierres esta página.</p>
                </div>

                <!-- Éxito -->
                <div v-else-if="phase === 'done'" class="mt-8 rounded-2xl border border-brand-200 bg-brand-50 p-6">
                    <h2 class="font-display text-lg font-semibold text-brand-900">¡Listo! Tu WhatsApp quedó conectado 🎉</h2>
                    <p class="mt-2 text-sm text-brand-800">
                        Número {{ result?.phone }}<template v-if="result?.verified_name"> ({{ result.verified_name }})</template> conectado en modo
                        {{ result?.mode === 'coexistence' ? 'coexistencia: sigues usando tu app como siempre' : 'dedicado' }}.
                    </p>
                    <p class="mt-3 text-sm text-brand-800">
                        Importante: abre tu app de WhatsApp Business al menos una vez cada dos semanas para que la conexión se mantenga activa.
                    </p>
                </div>

                <!-- Error -->
                <div v-else class="mt-8 rounded-2xl border border-amber-300 bg-white p-6">
                    <h2 class="font-display text-lg font-semibold text-amber-800">No pudimos completar la conexión</h2>
                    <p class="mt-2 text-sm text-sand-700">{{ errorMessage }}</p>

                    <button
                        v-if="needsOverwrite"
                        type="button"
                        class="mt-4 rounded-xl bg-brand-800 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-900"
                        @click="completeOnboarding(true)"
                    >
                        Sí, reemplazar la conexión anterior
                    </button>

                    <template v-else>
                        <p class="mt-4 text-sm font-medium text-sand-700">Causas comunes:</p>
                        <ul class="mt-2 flex list-disc flex-col gap-1.5 pl-5 text-sm text-sand-600">
                            <li v-for="tip in ELIGIBILITY_TIPS" :key="tip">{{ tip }}</li>
                        </ul>
                        <button
                            type="button"
                            class="mt-5 rounded-xl border border-sand-300 px-5 py-2.5 text-sm font-semibold text-sand-700 transition hover:bg-sand-50"
                            @click="phase = 'idle'"
                        >
                            Intentar de nuevo
                        </button>
                    </template>
                </div>
            </template>
        </main>
    </div>
</template>
