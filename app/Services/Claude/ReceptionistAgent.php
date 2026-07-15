<?php

namespace App\Services\Claude;

use App\Models\AgentLog;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\Message;
use App\Models\Service;
use App\Notifications\AppointmentBookedNotification;
use App\Notifications\ConversationEscalatedNotification;
use App\Services\WhatsApp\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Agente recepcionista: construye el prompt y el contexto de una
 * conversación, ejecuta el loop de tool use contra Claude y envía la
 * respuesta final por WhatsApp. Si algo falla en el camino, escala la
 * conversación en vez de dejar al cliente sin respuesta.
 */
class ReceptionistAgent
{
    protected const REASONS = ['no_sabe', 'cliente_lo_pidio', 'molestia', 'keyword'];

    public function respond(Conversation $conversation): void
    {
        $business = $conversation->business;

        if (! $business) {
            Log::error('RecepIA: conversación sin negocio asociado, no se puede invocar al agente.', ['conversation_id' => $conversation->id]);

            return;
        }

        try {
            $system = $this->buildSystemPrompt($business);
            $messages = $this->buildMessageHistory($conversation);

            if (! $messages) {
                return;
            }

            $result = $this->runToolLoop($business, $system, $messages, $conversation, dryRun: false);

            $this->sendReply($business, $conversation, $result, $system);
        } catch (Throwable $e) {
            Log::error('RecepIA: el agente falló, se escala la conversación.', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $this->escalarAHumano($business, $conversation, ['motivo' => 'no_sabe']);
            $this->sendFallbackReply($business, $conversation);
        }
    }

    /**
     * Chat de prueba desde el panel ("Probar mi bot"): ejecuta el mismo
     * prompt y las mismas herramientas, pero en modo dry-run (no crea citas
     * ni escalaciones reales) y sin pasar por WhatsApp.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function testReply(Business $business, array $messages): string
    {
        return $this->testConversation($business, $messages)['text'];
    }

    /**
     * Igual que testReply(), pero devuelve también las herramientas que se
     * invocaron y el conteo de tokens — para depurar el prompt desde
     * `php artisan recepia:simular`.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, input_tokens: int, output_tokens: int, tool_calls: array}
     */
    public function testConversation(Business $business, array $messages): array
    {
        $system = $this->buildSystemPrompt($business);
        $result = $this->runToolLoop($business, $system, $messages, null, dryRun: true);

        $toolCalls = collect($result['messages'])
            ->where('role', 'assistant')
            ->flatMap(fn ($m) => collect($m['content'])->where('type', 'tool_use'))
            ->map(fn ($t) => ['name' => $t['name'], 'input' => $t['input'] ?? []])
            ->values()
            ->all();

        return [
            'text' => $result['text'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'tool_calls' => $toolCalls,
        ];
    }

    protected function sendFallbackReply(Business $business, Conversation $conversation): void
    {
        $text = $this->escalationReplyText($business);
        $contact = $conversation->contact;

        try {
            $response = WhatsAppService::forBusiness($business)->sendText($contact->wa_id, $text);

            $conversation->messages()->create([
                'business_id' => $business->id,
                'direction' => 'out',
                'origin' => 'bot',
                'type' => 'text',
                'content' => $text,
                'wamid' => $response->json('messages.0.id'),
                'delivery_status' => 'sent',
            ]);
        } catch (Throwable) {
            // El error de fondo ya quedó registrado arriba; no hay más que
            // intentar si tampoco se puede enviar el mensaje de fallback.
        }
    }

    /**
     * Ejecuta el loop respuesta → tool_result → respuesta final contra
     * Claude. Cuando $conversation es null (chat de prueba) o $dryRun es
     * true, las herramientas con efectos secundarios (agendar_cita,
     * escalar_a_humano) se simulan sin tocar la base de datos.
     *
     * @return array{text: string, input_tokens: int, output_tokens: int, messages: array, last_response: ?array}
     */
    protected function runToolLoop(Business $business, string $system, array $messages, ?Conversation $conversation, bool $dryRun): array
    {
        $client = new ClaudeClient(model: $business->agent_model);
        $tools = $this->toolDefinitions($business);

        $finalText = null;
        $inputTokens = 0;
        $outputTokens = 0;
        $lastResponse = null;

        for ($round = 0; $round < config('recepia.agent.max_tool_rounds', 5); $round++) {
            $lastResponse = $client->send($messages, $system, $tools);

            $inputTokens += $lastResponse['usage']['input_tokens'] ?? 0;
            $outputTokens += $lastResponse['usage']['output_tokens'] ?? 0;

            $content = $lastResponse['content'] ?? [];
            $toolUses = collect($content)->where('type', 'tool_use')->values();

            if ($toolUses->isEmpty()) {
                $finalText = collect($content)->where('type', 'text')->pluck('text')->implode('');
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $content];

            $escalated = false;
            $toolResults = [];

            foreach ($toolUses as $toolUse) {
                $result = $this->executeTool($business, $conversation, $toolUse['name'], $toolUse['input'] ?? [], $dryRun);

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolUse['id'],
                    'content' => json_encode($result),
                ];

                if ($toolUse['name'] === 'escalar_a_humano') {
                    $escalated = true;
                }
            }

            if ($escalated) {
                $finalText = $this->escalationReplyText($business);
                break;
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return [
            'text' => $finalText ?? '¿Me lo puedes repetir? No logré entenderlo bien 🙏',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'messages' => $messages,
            'last_response' => $lastResponse,
        ];
    }

    protected function buildSystemPrompt(Business $business): string
    {
        $now = Carbon::now($business->timezone)->locale('es');
        $dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $tone = $business->tone === 'formal' ? 'formal y profesional, tratando de usted' : 'cercano, cálido y tratando de tú';

        // Tabla explícita de los próximos 7 días: es la forma más confiable de
        // que el modelo convierta "mañana", "el viernes", etc. a una fecha
        // exacta sin calcularla él mismo (y equivocarse).
        $upcomingDays = collect(range(0, 6))->map(function (int $offset) use ($now, $dayNames) {
            $day = $now->copy()->addDays($offset)->startOfDay();
            $suffix = match ($offset) {
                0 => ' (HOY)',
                1 => ' (MAÑANA)',
                default => '',
            };

            return '- '.$dayNames[$day->dayOfWeek].' = '.$day->toDateString().$suffix;
        })->implode("\n");

        $temporalContext = <<<CONTEXT
            FECHA Y HORA ACTUAL (zona horaria {$business->timezone}):
            - Ahora mismo es {$dayNames[$now->dayOfWeek]} {$now->toDateString()} y son las {$now->format('H:i')} (formato 24 horas).
            - Próximos 7 días:
            {$upcomingDays}
            - Cuando el cliente use expresiones relativas ("hoy", "mañana", "pasado mañana", "el viernes"), conviértelas SIEMPRE a la fecha exacta usando la tabla anterior antes de llamar a las herramientas. No calcules fechas por tu cuenta.
            - Nunca propongas, confirmes ni agendes un horario anterior a la hora actual ({$now->format('H:i')} de hoy).
            CONTEXT;


        $services = $business->services()->where('active', true)->get()
            ->map(function (Service $s) {
                $duration = $s->duration_minutes !== null
                    ? "{$s->duration_minutes} min"
                    : 'no se agenda por este canal';

                $price = match (true) {
                    $s->price !== null => '$'.number_format((float) $s->price, 0, ',', '.').($s->price_note ? " ({$s->price_note})" : ''),
                    $s->price_note !== null => $s->price_note,
                    default => 'el precio te lo confirma el equipo',
                };

                return "- {$s->name} (id {$s->id}, {$duration}): {$price}";
            })->implode("\n");

        $hours = $business->businessHours()->where('active', true)->orderBy('day_of_week')->get()
            ->groupBy('day_of_week')
            ->map(fn ($group, $day) => $dayNames[$day].': '.$group->map(fn ($h) => substr($h->opens_at, 0, 5).'–'.substr($h->closes_at, 0, 5))->implode(', '))
            ->implode("\n");

        $faqs = $business->faqs()->where('active', true)->get()
            ->map(fn (Faq $f) => "P: {$f->question}\nR: {$f->answer}")->implode("\n\n");

        $services = $services ?: 'Sin servicios configurados.';
        $hours = $hours ?: 'Sin horarios configurados.';
        $faqs = $faqs ?: 'Sin preguntas frecuentes configuradas.';
        $extra = $business->extra_instructions ?: 'Ninguna.';
        $description = $business->description ?: 'Sin descripción adicional.';
        $capabilityRules = $this->capabilityRules($business);

        return <<<PROMPT
            Eres el asistente de WhatsApp de {$business->name}, un negocio de tipo "{$business->type}" ubicado en {$business->address}.
            Tu tono es {$tone}.

            SOBRE EL NEGOCIO:
            {$description}

            {$temporalContext}

            SERVICIOS:
            {$services}

            HORARIOS:
            {$hours}

            PREGUNTAS FRECUENTES:
            {$faqs}

            INSTRUCCIONES ADICIONALES DEL DUEÑO:
            {$extra}

            REGLAS (no las rompas nunca):
            - Responde SOLO con la información provista arriba. Si algo no está aquí, usa la herramienta escalar_a_humano en vez de adivinar.
            {$capabilityRules}
            - Respuestas cortas, estilo WhatsApp: 1 a 3 oraciones, sin markdown (sin negritas, sin listas, sin encabezados).
            - Siempre en español.
            - Si el cliente pide hablar con una persona, o detectas molestia o urgencia, usa escalar_a_humano de inmediato.
            PROMPT;
    }

    /**
     * Reglas del prompt que dependen de las capacidades activas del negocio.
     */
    protected function capabilityRules(Business $business): string
    {
        $rules = [];

        if ($business->hasCapability('agendar')) {
            $rules[] = '- Nunca inventes precios ni disponibilidad — para disponibilidad usa siempre consultar_disponibilidad y agenda con agendar_cita.';
        } else {
            $rules[] = '- Nunca inventes precios. Este negocio no agenda citas por este canal: no ofrezcas agendar.';
        }

        if ($business->hasCapability('pedidos')) {
            $rules[] = '- Puedes tomar pedidos con tomar_pedido: confirma primero los ítems, cantidades y la forma de entrega (domicilio o recoger) antes de registrarlo, y pide el nombre del cliente.';
        }

        if ($business->hasCapability('cotizar')) {
            $rules[] = '- Cuando el cliente necesite una cotización o algo que requiera respuesta del negocio, captura los detalles con registrar_solicitud y dile que lo contactarán pronto.';
        }

        return implode("\n", $rules);
    }

    protected function buildMessageHistory(Conversation $conversation): array
    {
        $limit = config('recepia.agent.context_messages', 20);

        $mapped = $conversation->messages()
            ->whereIn('origin', ['cliente', 'bot'])
            ->whereNotNull('content')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (Message $m) => [
                'role' => $m->origin === 'cliente' ? 'user' : 'assistant',
                'content' => $m->content,
            ])
            ->values()
            ->all();

        // La API de Claude exige que el primer turno sea del usuario.
        while ($mapped && $mapped[0]['role'] !== 'user') {
            array_shift($mapped);
        }

        return $mapped;
    }

    /**
     * Herramientas disponibles según las capacidades activas del negocio:
     * agendar → disponibilidad + citas; pedidos → tomar_pedido; cotizar →
     * registrar_solicitud. Escalar a humano está siempre.
     */
    protected function toolDefinitions(Business $business): array
    {
        $tools = [];

        if ($business->hasCapability('agendar')) {
            $tools[] = [
                'name' => 'consultar_disponibilidad',
                'description' => 'Consulta los horarios disponibles de un servicio en una fecha específica, calculados a partir de los horarios del negocio y las citas ya agendadas.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'servicio_id' => ['type' => 'integer', 'description' => 'ID del servicio a consultar'],
                        'fecha' => ['type' => 'string', 'description' => 'Fecha en formato YYYY-MM-DD'],
                    ],
                    'required' => ['servicio_id', 'fecha'],
                ],
            ];
            $tools[] = [
                'name' => 'agendar_cita',
                'description' => 'Agenda una cita confirmada para el cliente en un horario disponible (verifícalo antes con consultar_disponibilidad).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'servicio_id' => ['type' => 'integer', 'description' => 'ID del servicio'],
                        'inicio' => ['type' => 'string', 'description' => 'Fecha y hora de inicio en formato YYYY-MM-DD HH:mm, hora local del negocio'],
                        'nombre_cliente' => ['type' => 'string', 'description' => 'Nombre del cliente para la cita'],
                    ],
                    'required' => ['servicio_id', 'inicio', 'nombre_cliente'],
                ],
            ];
        }

        if ($business->hasCapability('pedidos')) {
            $tools[] = [
                'name' => 'tomar_pedido',
                'description' => 'Registra un pedido del cliente con los productos o servicios que quiere. Confirma antes los ítems, cantidades y la forma de entrega.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'description' => 'Ítems del pedido',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'nombre' => ['type' => 'string', 'description' => 'Nombre del producto o servicio'],
                                    'cantidad' => ['type' => 'integer', 'description' => 'Cantidad (mínimo 1)'],
                                    'nota' => ['type' => 'string', 'description' => 'Variación o aclaración del ítem (opcional)'],
                                ],
                                'required' => ['nombre', 'cantidad'],
                            ],
                        ],
                        'nombre_cliente' => ['type' => 'string', 'description' => 'Nombre del cliente'],
                        'entrega' => ['type' => 'string', 'enum' => ['domicilio', 'recoger'], 'description' => 'Forma de entrega'],
                        'direccion' => ['type' => 'string', 'description' => 'Dirección de entrega si es a domicilio'],
                        'nota' => ['type' => 'string', 'description' => 'Nota general del pedido (opcional)'],
                    ],
                    'required' => ['items', 'nombre_cliente', 'entrega'],
                ],
            ];
        }

        if ($business->hasCapability('cotizar')) {
            $tools[] = [
                'name' => 'registrar_solicitud',
                'description' => 'Registra una solicitud de cotización o información que requiere respuesta del negocio: qué necesita el cliente, con los detalles que haya dado. El negocio lo contactará con la cotización.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'resumen' => ['type' => 'string', 'description' => 'Resumen corto de lo que el cliente necesita'],
                        'detalles' => ['type' => 'string', 'description' => 'Detalles adicionales: cantidades, fechas, presupuesto, referencias (opcional)'],
                        'nombre_cliente' => ['type' => 'string', 'description' => 'Nombre del cliente'],
                    ],
                    'required' => ['resumen', 'nombre_cliente'],
                ],
            ];
        }

        $tools[] = [
                'name' => 'escalar_a_humano',
                'description' => 'Escala la conversación a un humano: úsala cuando no sepas la respuesta con la información disponible, el cliente pida hablar con una persona, detectes molestia o urgencia, o el mensaje contenga una palabra clave que lo amerite.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'motivo' => [
                            'type' => 'string',
                            'enum' => self::REASONS,
                            'description' => 'Motivo de la escalación',
                        ],
                    ],
                    'required' => ['motivo'],
                ],
        ];

        return $tools;
    }

    protected function executeTool(Business $business, ?Conversation $conversation, string $name, array $input, bool $dryRun = false): array
    {
        return match ($name) {
            'consultar_disponibilidad' => $this->consultarDisponibilidad($business, $input),
            'agendar_cita' => $this->agendarCita($business, $conversation, $input, $dryRun),
            'tomar_pedido' => $this->registrarCustomerRequest($business, $conversation, 'pedido', $input, $dryRun),
            'registrar_solicitud' => $this->registrarCustomerRequest($business, $conversation, 'cotizacion', $input, $dryRun),
            'escalar_a_humano' => $this->escalarAHumano($business, $conversation, $input, $dryRun),
            default => ['error' => "Herramienta desconocida: {$name}"],
        };
    }

    /**
     * Registra un pedido o una solicitud de cotización como CustomerRequest
     * y notifica al dueño. El nombre del cliente actualiza el contacto igual
     * que al agendar una cita.
     */
    protected function registrarCustomerRequest(Business $business, ?Conversation $conversation, string $type, array $input, bool $dryRun): array
    {
        if ($dryRun || ! $conversation) {
            return ['registrado' => true, 'simulado' => true, 'tipo' => $type];
        }

        $contact = $conversation->contact;

        if (! empty($input['nombre_cliente']) && ! $contact->name) {
            $contact->update(['name' => $input['nombre_cliente']]);
        }

        $payload = collect($input)->except('nombre_cliente')->all();

        $request = \App\Models\CustomerRequest::create([
            'business_id' => $business->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'type' => $type,
            'payload' => $payload,
        ]);

        $business->owner?->notify(new \App\Notifications\CustomerRequestReceivedNotification($request));

        return [
            'registrado' => true,
            'tipo' => $type,
            'cliente' => $contact->fresh()->name ?? ($input['nombre_cliente'] ?? null),
        ];
    }

    protected function consultarDisponibilidad(Business $business, array $input): array
    {
        $service = $business->services()->where('active', true)->find($input['servicio_id'] ?? null);

        if (! $service) {
            return ['error' => 'Servicio no encontrado.'];
        }

        if ($service->duration_minutes === null) {
            return ['error' => 'Este servicio no se agenda por este canal; solo se informa. No ofrezcas horarios para él.'];
        }

        try {
            $localDate = Carbon::createFromFormat('Y-m-d', (string) ($input['fecha'] ?? ''), $business->timezone)->startOfDay();
        } catch (Throwable) {
            return ['error' => 'Fecha inválida, usa formato YYYY-MM-DD.'];
        }

        $hours = $business->businessHours()
            ->where('day_of_week', $localDate->dayOfWeek)
            ->where('active', true)
            ->get();

        if ($hours->isEmpty()) {
            return ['servicio' => $service->name, 'fecha' => $localDate->toDateString(), 'slots' => [], 'mensaje' => 'El negocio no abre ese día.'];
        }

        $existing = $business->appointments()
            ->whereIn('status', ['propuesta', 'confirmada'])
            ->whereBetween('starts_at', [$localDate->copy()->utc(), $localDate->copy()->endOfDay()->utc()])
            ->get(['starts_at', 'ends_at']);

        $duration = $service->duration_minutes;
        $slots = [];
        $now = Carbon::now($business->timezone);

        foreach ($hours as $hour) {
            $cursor = $localDate->copy()->setTimeFromTimeString($hour->opens_at);
            $closes = $localDate->copy()->setTimeFromTimeString($hour->closes_at);

            while ($cursor->copy()->addMinutes($duration)->lte($closes)) {
                $slotEnd = $cursor->copy()->addMinutes($duration);

                $overlaps = $existing->contains(function ($appt) use ($cursor, $slotEnd) {
                    $apptStart = $appt->starts_at->copy()->setTimezone($cursor->timezone);
                    $apptEnd = $appt->ends_at->copy()->setTimezone($cursor->timezone);

                    return $cursor->lt($apptEnd) && $slotEnd->gt($apptStart);
                });

                if (! $overlaps && $cursor->gt($now)) {
                    $slots[] = $cursor->format('H:i');
                }

                $cursor->addMinutes(15);
            }
        }

        return [
            'servicio' => $service->name,
            'fecha' => $localDate->toDateString(),
            'ahora' => $now->format('Y-m-d H:i'),
            'slots' => array_slice($slots, 0, 12),
        ];
    }

    protected function agendarCita(Business $business, ?Conversation $conversation, array $input, bool $dryRun = false): array
    {
        $service = $business->services()->where('active', true)->find($input['servicio_id'] ?? null);

        if (! $service) {
            return ['error' => 'Servicio no encontrado.'];
        }

        if ($service->duration_minutes === null) {
            return ['error' => 'Este servicio no se agenda por este canal; solo se informa. No lo agendes.'];
        }

        try {
            $startsLocal = Carbon::parse((string) ($input['inicio'] ?? ''), $business->timezone);
        } catch (Throwable) {
            return ['error' => 'Fecha/hora de inicio inválida.'];
        }

        $now = Carbon::now($business->timezone);

        if ($startsLocal->lte($now)) {
            return [
                'error' => 'Ese horario ya pasó. Ahora mismo son las '.$now->format('H:i').' del '.$now->toDateString().'; propón un horario futuro.',
            ];
        }

        $nombreCliente = trim((string) ($input['nombre_cliente'] ?? ''));

        if ($dryRun || ! $conversation) {
            return [
                'confirmado' => true,
                'simulado' => true,
                'servicio' => $service->name,
                'inicio' => $startsLocal->format('Y-m-d H:i'),
                'cliente' => $nombreCliente ?: 'Cliente de prueba',
            ];
        }

        $endsLocal = $startsLocal->copy()->addMinutes($service->duration_minutes);
        $startsUtc = $startsLocal->copy()->utc();
        $endsUtc = $endsLocal->copy()->utc();

        $overlap = $business->appointments()
            ->whereIn('status', ['propuesta', 'confirmada'])
            ->where('starts_at', '<', $endsUtc)
            ->where('ends_at', '>', $startsUtc)
            ->exists();

        if ($overlap) {
            return ['error' => 'Ese horario ya no está disponible, por favor elige otro.'];
        }

        $contact = $conversation->contact;

        if ($nombreCliente && ! $contact->name) {
            $contact->update(['name' => $nombreCliente]);
        }

        $appointment = $business->appointments()->create([
            'contact_id' => $contact->id,
            'service_id' => $service->id,
            'starts_at' => $startsUtc,
            'ends_at' => $endsUtc,
            'status' => 'confirmada',
            'origin' => 'bot',
        ]);

        $business->owner?->notify(new AppointmentBookedNotification($appointment));

        return [
            'confirmado' => true,
            'servicio' => $service->name,
            'inicio' => $startsLocal->format('Y-m-d H:i'),
            'cliente' => $contact->fresh()->name ?? $nombreCliente,
        ];
    }

    protected function escalarAHumano(Business $business, ?Conversation $conversation, array $input, bool $dryRun = false): array
    {
        $motivo = in_array($input['motivo'] ?? null, self::REASONS, true) ? $input['motivo'] : 'no_sabe';

        if ($dryRun || ! $conversation) {
            return ['escalado' => true, 'simulado' => true, 'motivo' => $motivo];
        }

        $conversation->update(['status' => 'escalada']);
        $conversation->escalations()->create([
            'business_id' => $business->id,
            'reason' => $motivo,
        ]);

        $business->owner?->notify(new ConversationEscalatedNotification($conversation, $motivo));

        return ['escalado' => true, 'motivo' => $motivo];
    }

    protected function escalationReplyText(Business $business): string
    {
        $ownerName = $business->owner?->name ?? 'el equipo';

        return "Ya le aviso a {$ownerName}, te contacta pronto 👍";
    }

    protected function sendReply(Business $business, Conversation $conversation, array $result, string $system): void
    {
        $contact = $conversation->contact;
        $wamid = null;

        try {
            $response = WhatsAppService::forBusiness($business)->sendText($contact->wa_id, $result['text']);
            $wamid = $response->json('messages.0.id');
        } catch (Throwable $e) {
            Log::error('RecepIA: no se pudo enviar la respuesta del bot por WhatsApp.', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }

        $model = $business->agent_model ?: config('services.anthropic.model');
        $cost = $this->estimateCost($model, $result['input_tokens'], $result['output_tokens']);

        $message = $conversation->messages()->create([
            'business_id' => $business->id,
            'direction' => 'out',
            'origin' => 'bot',
            'type' => 'text',
            'content' => $result['text'],
            'wamid' => $wamid,
            'delivery_status' => $wamid ? 'sent' : 'failed',
            'tokens_used' => $result['input_tokens'] + $result['output_tokens'],
            'estimated_cost' => $cost,
        ]);

        AgentLog::create([
            'business_id' => $business->id,
            'message_id' => $message->id,
            'model' => $model,
            'request' => ['system' => $system, 'messages' => $result['messages']],
            'response' => $result['last_response'],
            'tokens_input' => $result['input_tokens'],
            'tokens_output' => $result['output_tokens'],
            'estimated_cost' => $cost,
        ]);

        $conversation->update(['last_activity_at' => now()]);
    }

    protected function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = config("recepia.agent.pricing.{$model}") ?? config('recepia.agent.default_pricing');

        return round(
            ($inputTokens / 1_000_000 * $pricing['input']) + ($outputTokens / 1_000_000 * $pricing['output']),
            4
        );
    }
}
