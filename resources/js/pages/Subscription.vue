<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    CreditCardIcon,
    DevicePhoneMobileIcon,
    BuildingLibraryIcon,
    ShieldCheckIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ClockIcon,
    ArrowPathIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import api from '../lib/api';
import { useAuthStore } from '../stores/auth';
import Card from '../components/Card.vue';
import Badge from '../components/Badge.vue';
import Button from '../components/Button.vue';
import Spinner from '../components/Spinner.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const loading = ref(true);
const info = ref(null);
const error = ref('');
const success = ref('');
const paying = ref(false);
const managing = ref(false);
const method = ref('tarjeta');
const accepted = ref(false);
const nequiWaiting = ref(false);

const banks = ref([]);
const banksLoading = ref(false);

const form = ref({
    phone: '',
    legal_id_type: 'CC',
    legal_id: '',
    user_type: '0',
    financial_institution_code: '',
    full_name: '',
});

const cardForm = ref({ number: '', card_holder: '', expiry: '', cvc: '' });

const statusLabels = {
    pendiente: { text: 'Pago en proceso', tone: 'amber' },
    activa: { text: 'Activa', tone: 'brand' },
    vencida: { text: 'Vencida', tone: 'urgent' },
    cancelada: { text: 'Cancelada', tone: 'sand' },
};

const paymentLabels = {
    APPROVED: { text: 'Aprobado', tone: 'brand' },
    PENDING: { text: 'En proceso', tone: 'amber' },
    DECLINED: { text: 'Rechazado', tone: 'urgent' },
    VOIDED: { text: 'Anulado', tone: 'sand' },
    ERROR: { text: 'Error', tone: 'urgent' },
};

const methods = [
    { key: 'tarjeta', label: 'Tarjeta', icon: CreditCardIcon, hint: 'Se cobra sola cada mes. No tienes que acordarte de nada.' },
    { key: 'nequi', label: 'Nequi', icon: DevicePhoneMobileIcon, hint: 'Aceptas la notificación en tu app. Sin salir de aquí.' },
    { key: 'daviplata', label: 'DaviPlata', icon: DevicePhoneMobileIcon, hint: 'Confirmas con un código que llega a tu celular.' },
    { key: 'pse', label: 'PSE', icon: BuildingLibraryIcon, hint: 'Pagas desde la app o web de tu banco.' },
];

const money = (cents) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format((cents ?? 0) / 100);

const dateLong = (iso) =>
    iso ? new Date(iso).toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' }) : '';

const hasAccess = computed(() => info.value?.has_access);
const subscription = computed(() => info.value?.subscription);
const needsPayment = computed(() => info.value?.requires_subscription && !hasAccess.value);
const paymentDue = computed(() => Boolean(subscription.value?.payment_due));
const pendingPayment = computed(() => Boolean(subscription.value?.has_pending_payment));
// También se muestra cuando el dueño quiere cambiar de tarjeta o activar el
// cobro automático teniendo el periodo vigente (showCardForm).
const showCardForm = ref(false);
const showPaymentSection = computed(() => !pendingPayment.value && (needsPayment.value || paymentDue.value || showCardForm.value));

// Una sola mascota por pantalla, según el momento: saluda al llegar a pagar,
// piensa mientras se confirma, corre cuando el plazo aprieta y celebra cuando
// todo está al día.
const mascot = computed(() => {
    if (!info.value?.requires_subscription) return '/img/robot_pulgar_arriba.png';
    if (pendingPayment.value) return '/img/robot_pensando_duda.png';
    if (paymentDue.value) return '/img/robot_corriendo.png';
    if (hasAccess.value) return '/img/robot_pulgar_arriba.png';
    return '/img/robot_saludo_mano.png';
});

const daysLeftToPay = computed(() => {
    const until = subscription.value?.access_until;
    if (!until) return null;
    return Math.max(0, Math.ceil((new Date(until).getTime() - Date.now()) / 86400000));
});

const formValid = computed(() => {
    if (!accepted.value) return false;
    const f = form.value;
    if (method.value === 'tarjeta') {
        const c = cardForm.value;
        return c.number.replace(/\s/g, '').length >= 13
            && c.card_holder.trim().length > 2
            && /^\d{2}\s?\/\s?\d{2}$/.test(c.expiry)
            && /^\d{3,4}$/.test(c.cvc);
    }
    if (method.value === 'nequi') return /^3\d{9}$/.test(f.phone);
    if (method.value === 'daviplata') return /^\d{5,15}$/.test(f.legal_id);
    return /^\d{5,15}$/.test(f.legal_id)
        && f.financial_institution_code !== ''
        && f.full_name.trim().length > 2
        && /^3\d{9}$/.test(f.phone);
});

function formatCardNumber() {
    cardForm.value.number = cardForm.value.number.replace(/\D/g, '').slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ');
}

function formatExpiry() {
    const digits = cardForm.value.expiry.replace(/\D/g, '').slice(0, 4);
    cardForm.value.expiry = digits.length > 2 ? digits.slice(0, 2) + ' / ' + digits.slice(2) : digits;
}

// Los bancos de PSE se cargan la primera vez que el dueño elige ese método.
watch(method, async (value) => {
    if (value !== 'pse' || banks.value.length || banksLoading.value) return;
    banksLoading.value = true;
    try {
        const { data } = await api.get('/subscription/banks');
        banks.value = data.data;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'No pudimos cargar la lista de bancos.';
    } finally {
        banksLoading.value = false;
    }
});

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/subscription');
        info.value = data.data;
        if (pendingPayment.value) {
            pollWhilePending();
        }
    } catch {
        error.value = 'No pudimos cargar tu suscripción. Recarga la página.';
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    // Volviendo del banco (PSE) o de la OTP (DaviPlata), Wompi agrega
    // ?id=<transacción>: la confirmamos contra la API antes de pintar.
    const transactionId = route.query.id;

    if (transactionId) {
        try {
            await api.post('/subscription/confirm', { transaction_id: transactionId });
        } catch {
            // El polling de load() seguirá verificándolo.
        }
        router.replace({ query: {} });
        await auth.fetchUser();
    }

    await load();
});

onUnmounted(() => clearTimeout(pollTimer));

async function pay() {
    if (!formValid.value || paying.value) return;
    paying.value = true;
    error.value = '';
    success.value = '';

    // Tarjeta: alta de cobro recurrente (la tarjeta se tokeniza en nuestro
    // backend y queda guardada para cobrar cada mes automáticamente).
    if (method.value === 'tarjeta') {
        try {
            const [expMonth, expYear] = cardForm.value.expiry.split('/').map((s) => s.trim());
            const { data } = await api.post('/subscription/subscribe', {
                card_number: cardForm.value.number.replace(/\s/g, ''),
                cvc: cardForm.value.cvc,
                exp_month: expMonth,
                exp_year: expYear,
                card_holder: cardForm.value.card_holder.trim(),
            });

            info.value = data.data;
            showCardForm.value = false;
            cardForm.value = { number: '', card_holder: '', expiry: '', cvc: '' };
            success.value = hasAccess.value
                ? '¡Listo! Tu tarjeta quedó guardada y la suscripción se renovará sola cada mes.'
                : 'Recibimos tu pago y está en proceso de confirmación. Esta página se actualizará sola.';

            await auth.fetchUser();

            if (pendingPayment.value || subscription.value?.status === 'pendiente') {
                pollWhilePending();
            }
        } catch (e) {
            const validation = e.response?.data?.errors;
            error.value = validation
                ? Object.values(validation).flat().join(' ')
                : e.response?.data?.message ?? 'No pudimos procesar el pago. Verifica los datos de tu tarjeta.';
        } finally {
            paying.value = false;
        }

        return;
    }

    try {
        const { data } = await api.post('/subscription/pay', { method: method.value, ...form.value });

        // PSE y DaviPlata: se completa el pago en la página del banco/OTP.
        if (data.redirect_url) {
            window.location.assign(data.redirect_url);
            return;
        }

        info.value = data.data;

        if (method.value === 'nequi') {
            nequiWaiting.value = true;
            success.value = 'Te enviamos una notificación a tu app Nequi: ábrela y acepta el pago. Esta página se actualizará sola.';
        } else {
            success.value = 'Tu pago quedó en proceso de confirmación. Esta página se actualizará sola.';
        }

        pollWhilePending();
    } catch (e) {
        const validation = e.response?.data?.errors;
        error.value = validation
            ? Object.values(validation).flat().join(' ')
            : e.response?.data?.message ?? 'No pudimos iniciar el pago. Inténtalo de nuevo.';
    } finally {
        paying.value = false;
    }
}

// Mientras un pago esté "en proceso", refrescamos cada 5 s: el backend
// consulta el estado real en Wompi en cada refresco (webhook + respaldo).
let pollTimer = null;
function pollWhilePending(attempt = 0) {
    clearTimeout(pollTimer);
    if (attempt >= 36) {
        nequiWaiting.value = false;
        return;
    }
    pollTimer = setTimeout(async () => {
        try {
            const { data } = await api.get('/subscription');
            info.value = data.data;
        } catch {
            // Reintenta en el siguiente tick.
        }
        if (pendingPayment.value || subscription.value?.status === 'pendiente') {
            pollWhilePending(attempt + 1);
        } else {
            nequiWaiting.value = false;
            await auth.fetchUser();
            if (hasAccess.value) {
                success.value = '¡Pago confirmado! Tu suscripción quedó activa.';
                error.value = '';
            } else {
                const last = info.value?.payments?.[0];
                if (last && last.status !== 'APPROVED') {
                    success.value = '';
                    error.value = 'El pago no se completó. Inténtalo de nuevo o usa otro método.';
                }
            }
        }
    }, 5000);
}

async function deleteCard() {
    if (!confirm('Se eliminará tu tarjeta guardada y la suscripción dejará de cobrarse automáticamente: tendrás que pagar cada mes con Nequi, DaviPlata o PSE. ¿Continuar?')) return;
    managing.value = true;
    error.value = '';
    try {
        const { data } = await api.delete('/subscription/card');
        info.value = data.data;
        success.value = 'Tarjeta eliminada. Desde ahora pagas mes a mes con el método que prefieras.';
    } catch (e) {
        error.value = e.response?.data?.message ?? 'No se pudo eliminar la tarjeta.';
    } finally {
        managing.value = false;
    }
}

async function cancelSubscription() {
    if (!confirm('Tu recepcionista dejará de funcionar al terminar el periodo pagado. ¿Cancelar la suscripción?')) return;
    managing.value = true;
    error.value = '';
    try {
        const { data } = await api.post('/subscription/cancel');
        info.value = data.data;
        success.value = 'Cancelación programada: conservas el acceso hasta el fin del periodo pagado.';
    } catch (e) {
        error.value = e.response?.data?.message ?? 'No se pudo cancelar.';
    } finally {
        managing.value = false;
    }
}

async function resumeSubscription() {
    managing.value = true;
    error.value = '';
    try {
        const { data } = await api.post('/subscription/resume');
        info.value = data.data;
        success.value = 'Tu suscripción seguirá renovándose normalmente.';
    } catch (e) {
        error.value = e.response?.data?.message ?? 'No se pudo reactivar.';
    } finally {
        managing.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <Spinner v-if="loading">Cargando tu suscripción…</Spinner>

        <template v-else-if="info">
            <h1 class="mb-1 font-display text-2xl font-semibold text-brand-900">Suscripción</h1>
            <p class="mb-6 text-sm text-sand-500">
                {{ needsPayment ? 'Activa tu suscripción para empezar a usar tu recepcionista de IA.' : 'Tu plan, tu forma de pago y el historial de cobros.' }}
            </p>

            <div v-if="error" class="mb-4 flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <ExclamationTriangleIcon class="h-5 w-5 shrink-0" /> {{ error }}
            </div>
            <div v-if="success" class="mb-4 flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
                <CheckCircleIcon class="h-5 w-5 shrink-0" /> {{ success }}
            </div>

            <Card v-if="!info.requires_subscription">
                <div class="flex items-center gap-4">
                    <img :src="mascot" alt="" class="h-20 w-auto shrink-0 select-none" draggable="false">
                    <p class="text-sm text-sand-600">
                        Tu negocio no tiene un cobro de suscripción configurado. Disfruta de RecepIA sin costo. 🎉
                    </p>
                </div>
            </Card>

            <template v-else>
                <!-- Plan -->
                <Card class="mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Plan mensual — {{ info.business_name }}</p>
                            <p class="mt-1 font-mono text-3xl text-brand-800">{{ money(info.price_cents) }} <span class="font-sans text-sm text-sand-500">COP / mes</span></p>
                        </div>
                        <div class="flex items-center gap-4">
                            <Badge v-if="subscription" :tone="statusLabels[subscription.status]?.tone ?? 'sand'">
                                {{ statusLabels[subscription.status]?.text ?? subscription.status }}
                            </Badge>
                            <img :src="mascot" alt="" class="hidden h-20 w-auto shrink-0 select-none sm:block" draggable="false">
                        </div>
                    </div>

                    <!-- Renovación vencida, dentro del plazo de gracia -->
                    <div v-if="paymentDue && hasAccess" class="mt-4 flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <ClockIcon class="mt-0.5 h-5 w-5 shrink-0" />
                        <span>
                            Tu mes venció el <strong>{{ dateLong(subscription.current_period_ends_at) }}</strong>.
                            Tienes hasta el <strong>{{ dateLong(subscription.access_until) }}</strong>
                            <template v-if="daysLeftToPay !== null"> ({{ daysLeftToPay }} día{{ daysLeftToPay === 1 ? '' : 's' }})</template>
                            para pagar; si no, tu recepcionista se pausará. Paga abajo con el método que prefieras.
                        </span>
                    </div>

                    <!-- Pago esperando confirmación -->
                    <div v-if="pendingPayment" class="mt-4 flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <ArrowPathIcon class="h-5 w-5 shrink-0 animate-spin" />
                        <span>
                            <template v-if="nequiWaiting">Abre tu app <strong>Nequi</strong> y acepta la notificación de pago. </template>
                            Tu pago está en proceso de confirmación — esta página se actualiza sola.
                        </span>
                    </div>

                    <!-- Tarjeta guardada (cobro automático) -->
                    <div v-if="subscription?.card_last_four" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sand-200 bg-sand-50 px-4 py-3">
                        <span class="flex items-center gap-2 text-sm text-sand-700">
                            <CreditCardIcon class="h-5 w-5 text-sand-500" />
                            Cobro automático a la tarjeta {{ subscription.card_brand }} ····{{ subscription.card_last_four }}
                        </span>
                        <span class="flex items-center gap-4">
                            <button
                                type="button"
                                class="text-sm text-brand-700 underline hover:text-brand-900"
                                @click="showCardForm = !showCardForm; method = 'tarjeta';"
                            >
                                {{ showCardForm ? 'Ocultar formulario' : 'Cambiar tarjeta' }}
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-1 text-sm text-amber-800 underline hover:text-amber-900"
                                :disabled="managing"
                                @click="deleteCard"
                            >
                                <TrashIcon class="h-4 w-4" /> Eliminar tarjeta
                            </button>
                        </span>
                    </div>

                    <div v-if="subscription && hasAccess && !paymentDue" class="mt-4 border-t border-sand-100 pt-4 text-sm text-sand-600">
                        <p v-if="subscription.cancel_at_period_end" class="text-amber-800">
                            Cancelación programada: tu acceso termina el <strong>{{ dateLong(subscription.current_period_ends_at) }}</strong> y no se harán más cobros.
                        </p>
                        <p v-else-if="subscription.card_last_four">
                            Próximo cobro automático: <strong>{{ dateLong(subscription.current_period_ends_at) }}</strong>.
                        </p>
                        <p v-else>
                            Tu mes va hasta el <strong>{{ dateLong(subscription.current_period_ends_at) }}</strong>;
                            cuando venza tendrás {{ info.grace_days }} día{{ info.grace_days === 1 ? '' : 's' }} para hacer el siguiente pago desde aquí.
                        </p>

                        <div class="mt-3 flex flex-wrap gap-3">
                            <Button v-if="subscription.cancel_at_period_end" :disabled="managing" @click="resumeSubscription">
                                {{ managing ? 'Un momento…' : 'Reanudar renovación' }}
                            </Button>
                            <template v-else>
                                <Button
                                    v-if="!subscription.card_last_four"
                                    type="button"
                                    variant="ghost"
                                    @click="showCardForm = !showCardForm; method = 'tarjeta';"
                                >
                                    {{ showCardForm ? 'Ocultar formulario' : 'Activar cobro automático con tarjeta' }}
                                </Button>
                                <Button type="button" variant="danger" :disabled="managing" @click="cancelSubscription">
                                    {{ managing ? 'Un momento…' : 'Cancelar suscripción' }}
                                </Button>
                            </template>
                        </div>
                    </div>
                </Card>

                <!-- Cómo quieres pagar -->
                <Card v-if="showPaymentSection" class="mb-4">
                    <h3 class="mb-1 font-display text-sm font-semibold text-brand-900">¿Cómo quieres pagar?</h3>
                    <p class="mb-4 text-xs text-sand-500">Con tarjeta el cobro es automático cada mes; con Nequi, DaviPlata o PSE pagas mes a mes.</p>

                    <div class="mb-5 grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="m in methods"
                            :key="m.key"
                            type="button"
                            class="rounded-xl border-2 p-4 text-left transition"
                            :class="method === m.key ? 'border-brand-600 bg-brand-50' : 'border-sand-200 bg-white hover:border-sand-300'"
                            @click="method = m.key"
                        >
                            <span class="flex items-center gap-2 font-medium text-brand-900">
                                <component :is="m.icon" class="h-5 w-5" /> {{ m.label }}
                                <CheckCircleIcon v-if="method === m.key" class="ml-auto h-5 w-5 text-brand-600" />
                            </span>
                            <span class="mt-1 block text-xs text-sand-500">{{ m.hint }}</span>
                        </button>
                    </div>

                    <form class="flex flex-col gap-4" @submit.prevent="pay">
                        <!-- Tarjeta (cobro automático) -->
                        <div v-if="method === 'tarjeta'" class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-sand-700">Número de tarjeta</label>
                                <input
                                    v-model="cardForm.number" inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242" required
                                    class="w-full rounded-lg border border-sand-200 px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none"
                                    @input="formatCardNumber"
                                >
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-sand-700">Nombre en la tarjeta</label>
                                <input
                                    v-model="cardForm.card_holder" type="text" autocomplete="cc-name" placeholder="Como aparece en la tarjeta" required
                                    class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-sand-700">Vencimiento</label>
                                <input
                                    v-model="cardForm.expiry" inputmode="numeric" autocomplete="cc-exp" placeholder="MM / AA" required
                                    class="w-full rounded-lg border border-sand-200 px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none"
                                    @input="formatExpiry"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-sand-700">CVC</label>
                                <input
                                    v-model="cardForm.cvc" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4" required
                                    class="w-full rounded-lg border border-sand-200 px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none"
                                >
                            </div>
                            <p class="text-xs text-sand-400 sm:col-span-2">Tu tarjeta queda guardada de forma segura en Wompi y el pago se hace solo cada mes. Puedes eliminarla cuando quieras.</p>
                        </div>

                        <!-- Nequi -->
                        <div v-else-if="method === 'nequi'">
                            <label class="mb-1 block text-sm font-medium text-sand-700">Celular registrado en Nequi</label>
                            <input
                                v-model="form.phone" inputmode="numeric" placeholder="3001234567" maxlength="10" required
                                class="w-full rounded-lg border border-sand-200 px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none sm:max-w-xs"
                            >
                            <p class="mt-1 text-xs text-sand-400">Te llegará una notificación a la app Nequi para aceptar el pago.</p>
                        </div>

                        <!-- DaviPlata -->
                        <div v-else-if="method === 'daviplata'" class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-sand-700">Tipo de documento</label>
                                <select v-model="form.legal_id_type" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                                    <option value="CC">Cédula de ciudadanía</option>
                                    <option value="CE">Cédula de extranjería</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-sand-700">Número de documento</label>
                                <input
                                    v-model="form.legal_id" inputmode="numeric" placeholder="1023456789" required
                                    class="w-full rounded-lg border border-sand-200 px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none"
                                >
                            </div>
                            <p class="text-xs text-sand-400 sm:col-span-2">Te llevaremos a una página segura de Wompi donde confirmas con el código que llega a tu celular DaviPlata.</p>
                        </div>

                        <!-- PSE -->
                        <div v-else class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-sand-700">Tu banco</label>
                                <select v-model="form.financial_institution_code" required class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                                    <option value="" disabled>{{ banksLoading ? 'Cargando bancos…' : 'Elige tu banco' }}</option>
                                    <option v-for="bank in banks" :key="bank.financial_institution_code" :value="bank.financial_institution_code">
                                        {{ bank.financial_institution_name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-sand-700">Tipo de persona</label>
                                <select v-model="form.user_type" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                                    <option value="0">Persona natural</option>
                                    <option value="1">Empresa</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-sand-700">Tipo de documento</label>
                                <select v-model="form.legal_id_type" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                                    <option value="CC">Cédula de ciudadanía</option>
                                    <option value="CE">Cédula de extranjería</option>
                                    <option value="NIT">NIT</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-sand-700">Número de documento</label>
                                <input
                                    v-model="form.legal_id" inputmode="numeric" placeholder="1023456789" required
                                    class="w-full rounded-lg border border-sand-200 px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-sand-700">Celular</label>
                                <input
                                    v-model="form.phone" inputmode="numeric" placeholder="3001234567" maxlength="10" required
                                    class="w-full rounded-lg border border-sand-200 px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none"
                                >
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-sand-700">Nombre completo</label>
                                <input
                                    v-model="form.full_name" type="text" placeholder="Como aparece en tu banco" required
                                    class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                                >
                            </div>
                            <p class="text-xs text-sand-400 sm:col-span-2">Te llevaremos a tu banco para autorizar el pago y al terminar volverás aquí.</p>
                        </div>

                        <label class="flex items-start gap-2 text-xs text-sand-600">
                            <input v-model="accepted" type="checkbox" class="mt-0.5">
                            <span>
                                {{ method === 'tarjeta' ? `Acepto el cobro mensual recurrente de ${money(info.price_cents)}` : `Acepto pagar ${money(info.price_cents)} por este mes` }} y los
                                <a href="https://wompi.co/terminos-y-condiciones/" target="_blank" rel="noopener" class="text-brand-700 underline">términos y condiciones de Wompi</a>.
                            </span>
                        </label>

                        <Button type="submit" :disabled="!formValid || paying" class="w-full sm:w-auto">
                            {{ paying ? 'Procesando…' : (method === 'tarjeta' ? (needsPayment || paymentDue ? `Pagar ${money(info.price_cents)} y activar cobro automático` : 'Guardar tarjeta') : `Pagar ${money(info.price_cents)}`) }}
                        </Button>
                    </form>

                    <p class="mt-4 flex items-center gap-1.5 text-xs text-sand-400">
                        <ShieldCheckIcon class="h-4 w-4" />
                        Pago procesado por Wompi (Bancolombia). Nunca guardamos tus datos bancarios.
                    </p>
                </Card>

                <!-- Historial -->
                <Card v-if="info.payments.length">
                    <h3 class="mb-3 font-display text-sm font-semibold text-brand-900">Historial de pagos</h3>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs tracking-wide text-sand-500 uppercase">
                                <th class="pb-2 pr-4">Fecha</th>
                                <th class="pb-2 pr-4">Monto</th>
                                <th class="pb-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payment in info.payments" :key="payment.id" class="border-t border-sand-100">
                                <td class="py-2 pr-4 text-sand-600">{{ dateLong(payment.created_at) }}</td>
                                <td class="py-2 pr-4 font-mono text-sand-700">{{ money(payment.amount_cents) }}</td>
                                <td class="py-2">
                                    <Badge :tone="paymentLabels[payment.status]?.tone ?? 'sand'">{{ paymentLabels[payment.status]?.text ?? payment.status }}</Badge>
                                    <span v-if="payment.failure_reason && payment.status !== 'APPROVED'" class="ml-2 text-xs text-sand-400">{{ payment.failure_reason }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>
            </template>
        </template>
    </div>
</template>
