<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fijar la versión del Graph API para que el test no dependa del
        // valor de WHATSAPP_GRAPH_VERSION en el .env local.
        Config::set('services.whatsapp.graph_version', 'v21.0');
    }

    public function test_send_text_posts_to_the_correct_endpoint_with_the_business_token(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $business = Business::factory()->create();
        $account = WhatsappAccount::factory()->create([
            'business_id' => $business->id,
            'phone_number_id' => '111222333',
            'access_token' => 'secret-token',
        ]);

        $service = WhatsAppService::forBusiness($business->fresh());
        $service->sendText('573001112222', 'Hola');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v21.0/111222333/messages'
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request['to'] === '573001112222'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Hola';
        });
    }

    public function test_send_buttons_builds_reply_buttons_from_the_array(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.2']]], 200)]);

        $business = Business::factory()->create();
        WhatsappAccount::factory()->create(['business_id' => $business->id]);

        WhatsAppService::forBusiness($business->fresh())->sendButtons('573001112222', '¿Confirmas?', [
            'confirm' => 'Sí, confirmar',
            'cancel' => 'Cancelar',
        ]);

        Http::assertSent(function ($request) {
            $buttons = $request['interactive']['action']['buttons'];

            return $request['interactive']['type'] === 'button'
                && count($buttons) === 2
                && $buttons[0]['reply']['id'] === 'confirm'
                && $buttons[0]['reply']['title'] === 'Sí, confirmar';
        });
    }

    public function test_for_business_throws_when_business_has_no_whatsapp_account(): void
    {
        $business = Business::factory()->create();

        $this->expectException(\RuntimeException::class);

        WhatsAppService::forBusiness($business);
    }
}
