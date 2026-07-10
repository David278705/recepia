<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";
import {
    Bars3Icon,
    XMarkIcon,
    ChevronDownIcon,
    CheckIcon,
} from "@heroicons/vue/24/outline";
import BrandMark from "../components/BrandMark.vue";

const scrolled = ref(false);
const mobileOpen = ref(false);
const openFaq = ref(0);

function onScroll() {
    scrolled.value = window.scrollY > 12;
}

onMounted(() => window.addEventListener("scroll", onScroll, { passive: true }));
onBeforeUnmount(() => window.removeEventListener("scroll", onScroll));

function toggleFaq(i) {
    openFaq.value = openFaq.value === i ? -1 : i;
}

const navLinks = [
    { href: "#producto", label: "Producto" },
    { href: "#como-funciona", label: "Cómo funciona" },
    { href: "#preguntas", label: "Preguntas" },
];

const facts = [
    { value: "24/7", label: "Siempre disponible" },
    { value: "Segundos", label: "Tiempo de respuesta" },
    { value: "1 número", label: "El tuyo de siempre" },
    { value: "Cero", label: "Datos inventados" },
];

const steps = [
    {
        title: "Conectamos tu número",
        description:
            "Activamos el modo Coexistence de WhatsApp en tu número actual. Conservas tu historial y sigues usando tu app normal.",
    },
    {
        title: "Le enseñas tu negocio",
        description:
            "Servicios, precios, horarios y preguntas frecuentes. Ese es todo el conocimiento del que Recepia puede hablar — ni más, ni menos.",
    },
    {
        title: "Recepia atiende",
        description:
            "Responde al instante, propone horarios reales y agenda citas. Cuando algo se sale del guion, te lo pasa a ti.",
    },
    {
        title: "Tú supervisas",
        description:
            "Desde el panel ves cada conversación, tomas el control cuando quieres y mides cuántas citas te consiguió el bot.",
    },
];

const guarantees = [
    {
        title: "Nunca inventa",
        description:
            "Responde solo con la información que tú configuraste. Si no sabe, pregunta o escala — jamás improvisa un precio.",
    },
    {
        title: "Se aparta cuando tú entras",
        description:
            "Si respondes a un cliente desde tu celular, Recepia lo detecta y guarda silencio en esa conversación.",
    },
    {
        title: "Tus datos, solo tuyos",
        description:
            "La información de cada negocio vive aislada. Lo que configuras para tu bot no toca ni alimenta a ningún otro.",
    },
];

const faqs = [
    {
        q: "¿Tengo que cambiar de número de WhatsApp?",
        a: "No. Recepia se conecta a tu número actual mediante la WhatsApp Cloud API en modo Coexistence: tú sigues usando tu app de WhatsApp Business con normalidad, y Recepia responde en paralelo desde el mismo número.",
    },
    {
        q: "¿Qué pasa si no sabe responder algo?",
        a: "Escala la conversación: te notifica, se silencia en ese chat y el cliente recibe un aviso de que ya te contactaron. Tú retomas la conversación desde el panel cuando puedas.",
    },
    {
        q: "¿Puede inventar precios o disponibilidad?",
        a: "No. Responde únicamente con los servicios, precios, horarios y respuestas que configuras en tu panel. La disponibilidad la calcula contra tu calendario real, no la adivina.",
    },
    {
        q: "¿Y si yo le escribo al cliente desde mi celular?",
        a: "Recepia lo detecta y pausa el bot en esa conversación durante un rato, para que nunca haya dos respuestas cruzadas. Cuando terminas, retoma solo.",
    },
    {
        q: "¿Necesito saber de tecnología?",
        a: "No. La conexión inicial la hacemos contigo, y el panel del día a día está diseñado para dueños de negocio, no para técnicos.",
    },
];
</script>

<template>
    <div class="overflow-x-clip bg-sand-50 text-sand-900">
        <!-- ==================== NAV ==================== -->
        <header
            class="fixed inset-x-0 top-0 z-50 transition-all duration-300"
            :class="
                scrolled
                    ? 'border-b border-sand-200 bg-white/85 backdrop-blur-md'
                    : 'border-b border-transparent'
            "
        >
            <div
                class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4 sm:px-8"
            >
                <a href="#inicio" class="flex items-center gap-2.5">
                    <BrandMark :size="44" />
                    <span
                        class="font-display text-xl font-semibold tracking-tight text-brand-950"
                        >recepia</span
                    >
                </a>

                <nav class="hidden items-center gap-8 md:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-sm font-medium text-sand-600 transition hover:text-brand-900"
                        >{{ link.label }}</a
                    >
                </nav>

                <div class="hidden items-center gap-5 md:flex">
                    <router-link
                        :to="{ name: 'login' }"
                        class="text-sm font-medium text-sand-600 transition hover:text-brand-900"
                    >
                        Iniciar sesión
                    </router-link>
                    <a
                        href="#contacto"
                        class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-900"
                    >
                        Solicitar demo
                    </a>
                </div>

                <button
                    type="button"
                    class="text-brand-950 md:hidden"
                    aria-label="Abrir menú"
                    @click="mobileOpen = true"
                >
                    <Bars3Icon class="h-6 w-6" />
                </button>
            </div>
        </header>

        <!-- Menú móvil -->
        <Transition name="slideover-backdrop">
            <div
                v-if="mobileOpen"
                class="fixed inset-0 z-50 bg-sand-950/50 md:hidden"
                @click="mobileOpen = false"
            />
        </Transition>
        <Transition name="slideover-panel">
            <div
                v-if="mobileOpen"
                class="fixed inset-y-0 right-0 z-50 flex w-72 flex-col gap-1 bg-white p-6 shadow-2xl md:hidden"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <BrandMark :size="36" />
                        <span
                            class="font-display text-lg font-semibold tracking-tight text-brand-950"
                            >recepia</span
                        >
                    </span>
                    <button
                        type="button"
                        aria-label="Cerrar menú"
                        class="text-sand-400"
                        @click="mobileOpen = false"
                    >
                        <XMarkIcon class="h-6 w-6" />
                    </button>
                </div>
                <a
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="link.href"
                    class="rounded-lg px-3 py-2.5 text-sm font-medium text-sand-700 hover:bg-sand-50"
                    @click="mobileOpen = false"
                    >{{ link.label }}</a
                >
                <hr class="my-3 border-sand-100" />
                <router-link
                    :to="{ name: 'login' }"
                    class="rounded-lg px-3 py-2.5 text-sm font-medium text-sand-700 hover:bg-sand-50"
                >
                    Iniciar sesión
                </router-link>
                <a
                    href="#contacto"
                    class="mt-2 rounded-lg bg-brand-800 px-3 py-2.5 text-center text-sm font-semibold text-white"
                    @click="mobileOpen = false"
                    >Solicitar demo</a
                >
            </div>
        </Transition>

        <main id="inicio">
            <!-- ==================== HERO ==================== -->
            <section
                class="mx-auto grid max-w-6xl items-center gap-14 px-5 pt-32 pb-16 sm:px-8 sm:pt-40 lg:grid-cols-[1.1fr_0.9fr] lg:gap-10"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase"
                    >
                        Recepcionista con inteligencia artificial
                    </p>

                    <h1
                        class="mt-5 font-display text-[2.6rem] leading-[1.05] font-medium tracking-tight text-brand-950 sm:text-6xl"
                    >
                        Tu negocio ya no deja mensajes en visto.
                    </h1>

                    <p
                        class="mt-6 max-w-lg text-lg leading-relaxed text-sand-600"
                    >
                        Recepia atiende el WhatsApp de tu negocio como lo haría
                        tu mejor recepcionista: responde en segundos, agenda
                        citas contra tu calendario real y te pasa la
                        conversación cuando de verdad se necesita una persona.
                        En tu mismo número de siempre.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a
                            href="#contacto"
                            class="inline-flex items-center justify-center rounded-xl bg-brand-800 px-7 py-3.5 text-base font-semibold text-white shadow-md shadow-brand-900/10 transition hover:bg-brand-900"
                        >
                            Solicitar una demo
                        </a>
                        <a
                            href="#como-funciona"
                            class="inline-flex items-center justify-center rounded-xl border border-sand-300 bg-white px-7 py-3.5 text-base font-semibold text-sand-700 transition hover:border-sand-400 hover:bg-sand-50"
                        >
                            Ver cómo funciona
                        </a>
                    </div>

                    <p class="mt-6 text-sm text-sand-400">
                        Sin cambiar de número &nbsp;·&nbsp; Sin apps nuevas para
                        tus clientes &nbsp;·&nbsp; Configuración guiada
                    </p>
                </div>

                <!-- Mockup de teléfono -->
                <div class="relative mx-auto w-full max-w-[21rem]">
                    <img
                        :src="'/img/robot_saludo_mano.png'"
                        alt="Recepia, tu recepcionista de IA"
                        class="absolute -bottom-6 -left-24 z-10 hidden w-36 select-none drop-shadow-xl lg:block"
                        draggable="false"
                    />
                    <div
                        class="rounded-[2.4rem] border-[7px] border-sand-950 bg-sand-950 shadow-2xl shadow-brand-950/20"
                    >
                        <div
                            class="overflow-hidden rounded-[1.9rem] bg-[#e8e2d8]"
                        >
                            <div
                                class="flex items-center gap-3 bg-brand-900 px-4 pt-8 pb-3"
                            >
                                <span
                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-sand-200 font-display text-sm font-semibold text-brand-900"
                                    >B</span
                                >
                                <div>
                                    <p class="text-sm font-semibold text-white">
                                        Barbería El Corte
                                    </p>
                                    <p
                                        class="flex items-center gap-1 text-[11px] text-brand-200"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-amber-400"
                                        />
                                        en línea
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex min-h-[23rem] flex-col gap-2.5 px-3 py-4"
                            >
                                <div
                                    class="max-w-[80%] self-start rounded-xl rounded-tl-sm bg-white px-3 py-2 text-[13px] text-sand-800 shadow-sm"
                                >
                                    Hola, ¿tienen turno para mañana en la tarde?
                                </div>
                                <div
                                    class="max-w-[85%] self-end rounded-xl rounded-tr-sm bg-[#d7f3e3] px-3 py-2 text-[13px] text-sand-800 shadow-sm"
                                >
                                    Hola, claro. Mañana tenemos 3:00 pm y 4:30
                                    pm disponibles. ¿Cuál te sirve?
                                </div>
                                <div
                                    class="max-w-[80%] self-start rounded-xl rounded-tl-sm bg-white px-3 py-2 text-[13px] text-sand-800 shadow-sm"
                                >
                                    A las 3 está bien. Soy Andrés
                                </div>
                                <div
                                    class="max-w-[85%] self-end rounded-xl rounded-tr-sm bg-[#d7f3e3] px-3 py-2 text-[13px] text-sand-800 shadow-sm"
                                >
                                    Listo, Andrés: corte de cabello mañana a las
                                    3:00 pm. Te esperamos.
                                </div>
                                <p
                                    class="mt-1 self-end text-[10px] text-sand-400"
                                >
                                    Atendido por Recepia · 22:47
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Cifras -->
            <section class="border-y border-sand-200 bg-white">
                <div
                    class="mx-auto grid max-w-6xl grid-cols-2 gap-y-8 px-5 py-10 sm:px-8 md:grid-cols-4"
                >
                    <div
                        v-for="fact in facts"
                        :key="fact.label"
                        class="text-center md:text-left"
                    >
                        <p
                            class="font-display text-2xl font-medium tracking-tight text-brand-950"
                        >
                            {{ fact.value }}
                        </p>
                        <p class="mt-1 text-sm text-sand-500">
                            {{ fact.label }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Verticales -->
            <p
                class="mx-auto max-w-6xl px-5 pt-14 text-center text-xs font-medium tracking-[0.18em] text-sand-400 uppercase sm:px-8"
            >
                Hecho para negocios que viven de su agenda — barberías ·
                clínicas estéticas · consultorios · restaurantes · talleres
            </p>

            <!-- Lo que hace por ti -->
            <div
                class="mx-auto grid max-w-5xl grid-cols-2 gap-6 px-5 pt-14 sm:px-8 lg:grid-cols-4"
            >
                <img
                    v-for="feature in [
                        'siempre_disponible',
                        'agenda_citas',
                        'escala_humano',
                        'cercano_confiable',
                    ]"
                    :key="feature"
                    :src="`/img/icono_${feature}.png`"
                    :alt="feature.replaceAll('_', ' ')"
                    class="mx-auto w-full max-w-[13rem] select-none"
                    draggable="false"
                    loading="lazy"
                />
            </div>

            <!-- ==================== PRODUCTO ==================== -->
            <section
                id="producto"
                class="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28"
            >
                <div class="flex flex-col gap-24">
                    <!-- Bloque 1 -->
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div>
                            <h2
                                class="font-display text-3xl font-medium tracking-tight text-brand-950 sm:text-4xl"
                            >
                                Responde como si fueras tú
                            </h2>
                            <p
                                class="mt-4 text-lg leading-relaxed text-sand-600"
                            >
                                Cada respuesta sale de lo que tú configuraste:
                                tus servicios, tus precios, tus horarios, tus
                                reglas. Con el tono que elijas — cercano o
                                formal — y siempre en español.
                            </p>
                            <ul class="mt-6 flex flex-col gap-3">
                                <li
                                    v-for="item in [
                                        'Precios y servicios exactos, nunca aproximados',
                                        'Preguntas frecuentes respondidas al instante',
                                        'Instrucciones tuyas que el bot respeta al pie de la letra',
                                    ]"
                                    :key="item"
                                    class="flex items-start gap-3 text-sand-700"
                                >
                                    <CheckIcon
                                        class="mt-1 h-4 w-4 shrink-0 text-brand-600"
                                    />
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                        <div
                            class="rounded-2xl border border-sand-200 bg-white p-6 shadow-sm"
                        >
                            <p
                                class="text-xs font-semibold tracking-wider text-sand-400 uppercase"
                            >
                                Conversación real de ejemplo
                            </p>
                            <div class="mt-4 flex flex-col gap-2.5">
                                <div
                                    class="max-w-[85%] self-start rounded-xl rounded-tl-sm bg-sand-100 px-3.5 py-2.5 text-sm text-sand-800"
                                >
                                    ¿Cuánto vale el combo corte y barba?
                                </div>
                                <div
                                    class="max-w-[85%] self-end rounded-xl rounded-tr-sm bg-brand-800 px-3.5 py-2.5 text-sm text-white"
                                >
                                    El combo corte + barba vale $35.000 y toma
                                    unos 45 minutos. ¿Quieres que te reserve un
                                    turno?
                                </div>
                                <div
                                    class="max-w-[85%] self-start rounded-xl rounded-tl-sm bg-sand-100 px-3.5 py-2.5 text-sm text-sand-800"
                                >
                                    ¿Hacen tintes fantasía?
                                </div>
                                <div
                                    class="max-w-[85%] self-end rounded-xl rounded-tr-sm bg-brand-800 px-3.5 py-2.5 text-sm text-white"
                                >
                                    Esa la tengo que confirmar con el equipo. Ya
                                    le avisé a Carlos y te contacta pronto.
                                </div>
                            </div>
                            <p
                                class="mt-4 border-t border-sand-100 pt-3 text-xs text-sand-400"
                            >
                                El tinte fantasía no estaba configurado —
                                Recepia no lo inventó: escaló.
                            </p>
                        </div>
                    </div>

                    <!-- Bloque 2 -->
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div class="order-2 lg:order-1">
                            <div
                                class="rounded-2xl border border-sand-200 bg-white p-6 shadow-sm"
                            >
                                <div class="flex items-center justify-between">
                                    <p
                                        class="text-xs font-semibold tracking-wider text-sand-400 uppercase"
                                    >
                                        Viernes 3 de julio
                                    </p>
                                    <span
                                        class="rounded-md bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700"
                                        >Agenda del día</span
                                    >
                                </div>
                                <div class="mt-4 flex flex-col gap-2">
                                    <div
                                        class="flex items-center gap-3 rounded-lg border border-sand-100 px-3.5 py-2.5"
                                    >
                                        <span
                                            class="font-mono text-sm text-sand-500"
                                            >10:00</span
                                        >
                                        <span class="text-sm text-sand-700"
                                            >Manicure · Laura P.</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center justify-between rounded-lg border border-brand-200 bg-brand-50 px-3.5 py-2.5"
                                    >
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="font-mono text-sm font-medium text-brand-800"
                                                >15:00</span
                                            >
                                            <span
                                                class="text-sm font-medium text-brand-900"
                                                >Corte de cabello · Andrés
                                                T.</span
                                            >
                                        </div>
                                        <span
                                            class="rounded-md bg-white px-2 py-0.5 text-[11px] font-semibold text-brand-700"
                                            >Agendada por Recepia</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center gap-3 rounded-lg border border-sand-100 px-3.5 py-2.5"
                                    >
                                        <span
                                            class="font-mono text-sm text-sand-500"
                                            >16:30</span
                                        >
                                        <span
                                            class="text-sm text-sand-400 italic"
                                            >Disponible</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="order-1 lg:order-2">
                            <h2
                                class="font-display text-3xl font-medium tracking-tight text-brand-950 sm:text-4xl"
                            >
                                Agenda sin pisarse contigo
                            </h2>
                            <p
                                class="mt-4 text-lg leading-relaxed text-sand-600"
                            >
                                Antes de proponer un horario, Recepia consulta
                                la disponibilidad real de tu calendario — tus
                                horarios de atención menos las citas que ya
                                existen. Las citas del bot y las que agendas a
                                mano viven en el mismo lugar.
                            </p>
                            <ul class="mt-6 flex flex-col gap-3">
                                <li
                                    v-for="item in [
                                        'Propone solo horarios que de verdad están libres',
                                        'Confirma la cita y te notifica al momento',
                                        'Calendario semanal con todo en un solo vistazo',
                                    ]"
                                    :key="item"
                                    class="flex items-start gap-3 text-sand-700"
                                >
                                    <CheckIcon
                                        class="mt-1 h-4 w-4 shrink-0 text-brand-600"
                                    />
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Bloque 3 -->
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div>
                            <h2
                                class="font-display text-3xl font-medium tracking-tight text-brand-950 sm:text-4xl"
                            >
                                Sabe cuándo apartarse
                            </h2>
                            <p
                                class="mt-4 text-lg leading-relaxed text-sand-600"
                            >
                                Un buen recepcionista también sabe cuándo pasar
                                la llamada. Si el cliente se molesta, pide
                                hablar con una persona o pregunta algo fuera de
                                guion, Recepia escala la conversación y te
                                avisa. Y si tú entras a responder desde tu
                                celular, se hace a un lado sin que se lo pidas.
                            </p>
                            <ul class="mt-6 flex flex-col gap-3">
                                <li
                                    v-for="item in [
                                        'Detecta molestia, urgencia o la petición de un humano',
                                        'Te notifica y silencia el bot en esa conversación',
                                        'Desde el panel la retomas o se la devuelves al bot',
                                    ]"
                                    :key="item"
                                    class="flex items-start gap-3 text-sand-700"
                                >
                                    <CheckIcon
                                        class="mt-1 h-4 w-4 shrink-0 text-brand-600"
                                    />
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                        <div
                            class="rounded-2xl border border-sand-200 bg-white p-6 shadow-sm"
                        >
                            <p
                                class="text-xs font-semibold tracking-wider text-sand-400 uppercase"
                            >
                                Bandeja del panel
                            </p>
                            <div class="mt-4 flex flex-col gap-2.5">
                                <div
                                    class="flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
                                >
                                    <div>
                                        <p
                                            class="flex items-center gap-2 text-sm font-semibold text-sand-900"
                                        >
                                            <span
                                                class="h-2 w-2 rounded-full bg-amber-500"
                                            />
                                            Ana M.
                                        </p>
                                        <p class="mt-0.5 text-xs text-sand-500">
                                            Pidió hablar con una persona · hace
                                            2 min
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-lg bg-brand-800 px-3 py-1.5 text-xs font-semibold text-white"
                                        >Tomar conversación</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-xl border border-sand-100 px-4 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-medium text-sand-800"
                                        >
                                            Andrés T.
                                        </p>
                                        <p class="mt-0.5 text-xs text-sand-400">
                                            Cita confirmada para mañana 3:00 pm
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-md bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700"
                                        >Bot activo</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-xl border border-sand-100 px-4 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-medium text-sand-800"
                                        >
                                            Julián R.
                                        </p>
                                        <p class="mt-0.5 text-xs text-sand-400">
                                            Preguntó horarios · resuelto por el
                                            bot
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-md bg-sand-100 px-2 py-0.5 text-[11px] font-semibold text-sand-500"
                                        >Cerrada</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ==================== CÓMO FUNCIONA ==================== -->
            <section id="como-funciona" class="bg-brand-950 py-20 sm:py-28">
                <div class="mx-auto max-w-6xl px-5 sm:px-8">
                    <div class="max-w-2xl">
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-amber-400 uppercase"
                        >
                            Cómo funciona
                        </p>
                        <h2
                            class="mt-4 font-display text-3xl font-medium tracking-tight text-white sm:text-4xl"
                        >
                            En marcha en días, no en meses.
                        </h2>
                    </div>

                    <div
                        class="mt-14 grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            v-for="(step, i) in steps"
                            :key="step.title"
                            class="border-t border-white/15 pt-6"
                        >
                            <p
                                class="font-display text-sm font-medium text-amber-400"
                            >
                                {{ String(i + 1).padStart(2, "0") }}
                            </p>
                            <h3
                                class="mt-3 font-display text-xl font-medium tracking-tight text-white"
                            >
                                {{ step.title }}
                            </h3>
                            <p
                                class="mt-3 text-sm leading-relaxed text-brand-100/70"
                            >
                                {{ step.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ==================== GARANTÍAS ==================== -->
            <section class="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
                <div class="grid gap-12 md:grid-cols-3">
                    <div
                        v-for="g in guarantees"
                        :key="g.title"
                        class="border-t-2 border-brand-800 pt-6"
                    >
                        <h3
                            class="font-display text-xl font-medium tracking-tight text-brand-950"
                        >
                            {{ g.title }}
                        </h3>
                        <p class="mt-3 leading-relaxed text-sand-600">
                            {{ g.description }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- ==================== FAQ ==================== -->
            <section
                id="preguntas"
                class="border-t border-sand-200 bg-white py-20 sm:py-28"
            >
                <div
                    class="mx-auto grid max-w-6xl gap-12 px-5 sm:px-8 lg:grid-cols-[0.8fr_1.2fr]"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase"
                        >
                            Preguntas frecuentes
                        </p>
                        <h2
                            class="mt-4 font-display text-3xl font-medium tracking-tight text-brand-950 sm:text-4xl"
                        >
                            Lo que todo dueño pregunta primero.
                        </h2>
                        <p class="mt-4 text-sand-500">
                            ¿Tienes otra?
                            <a
                                href="mailto:hola@recepia.app"
                                class="font-medium text-brand-700 hover:underline"
                                >Escríbenos</a
                            >
                            — respondemos rápido. Es lo nuestro.
                        </p>
                    </div>

                    <div
                        class="divide-y divide-sand-200 border-y border-sand-200"
                    >
                        <div v-for="(item, i) in faqs" :key="item.q">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 py-5 text-left"
                                @click="toggleFaq(i)"
                            >
                                <span class="font-medium text-sand-900">{{
                                    item.q
                                }}</span>
                                <ChevronDownIcon
                                    class="h-5 w-5 shrink-0 text-sand-400 transition-transform duration-200"
                                    :class="{ 'rotate-180': openFaq === i }"
                                />
                            </button>
                            <p
                                v-show="openFaq === i"
                                class="pb-5 text-sm leading-relaxed text-sand-600"
                            >
                                {{ item.a }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ==================== CTA FINAL ==================== -->
            <section id="contacto" class="bg-brand-950 py-20 sm:py-28">
                <div class="mx-auto max-w-3xl px-5 text-center sm:px-8">
                    <img
                        :src="'/img/robot_agradecido.png'"
                        alt=""
                        class="mx-auto mb-8 w-32 select-none drop-shadow-[0_18px_35px_rgba(0,0,0,0.4)]"
                        draggable="false"
                        loading="lazy"
                    />
                    <h2
                        class="font-display text-3xl leading-tight font-medium tracking-tight text-white sm:text-5xl"
                    >
                        ¿Cuántos clientes se te fueron esta semana por no
                        alcanzar a contestar?
                    </h2>
                    <p class="mx-auto mt-6 max-w-xl text-lg text-brand-100/80">
                        Cuéntanos de tu negocio. Conectamos tu número en modo
                        Coexistence, sin perder tu historial y sin que tus
                        clientes noten ningún cambio.
                    </p>
                    <div
                        class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row"
                    >
                        <a
                            href="mailto:hola@recepia.app?subject=Quiero%20conocer%20Recepia"
                            class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-7 py-3.5 text-base font-semibold text-brand-950 shadow-lg transition hover:bg-amber-400"
                        >
                            Escríbenos: hola@recepia.app
                        </a>
                        <router-link
                            :to="{ name: 'login' }"
                            class="text-sm font-medium text-brand-100 underline-offset-4 transition hover:text-white hover:underline"
                        >
                            Ya tengo cuenta — iniciar sesión
                        </router-link>
                    </div>
                </div>
            </section>
        </main>

        <!-- ==================== FOOTER ==================== -->
        <footer class="border-t border-white/10 bg-brand-950">
            <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
                <div
                    class="flex flex-col items-start justify-between gap-8 sm:flex-row sm:items-center"
                >
                    <a href="#inicio" class="flex items-center gap-2.5">
                        <BrandMark :size="40" variant="inverse" />
                        <span
                            class="font-display text-lg font-semibold tracking-tight text-white"
                            >recepia</span
                        >
                    </a>
                    <nav class="flex flex-wrap items-center gap-x-7 gap-y-2">
                        <a
                            v-for="link in navLinks"
                            :key="link.href"
                            :href="link.href"
                            class="text-sm text-brand-100/70 transition hover:text-white"
                            >{{ link.label }}</a
                        >
                        <router-link
                            :to="{ name: 'login' }"
                            class="text-sm text-brand-100/70 transition hover:text-white"
                            >Iniciar sesión</router-link
                        >
                    </nav>
                </div>
                <div
                    class="mt-10 flex flex-col justify-between gap-2 border-t border-white/10 pt-6 text-xs text-brand-100/50 sm:flex-row"
                >
                    <p>
                        © {{ new Date().getFullYear() }} Recepia. Todos los
                        derechos reservados.
                    </p>
                    <p>Hecho para atender mejor.</p>
                </div>
            </div>
        </footer>
    </div>
</template>
