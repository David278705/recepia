<script setup>
import BrandMark from "../components/BrandMark.vue";

// TODO(legal): completar con los datos reales de la empresa antes de publicar.
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
                        >pilo</span
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
                Términos y condiciones
            </h1>
            <p class="mt-2 text-sm text-sand-500">
                Última actualización: {{ updatedAt }}
            </p>

            <div
                class="prose-pilo mt-8 flex flex-col gap-6 text-[15px] leading-relaxed text-sand-700"
            >
                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        1. Quiénes somos
                    </h2>
                    <p>
                        Pilo es un servicio operado por
                        <strong>{{ company.name }}</strong
                        >, identificado con <strong>{{ company.nit }}</strong
                        >, con domicilio en {{ company.address }}. Puedes
                        contactarnos en
                        <a
                            :href="`mailto:${company.email}`"
                            class="text-brand-700 underline"
                            >{{ company.email }}</a
                        >
                        o por WhatsApp al {{ company.whatsapp }}. Estos términos
                        regulan el uso de la plataforma Pilo (el "Servicio")
                        y constituyen el contrato entre tú y nosotros. Al
                        contratar o usar el Servicio aceptas estos términos.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        2. Descripción del servicio
                    </h2>
                    <p>
                        Pilo es un software por suscripción (SaaS) que
                        conecta el número de WhatsApp de tu negocio con un
                        recepcionista de inteligencia artificial que responde
                        mensajes de tus clientes, resuelve preguntas y agenda
                        citas, junto con un panel web para administrar
                        conversaciones, citas y la configuración del negocio.
                    </p>
                    <p class="mt-2">
                        El Servicio depende de plataformas de terceros — en
                        particular la API de WhatsApp Business de Meta — cuya
                        disponibilidad y reglas no controlamos. Las respuestas
                        del recepcionista son generadas por inteligencia
                        artificial: hacemos esfuerzos razonables para que sean
                        precisas y fieles a la información que configuras, pero
                        pueden contener errores. Eres responsable de revisar la
                        información de tu negocio que alimenta al bot y de
                        supervisar las conversaciones desde tu panel.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        3. Cuenta y acceso
                    </h2>
                    <p>
                        El alta de la cuenta la realiza nuestro equipo tras un
                        acuerdo contigo. Eres responsable de mantener la
                        confidencialidad de tus credenciales y de toda actividad
                        realizada desde tu cuenta. Debes ser mayor de edad y
                        tener capacidad para obligar al negocio que representas.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        4. Precio, facturación y renovación
                    </h2>
                    <p>
                        El precio de la suscripción se informa en pesos
                        colombianos (COP) antes del pago e incluye todos los
                        impuestos aplicables. El pago se procesa a través de
                        <strong>Wompi</strong> (Bancolombia); nunca almacenamos
                        los datos de tu tarjeta ni de tus cuentas.
                    </p>
                    <p class="mt-2">
                        La suscripción es mensual. Si pagas con tarjeta y así lo
                        autorizas expresamente al momento del pago, el cobro se
                        renueva automáticamente cada mes; puedes revocar esa
                        autorización en cualquier momento eliminando la tarjeta
                        o cancelando la suscripción desde tu panel. Con otros
                        medios de pago (Nequi, DaviPlata, PSE) cada pago cubre
                        un mes y te avisaremos antes del vencimiento para
                        renovar.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        5. Derecho de retracto
                    </h2>
                    <p>
                        Conforme al artículo 47 de la Ley 1480 de 2011 (Estatuto
                        del Consumidor), puedes retractarte de la compra dentro
                        de los cinco (5) días hábiles siguientes al pago,
                        siempre que la prestación del servicio no haya
                        comenzado. Ejercido el retracto, reembolsaremos el
                        dinero pagado dentro de los treinta (30) días calendario
                        siguientes. Para ejercerlo escríbenos a
                        {{ company.email }} o al WhatsApp
                        {{ company.whatsapp }}.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        6. Reversión del pago
                    </h2>
                    <p>
                        Conforme al artículo 51 de la Ley 1480 de 2011 y el
                        Decreto 587 de 2016, tienes derecho a solicitar la
                        reversión del pago realizado con tarjeta u otro
                        instrumento de pago electrónico cuando seas objeto de
                        fraude, el pago corresponda a una operación no
                        solicitada o el servicio adquirido no sea prestado.
                        Debes presentar la queja ante nosotros y solicitar la
                        reversión al emisor de tu medio de pago dentro de los
                        cinco (5) días hábiles siguientes a la fecha en que
                        tuviste noticia del hecho.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        7. Cancelación
                    </h2>
                    <p>
                        Puedes cancelar la suscripción en cualquier momento
                        desde tu panel, sin penalidad. La cancelación evita
                        cobros futuros y conservas acceso al Servicio hasta el
                        final del periodo ya pagado. Podemos suspender o
                        terminar el Servicio por falta de pago, uso contrario a
                        estos términos o exigencia de las plataformas de
                        terceros de las que depende, avisándote con anticipación
                        razonable cuando sea posible.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        8. Uso aceptable
                    </h2>
                    <p>
                        Te comprometes a usar el Servicio solo para fines
                        lícitos y de acuerdo con las políticas de WhatsApp
                        Business: no enviar spam ni comunicaciones no
                        consentidas, no usar el Servicio para actividades
                        engañosas, ilegales o que vulneren derechos de terceros,
                        y no intentar vulnerar la seguridad de la plataforma.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        9. Datos personales
                    </h2>
                    <p>
                        El tratamiento de datos personales — los tuyos y los de
                        los clientes que escriben a tu negocio — se rige por
                        nuestra
                        <router-link
                            :to="{ name: 'privacy' }"
                            class="text-brand-700 underline"
                            >Política de tratamiento de datos
                            personales</router-link
                        >. Respecto de los datos de tus clientes, tu negocio
                        actúa como responsable del tratamiento y Pilo como
                        encargado, procesándolos únicamente para prestarte el
                        Servicio.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        10. Propiedad intelectual
                    </h2>
                    <p>
                        La plataforma, su código, diseño y marca son de nuestra
                        propiedad o de nuestros licenciantes. La información de
                        tu negocio y el contenido de tus conversaciones siguen
                        siendo tuyos; nos autorizas a procesarlos únicamente
                        para operar el Servicio.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        11. Responsabilidad
                    </h2>
                    <p>
                        Prestamos el Servicio con diligencia, pero no
                        garantizamos disponibilidad ininterrumpida ni la
                        exactitud de cada respuesta generada por la inteligencia
                        artificial. En la máxima medida permitida por la ley
                        colombiana, nuestra responsabilidad total frente a ti se
                        limita al valor pagado por el Servicio en los tres (3)
                        meses anteriores al hecho que la origine. Nada en estos
                        términos limita los derechos que la ley te otorga como
                        consumidor.
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        12. Peticiones, quejas y reclamos (PQRS)
                    </h2>
                    <p>
                        Puedes presentar peticiones, quejas o reclamos
                        escribiendo a {{ company.email }} o al WhatsApp
                        {{ company.whatsapp }}. Responderemos dentro de los
                        quince (15) días hábiles siguientes. También puedes
                        acudir a la Superintendencia de Industria y Comercio
                        (SIC), autoridad de protección al consumidor en Colombia
                        (<a
                            href="https://www.sic.gov.co"
                            target="_blank"
                            rel="noopener"
                            class="text-brand-700 underline"
                            >www.sic.gov.co</a
                        >).
                    </p>
                </section>

                <section>
                    <h2
                        class="mb-2 font-display text-xl font-semibold text-brand-950"
                    >
                        13. Ley aplicable y modificaciones
                    </h2>
                    <p>
                        Estos términos se rigen por las leyes de la República de
                        Colombia. Podemos actualizarlos; si el cambio es
                        sustancial te lo notificaremos por correo o desde el
                        panel con al menos quince (15) días de anticipación, y
                        podrás cancelar sin costo si no estás de acuerdo. Esta
                        página está disponible para consulta, impresión y
                        descarga en todo momento.
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>
