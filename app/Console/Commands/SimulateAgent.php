<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Claude\ReceptionistAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Ejecuta el agente recepcionista por consola, sin pasar por WhatsApp, para
 * iterar prompts rápido. Mantiene el hilo de la conversación entre llamadas
 * (un archivo de sesión por negocio) hasta que se use --reset.
 */
class SimulateAgent extends Command
{
    protected $signature = 'pilo:simular
        {mensaje : Lo que "escribe" el cliente}
        {--business= : Slug o ID del negocio (por defecto, el negocio demo)}
        {--reset : Empezar una conversación de prueba nueva en vez de continuar la anterior}';

    protected $description = 'Simula un mensaje de cliente contra el agente recepcionista, sin WhatsApp.';

    public function handle(ReceptionistAgent $agent): int
    {
        $business = $this->resolveBusiness();

        if (! $business) {
            $this->error('No encontré ese negocio. Usa --business=<slug|id> o corre el seeder de demo primero.');

            return self::FAILURE;
        }

        $sessionPath = "pilo-simular/{$business->id}.json";

        if ($this->option('reset')) {
            Storage::delete($sessionPath);
        }

        $messages = Storage::exists($sessionPath)
            ? json_decode(Storage::get($sessionPath), true)
            : [];

        $messages[] = ['role' => 'user', 'content' => $this->argument('mensaje')];

        $this->line("<fg=blue>Cliente:</> {$this->argument('mensaje')}");

        $result = $agent->testConversation($business, $messages);

        $messages[] = ['role' => 'assistant', 'content' => $result['text']];
        Storage::put($sessionPath, json_encode($messages));

        $this->newLine();
        $this->line("<fg=green>Bot ({$business->name}):</> {$result['text']}");

        if ($result['tool_calls']) {
            $this->newLine();
            $this->comment('Herramientas usadas:');
            foreach ($result['tool_calls'] as $call) {
                $this->line("  - {$call['name']}(".json_encode($call['input']).')');
            }
        }

        $this->newLine();
        $this->comment("Tokens: {$result['input_tokens']} entrada / {$result['output_tokens']} salida");
        $this->comment('Conversación de prueba con '.count($messages).' mensajes. Usa --reset para empezar de nuevo.');

        return self::SUCCESS;
    }

    protected function resolveBusiness(): ?Business
    {
        $identifier = $this->option('business');

        if (! $identifier) {
            return Business::where('slug', 'barberia-el-corte')->first() ?? Business::first();
        }

        return is_numeric($identifier)
            ? Business::find($identifier)
            : Business::where('slug', $identifier)->first();
    }
}
