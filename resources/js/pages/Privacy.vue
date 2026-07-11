<script setup>
import BrandMark from "../components/BrandMark.vue";

// TODO(legal): completar con los datos reales del responsable antes de publicar.
const company = {
    name: "David Lozano",
    nit: "1088826094",
    address: "Pereira, Risaralda, Colombia",
    email: "jdavid.lozano1404@gmail.com",
    whatsapp: "+57 302 472 0171",
};

const updatedAt = "11 de julio de 2026";
</script>

<template>
    <div class="min-h-screen bg-white">
        <header class="border-b border-sand-200">
            <div
                class="mx-auto flex max-w-3xl items-center justify-between px-5 py-5 sm:px-8"
            >
                <router-link
                    :to="{ name: 'landing' }"
                    class="flex items-center gap-2.5"
                >
                    <BrandMark :size="40" />
                    <span
                        class="font-display text-lg font-semibold tracking-tight text-brand-950"
                        >recepia</span
                    >
                </router-link>
                <router-link
                    :to="{ name: 'landing' }"
                    class="text-sm text-sand-500 transition hover:text-sand-700"
                    >← Volver al inicio</router-link
                >
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
            <h1
                class="font-display text-3xl font-semibold tracking-tight text-brand-950"
            >
                Política de tratamiento de datos personales
            </h1>
            <p class="mt-2 text-sm text-sand-500">
                Última actualización: {{ updatedAt }} · Expedida en cumplimiento
                de la Ley 1581 de 2012 y el Decreto 1377 de 2013.
            </p>

            <div
                class="mt-8 flex flex-col gap-6 text-[15px] leading-relaxed text-sand-700"
            >
                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        1. Responsable del tratamiento
                    </h2>
                    <p>
                        <strong>{{ company.name }}</strong
                        >, identificado con <strong>{{ company.nit }}</strong
                        >, con domicilio en {{ company.address }}, correo
                        electrónico
                        <a
                            :href="`mailto:${company.email}`"
                            class="text-brand-700 underline"
                            >{{ company.email }}</a
                        >
                        y WhatsApp {{ company.whatsapp }} (en adelante,
                        "Recepia").
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        2. Datos que tratamos
                    </h2>
                    <p>Tratamos las siguientes categorías de datos:</p>
                    <ul class="mt-2 list-disc pl-5">
                        <li>
                            <strong>De los dueños y usuarios del panel:</strong>
                            nombre, correo electrónico, teléfono, datos del
                            negocio (nombre, dirección, servicios, horarios) y
                            datos de facturación. Los datos de medios de pago
                            (tarjeta, Nequi, DaviPlata, PSE) son recolectados y
                            custodiados directamente por Wompi; no los
                            almacenamos.
                        </li>
                        <li>
                            <strong
                                >De los clientes finales que escriben al
                                WhatsApp del negocio:</strong
                            >
                            número de teléfono, nombre de perfil, contenido de
                            los mensajes y citas agendadas. Respecto de estos
                            datos, el negocio que contrata Recepia actúa como
                            responsable del tratamiento y Recepia como
                            encargado.
                        </li>
                        <li>
                            <strong>Datos técnicos:</strong> cookies
                            estrictamente necesarias para la sesión y la
                            seguridad (autenticación y protección CSRF). No
                            usamos cookies publicitarias ni de seguimiento de
                            terceros.
                        </li>
                    </ul>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        3. Finalidades
                    </h2>
                    <ul class="list-disc pl-5">
                        <li>
                            Prestar el servicio: recibir y responder mensajes de
                            WhatsApp con inteligencia artificial, agendar citas
                            y mostrar la información en el panel del negocio.
                        </li>
                        <li>
                            Gestionar la cuenta, la suscripción, los cobros y el
                            envío de comprobantes y recordatorios.
                        </li>
                        <li>
                            Enviar notificaciones operativas (citas agendadas,
                            conversaciones que requieren atención, estado de la
                            suscripción).
                        </li>
                        <li>
                            Atender consultas, quejas y reclamos, y cumplir
                            obligaciones legales, contables y tributarias.
                        </li>
                        <li>Mejorar la calidad y seguridad del servicio.</li>
                    </ul>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        4. Encargados y transferencias internacionales
                    </h2>
                    <p>
                        Para operar el servicio compartimos datos con
                        proveedores que actúan bajo nuestras instrucciones y con
                        medidas de seguridad contractuales, algunos ubicados
                        fuera de Colombia (principalmente en Estados Unidos):
                    </p>
                    <ul class="mt-2 list-disc pl-5">
                        <li>
                            <strong>Meta Platforms</strong> (API de WhatsApp
                            Business): transporte de los mensajes.
                        </li>
                        <li>
                            <strong>Anthropic</strong>: procesamiento de los
                            mensajes con modelos de inteligencia artificial para
                            generar las respuestas del recepcionista.
                        </li>
                        <li>
                            <strong>Wompi / Bancolombia</strong>: procesamiento
                            de pagos.
                        </li>
                        <li>
                            Proveedores de infraestructura (alojamiento y correo
                            transaccional).
                        </li>
                    </ul>
                    <p class="mt-2">
                        Al aceptar esta política autorizas la transmisión y
                        transferencia internacional de tus datos con estas
                        finalidades exclusivamente.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        5. Derechos del titular
                    </h2>
                    <p>
                        Como titular de los datos, conforme al artículo 8 de la
                        Ley 1581 de 2012, tienes derecho a:
                    </p>
                    <ul class="mt-2 list-disc pl-5">
                        <li>
                            Conocer, actualizar y rectificar tus datos
                            personales.
                        </li>
                        <li>Solicitar prueba de la autorización otorgada.</li>
                        <li>Ser informado sobre el uso que se les ha dado.</li>
                        <li>
                            Revocar la autorización y/o solicitar la supresión
                            de los datos cuando no exista un deber legal o
                            contractual que lo impida.
                        </li>
                        <li>Acceder gratuitamente a tus datos.</li>
                        <li>
                            Presentar quejas ante la Superintendencia de
                            Industria y Comercio (<a
                                href="https://www.sic.gov.co"
                                target="_blank"
                                rel="noopener"
                                class="text-brand-700 underline"
                                >www.sic.gov.co</a
                            >) por infracciones a la ley.
                        </li>
                    </ul>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        6. Cómo ejercer tus derechos
                    </h2>
                    <p>
                        Escríbenos a
                        <a
                            :href="`mailto:${company.email}`"
                            class="text-brand-700 underline"
                            >{{ company.email }}</a
                        >
                        o al WhatsApp {{ company.whatsapp }}, indicando tu
                        nombre, tu solicitud y un medio de contacto. Conforme a
                        los artículos 14 y 15 de la Ley 1581 de 2012:
                    </p>
                    <ul class="mt-2 list-disc pl-5">
                        <li>
                            <strong>Consultas:</strong> respondemos en máximo
                            diez (10) días hábiles, prorrogables por cinco (5)
                            más, informándote la razón.
                        </li>
                        <li>
                            <strong>Reclamos:</strong> respondemos en máximo
                            quince (15) días hábiles, prorrogables por ocho (8)
                            más. Si el reclamo está incompleto te pediremos
                            completarlo dentro de los cinco (5) días siguientes.
                        </li>
                    </ul>
                    <p class="mt-2">
                        Si eres cliente de un negocio que usa Recepia y quieres
                        ejercer derechos sobre tus conversaciones, puedes
                        dirigirte al negocio (responsable) o a nosotros;
                        canalizaremos la solicitud.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        7. Seguridad y conservación
                    </h2>
                    <p>
                        Aplicamos medidas técnicas y organizativas razonables
                        para proteger los datos: cifrado en tránsito, control de
                        acceso por roles y contraseñas almacenadas con hash.
                        Conservamos los datos mientras la cuenta esté activa y,
                        después, solo por el tiempo necesario para cumplir
                        obligaciones legales, contables o contractuales, tras lo
                        cual se suprimen.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        8. Aviso de privacidad y autorización
                    </h2>
                    <p>
                        La autorización para el tratamiento se obtiene al
                        momento de contratar el servicio y aceptar estos
                        documentos. Esta política está permanentemente
                        disponible en esta página para consulta, impresión y
                        descarga.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        9. Vigencia y cambios
                    </h2>
                    <p>
                        Esta política rige desde su publicación. Cualquier
                        cambio sustancial será informado por correo electrónico
                        o a través del panel antes de su entrada en vigencia.
                        Las bases de datos se mantendrán durante la operación
                        del servicio y los plazos legales aplicables.
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>
