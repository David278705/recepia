<?php

namespace App\Services\Wompi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente para la API de Wompi (Bancolombia). Los cobros recurrentes se hacen
 * con una fuente de pago (tarjeta tokenizada en el frontend con la llave
 * pública) contra la que se crean transacciones cada mes.
 * https://docs.wompi.co/docs/colombia/fuentes-de-pago/
 */
class WompiService
{
    protected string $baseUrl;

    protected string $publicKey;

    protected string $privateKey;

    protected string $eventsSecret;

    protected string $integritySecret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.wompi.base_url'), '/');
        $this->publicKey = (string) config('services.wompi.public_key');
        $this->privateKey = (string) config('services.wompi.private_key');
        $this->eventsSecret = (string) config('services.wompi.events_secret');
        $this->integritySecret = (string) config('services.wompi.integrity_secret');
    }

    /**
     * Firma de integridad de una transacción:
     * SHA256(referencia + monto_en_centavos + moneda + secreto_de_integridad).
     * Wompi la exige en el Widget y en la API cuando la cuenta la tiene
     * activada. https://docs.wompi.co/docs/colombia/widget-checkout-web/
     */
    public function integritySignature(string $reference, int $amountCents, string $currency): ?string
    {
        if (! $this->integritySecret) {
            return null;
        }

        return hash('sha256', $reference.$amountCents.$currency.$this->integritySecret);
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function hasIntegritySecret(): bool
    {
        return $this->integritySecret !== '';
    }

    /**
     * Cliente HTTP base hacia Wompi. Fuerza resolución IPv4 y reintenta las
     * fallas de conexión: en Windows/XAMPP la resolución DNS de PHP falla de
     * forma intermitente (cURL error 6) aunque el sistema sí resuelva.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout(20)
            ->connectTimeout(10)
            ->withOptions(['force_ip_resolve' => 'v4'])
            ->retry(3, 750, fn ($exception) => $exception instanceof \Illuminate\Http\Client\ConnectionException, throw: false)
            ->acceptJson();
    }

    protected function client(): PendingRequest
    {
        if (! $this->privateKey) {
            throw new RuntimeException('WOMPI_PRIVATE_KEY no está configurado.');
        }

        return $this->http()->withToken($this->privateKey);
    }

    /**
     * Token de aceptación de términos y condiciones, requerido por Wompi para
     * crear fuentes de pago. Se obtiene con la llave pública.
     *
     * @return array{acceptance_token: string, permalink: string}
     */
    public function acceptanceToken(): array
    {
        $response = $this->http()->get("/merchants/{$this->publicKey}");

        $response->throw();

        $data = $response->json('data.presigned_acceptance');

        if (! isset($data['acceptance_token'])) {
            throw new RuntimeException('Wompi no devolvió el acceptance_token del comercio.');
        }

        return $data;
    }

    /**
     * Tokeniza una tarjeta desde el backend (con la llave pública). Los datos
     * viajan del navegador a nuestro servidor por HTTPS y de aquí a Wompi;
     * nunca se guardan. Se hace server-side para evitar los problemas de
     * CORS del endpoint de tokens cuando se llama desde el navegador.
     *
     * @param  array{number: string, cvc: string, exp_month: string, exp_year: string, card_holder: string}  $card
     * @return array El token creado (id, brand, last_four, ...)
     */
    public function tokenizeCard(array $card): array
    {
        if (! $this->publicKey) {
            throw new RuntimeException('WOMPI_PUBLIC_KEY no está configurado.');
        }

        $response = $this->http()
            ->withToken($this->publicKey)
            ->post('/tokens/cards', [
                'number' => $card['number'],
                'cvc' => $card['cvc'],
                'exp_month' => str_pad($card['exp_month'], 2, '0', STR_PAD_LEFT),
                'exp_year' => $card['exp_year'],
                'card_holder' => $card['card_holder'],
            ]);

        $response->throw();

        return $response->json('data');
    }

    /**
     * Crea una fuente de pago recurrente a partir de un token de tarjeta
     * generado en el frontend.
     *
     * @return array La fuente de pago (id, public_data con brand/last_four, status)
     */
    public function createPaymentSource(string $cardToken, string $customerEmail, string $acceptanceToken): array
    {
        $response = $this->client()->post('/payment_sources', [
            'type' => 'CARD',
            'token' => $cardToken,
            'customer_email' => $customerEmail,
            'acceptance_token' => $acceptanceToken,
        ]);

        $response->throw();

        return $response->json('data');
    }

    /**
     * Cobra a una fuente de pago existente (cargo recurrente).
     *
     * @return array La transacción creada (id, status normalmente PENDING)
     */
    public function chargePaymentSource(
        int|string $paymentSourceId,
        int $amountCents,
        string $currency,
        string $customerEmail,
        string $reference,
    ): array {
        $payload = [
            'amount_in_cents' => $amountCents,
            'currency' => $currency,
            'customer_email' => $customerEmail,
            'reference' => $reference,
            'payment_source_id' => (int) $paymentSourceId,
            'recurrent' => true,
            // Wompi exige el número de cuotas en cobros con tarjeta; la
            // suscripción siempre se cobra a una sola cuota.
            'payment_method' => ['installments' => 1],
        ];

        if ($signature = $this->integritySignature($reference, $amountCents, $currency)) {
            $payload['signature'] = $signature;
        }

        $response = $this->client()->post('/transactions', $payload);

        $response->throw();

        return $response->json('data');
    }

    /**
     * Bancos disponibles para PSE (el cliente debe elegir uno).
     */
    public function financialInstitutions(): array
    {
        $response = $this->client()->get('/pse/financial_institutions');

        $response->throw();

        return $response->json('data') ?? [];
    }

    /**
     * Crea una transacción de pago único con un método específico (NEQUI,
     * DAVIPLATA, PSE). El estado final llega por webhook o polling.
     *
     * @param  array  $paymentMethod  El objeto payment_method según el método
     * @param  array|null  $customerData  customer_data (requerido por PSE)
     */
    public function createPaymentTransaction(
        array $paymentMethod,
        int $amountCents,
        string $currency,
        string $customerEmail,
        string $reference,
        string $acceptanceToken,
        ?string $redirectUrl = null,
        ?array $customerData = null,
    ): array {
        $payload = [
            'amount_in_cents' => $amountCents,
            'currency' => $currency,
            'customer_email' => $customerEmail,
            'reference' => $reference,
            'acceptance_token' => $acceptanceToken,
            'payment_method' => $paymentMethod,
        ];

        if ($redirectUrl) {
            $payload['redirect_url'] = $redirectUrl;
        }

        if ($customerData) {
            $payload['customer_data'] = $customerData;
        }

        if ($signature = $this->integritySignature($reference, $amountCents, $currency)) {
            $payload['signature'] = $signature;
        }

        $response = $this->client()->post('/transactions', $payload);

        $response->throw();

        return $response->json('data');
    }

    /**
     * Espera a que la transacción exponga un campo (p. ej. la URL del banco
     * para PSE o la de OTP de DaviPlata, que Wompi genera unos instantes
     * después de crearla). Devuelve la transacción más reciente consultada.
     */
    public function waitForTransactionField(string $transactionId, string $path, int $attempts = 8, int $sleepSeconds = 1): array
    {
        $transaction = $this->getTransaction($transactionId);

        while ($attempts-- > 0) {
            if (data_get($transaction, $path) || ($transaction['status'] ?? 'PENDING') !== 'PENDING') {
                break;
            }

            sleep($sleepSeconds);
            $transaction = $this->getTransaction($transactionId);
        }

        return $transaction;
    }

    public function getTransaction(string $transactionId): array
    {
        $response = $this->client()->get("/transactions/{$transactionId}");

        $response->throw();

        return $response->json('data');
    }

    /**
     * Espera (con reintentos cortos) a que una transacción salga de PENDING.
     * Si sigue pendiente al agotar los intentos, devuelve el último estado:
     * el webhook de eventos la resolverá después.
     */
    public function waitForTransaction(string $transactionId, int $attempts = 6, int $sleepSeconds = 2): array
    {
        $transaction = $this->getTransaction($transactionId);

        while ($transaction['status'] === 'PENDING' && --$attempts > 0) {
            sleep($sleepSeconds);
            $transaction = $this->getTransaction($transactionId);
        }

        return $transaction;
    }

    /**
     * Valida el checksum de un evento del webhook de Wompi: SHA256 de la
     * concatenación de los valores listados en signature.properties, el
     * timestamp y el secreto de eventos.
     * https://docs.wompi.co/docs/colombia/eventos/
     */
    public function isValidEvent(array $payload): bool
    {
        if (! $this->eventsSecret) {
            return false;
        }

        $properties = $payload['signature']['properties'] ?? null;
        $checksum = $payload['signature']['checksum'] ?? null;
        $timestamp = $payload['timestamp'] ?? null;

        if (! is_array($properties) || ! $checksum || $timestamp === null) {
            return false;
        }

        $concatenated = '';

        foreach ($properties as $property) {
            $concatenated .= (string) data_get($payload, 'data.'.$property);
        }

        $expected = hash('sha256', $concatenated.$timestamp.$this->eventsSecret);

        return hash_equals($expected, strtolower((string) $checksum));
    }
}
