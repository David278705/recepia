<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsappAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Un negocio demo completo (barbería) para desarrollo local y demos del
 * producto: servicios, horarios, FAQs, un WhatsApp conectado, varios
 * contactos con conversaciones en distintos estados, y citas pasadas y
 * futuras — para que el dashboard, la bandeja y el calendario se vean con
 * datos realistas desde el primer `migrate:fresh --seed`.
 */
class BusinessDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'demo@pilo.test'],
            [
                'name' => 'Carlos Ramírez',
                'password' => Hash::make('password'),
                'role' => 'owner',
            ]
        );

        $business = Business::updateOrCreate(
            ['user_id' => $owner->id],
            [
                'name' => 'Barbería El Corte',
                'slug' => 'barberia-el-corte',
                'type' => 'barberia',
                'address' => 'Calle 45 #12-30, Bogotá',
                'phone' => '+573001234567',
                'timezone' => 'America/Bogota',
                'status' => 'activo',
                'tone' => 'cercano',
                'extra_instructions' => 'Si preguntan por parqueadero, di que hay uno público a media cuadra.',
            ]
        );

        WhatsappAccount::updateOrCreate(
            ['business_id' => $business->id],
            [
                'phone_number_id' => env('WHATSAPP_DEMO_PHONE_NUMBER_ID') ?: '109876543210',
                'waba_id' => env('WHATSAPP_DEMO_WABA_ID') ?: '123456789012345',
                'phone_e164' => env('WHATSAPP_DEMO_PHONE_E164') ?: '+573001234567',
                'access_token' => env('WHATSAPP_DEMO_ACCESS_TOKEN') ?: 'demo-access-token',
                'verify_token' => config('services.whatsapp.verify_token') ?: 'demo-verify-token',
                'mode' => 'coexistence',
                'connection_status' => env('WHATSAPP_DEMO_PHONE_NUMBER_ID') ? 'conectado' : 'pendiente',
            ]
        );

        $services = collect([
            ['name' => 'Corte de cabello', 'description' => 'Corte clásico o a la moda', 'duration_minutes' => 30, 'price' => 25000],
            ['name' => 'Barba', 'description' => 'Perfilado y arreglo de barba', 'duration_minutes' => 20, 'price' => 15000],
            ['name' => 'Corte + barba', 'description' => 'Combo completo', 'duration_minutes' => 45, 'price' => 35000],
            ['name' => 'Tinte', 'description' => null, 'duration_minutes' => 60, 'price' => null],
        ])->map(fn (array $data) => Service::updateOrCreate(
            ['business_id' => $business->id, 'name' => $data['name']],
            [...$data, 'active' => true]
        ));

        foreach (range(1, 6) as $day) { // lunes(1) a sábado(6)
            BusinessHour::updateOrCreate(
                ['business_id' => $business->id, 'day_of_week' => $day],
                [
                    'opens_at' => '09:00',
                    'closes_at' => $day === 6 ? '14:00' : '19:00',
                    'active' => true,
                ]
            );
        }

        collect([
            ['question' => '¿Hasta qué hora atienden?', 'answer' => 'Lunes a viernes hasta las 7pm, sábados hasta la 1pm. Domingos cerrado.'],
            ['question' => '¿Aceptan pagos con tarjeta?', 'answer' => 'Sí, aceptamos efectivo, tarjeta y transferencia.'],
            ['question' => '¿Necesito reservar con anticipación?', 'answer' => 'Se puede llegar directo, pero para asegurar el horario que quieres, mejor agenda con anticipación.'],
        ])->each(fn (array $data) => Faq::updateOrCreate(
            ['business_id' => $business->id, 'question' => $data['question']],
            [...$data, 'active' => true]
        ));

        // --- Contacto 1: Andrés — conversación activa del bot, con una cita agendada por él mismo ---
        $andres = Contact::updateOrCreate(
            ['business_id' => $business->id, 'wa_id' => '573009876543'],
            ['name' => 'Andrés Torres', 'notes' => null]
        );

        $conversationAndres = Conversation::updateOrCreate(
            ['business_id' => $business->id, 'contact_id' => $andres->id],
            [
                'status' => 'bot_activo',
                'last_activity_at' => now()->subMinutes(10),
                'window_expires_at' => now()->addHours(23),
            ]
        );

        if ($conversationAndres->messages()->count() === 0) {
            $conversationAndres->messages()->createMany([
                ['business_id' => $business->id, 'direction' => 'in', 'origin' => 'cliente', 'type' => 'text', 'content' => 'Hola, ¿tienen turno para mañana en la tarde?', 'wamid' => 'demo-wamid-1', 'delivery_status' => 'delivered'],
                ['business_id' => $business->id, 'direction' => 'out', 'origin' => 'bot', 'type' => 'text', 'content' => 'Hola Andrés 👋 Sí, tenemos disponibilidad mañana a las 3:00pm o 4:30pm. ¿Cuál te queda mejor?', 'wamid' => 'demo-wamid-2', 'delivery_status' => 'read', 'tokens_used' => 180, 'estimated_cost' => 0.0021],
                ['business_id' => $business->id, 'direction' => 'in', 'origin' => 'cliente', 'type' => 'text', 'content' => 'A las 3pm está bien', 'wamid' => 'demo-wamid-3', 'delivery_status' => 'delivered'],
            ]);
        }

        Appointment::updateOrCreate(
            ['business_id' => $business->id, 'contact_id' => $andres->id, 'starts_at' => now()->addDay()->setTime(15, 0)],
            [
                'service_id' => $services->firstWhere('name', 'Corte de cabello')->id,
                'ends_at' => now()->addDay()->setTime(15, 30),
                'status' => 'confirmada',
                'origin' => 'bot',
                'notes' => null,
            ]
        );

        // --- Contacto 2: Valentina — el bot no supo responder y escaló, pendiente de atender ---
        $valentina = Contact::updateOrCreate(
            ['business_id' => $business->id, 'wa_id' => '573011112222'],
            ['name' => 'Valentina Gómez', 'notes' => null]
        );

        $conversationValentina = Conversation::updateOrCreate(
            ['business_id' => $business->id, 'contact_id' => $valentina->id],
            [
                'status' => 'escalada',
                'last_activity_at' => now()->subHours(2),
                'window_expires_at' => now()->addHours(22),
            ]
        );

        if ($conversationValentina->messages()->count() === 0) {
            $conversationValentina->messages()->createMany([
                ['business_id' => $business->id, 'direction' => 'in', 'origin' => 'cliente', 'type' => 'text', 'content' => '¿Hacen alisado japonés?', 'wamid' => 'demo-wamid-4', 'delivery_status' => 'delivered'],
                ['business_id' => $business->id, 'direction' => 'out', 'origin' => 'bot', 'type' => 'text', 'content' => 'Ya le aviso a Carlos Ramírez, te contacta pronto 👍', 'wamid' => 'demo-wamid-5', 'delivery_status' => 'read', 'tokens_used' => 210, 'estimated_cost' => 0.0025],
            ]);
        }

        if ($conversationValentina->escalations()->count() === 0) {
            $conversationValentina->escalations()->create([
                'business_id' => $business->id,
                'reason' => 'no_sabe',
            ]);
        }

        // --- Contacto 3: Miguel — conversación ya cerrada, cliente frecuente ---
        $miguel = Contact::updateOrCreate(
            ['business_id' => $business->id, 'wa_id' => '573033334444'],
            ['name' => 'Miguel Ángel Ruiz', 'notes' => 'Cliente frecuente, viene cada 3 semanas.']
        );

        $conversationMiguel = Conversation::updateOrCreate(
            ['business_id' => $business->id, 'contact_id' => $miguel->id],
            [
                'status' => 'cerrada',
                'last_activity_at' => now()->subDays(3),
                'window_expires_at' => now()->subDays(2),
            ]
        );

        if ($conversationMiguel->messages()->count() === 0) {
            $conversationMiguel->messages()->createMany([
                ['business_id' => $business->id, 'direction' => 'in', 'origin' => 'cliente', 'type' => 'text', 'content' => '¿Cuánto cuesta corte y barba?', 'wamid' => 'demo-wamid-6', 'delivery_status' => 'delivered'],
                ['business_id' => $business->id, 'direction' => 'out', 'origin' => 'bot', 'type' => 'text', 'content' => 'El combo de corte + barba está en $35.000. ¿Quieres que te agende?', 'wamid' => 'demo-wamid-7', 'delivery_status' => 'read', 'tokens_used' => 160, 'estimated_cost' => 0.0019],
                ['business_id' => $business->id, 'direction' => 'in', 'origin' => 'cliente', 'type' => 'text', 'content' => 'No por ahora, gracias', 'wamid' => 'demo-wamid-8', 'delivery_status' => 'delivered'],
            ]);
        }

        // Citas pasadas de Miguel: una completada y una a la que no asistió,
        // para que "Salud del sistema"/reportes tengan variedad de estados.
        Appointment::updateOrCreate(
            ['business_id' => $business->id, 'contact_id' => $miguel->id, 'starts_at' => now()->subWeeks(3)->setTime(11, 0)],
            [
                'service_id' => $services->firstWhere('name', 'Corte + barba')->id,
                'ends_at' => now()->subWeeks(3)->setTime(11, 45),
                'status' => 'completada',
                'origin' => 'bot',
                'notes' => null,
            ]
        );

        Appointment::updateOrCreate(
            ['business_id' => $business->id, 'contact_id' => $miguel->id, 'starts_at' => now()->subWeeks(6)->setTime(16, 0)],
            [
                'service_id' => $services->firstWhere('name', 'Corte de cabello')->id,
                'ends_at' => now()->subWeeks(6)->setTime(16, 30),
                'status' => 'no_asistio',
                'origin' => 'bot',
                'notes' => null,
            ]
        );

        // Una cita adicional a futuro, agendada manualmente desde el panel,
        // para que el calendario no muestre un único día con actividad.
        Appointment::updateOrCreate(
            ['business_id' => $business->id, 'contact_id' => $valentina->id, 'starts_at' => now()->addDays(3)->setTime(10, 0)],
            [
                'service_id' => $services->firstWhere('name', 'Barba')->id,
                'ends_at' => now()->addDays(3)->setTime(10, 20),
                'status' => 'confirmada',
                'origin' => 'panel',
                'notes' => 'Agendada por teléfono, confirmar con recordatorio.',
            ]
        );
    }
}
