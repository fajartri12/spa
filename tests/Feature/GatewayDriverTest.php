<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GatewayProvider;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use App\Services\Gateway\GatewayException;
use App\Services\Gateway\MidtransGateway;
use App\Services\Gateway\TripayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kontrak driver gateway terhadap penyedia asli, diuji dengan respons tiruan
 * agar pemetaan payload dan tanda tangan tidak bergantung pada jaringan.
 */
class GatewayDriverTest extends TestCase
{
    use RefreshDatabase;

    private function intent(GatewayProvider $provider, string $channel): PaymentIntent
    {
        return new PaymentIntent([
            'provider' => $provider->value,
            'channel' => $channel,
            'reference' => 'PG260101-ABCDEFGHIJ',
            'amount' => 250000,
            'status' => PaymentIntentStatus::Pending->value,
        ]);
    }

    private function configureTripay(): void
    {
        Config::set('payment_gateway.providers.tripay', [
            'enabled' => true,
            'mode' => 'sandbox',
            'base_url' => 'https://tripay.test/api-sandbox',
            'api_key' => 'tripay-api-key',
            'private_key' => 'tripay-private-key',
            'merchant_code' => 'T0001',
            'callback_url' => 'https://app.test/webhook/pembayaran/tripay',
            'channels' => ['BRIVA' => 'BRI Virtual Account', 'QRIS' => 'QRIS'],
        ]);
    }

    private function configureMidtrans(): void
    {
        Config::set('payment_gateway.providers.midtrans', [
            'enabled' => true,
            'mode' => 'sandbox',
            'base_url' => 'https://midtrans.test',
            'server_key' => 'midtrans-server-key',
            'client_key' => 'midtrans-client-key',
            'merchant_id' => 'M0001',
            'channels' => ['bri_va' => 'BRI Virtual Account', 'qris' => 'QRIS'],
        ]);
    }

    public function test_tripay_charge_maps_response_to_gateway_charge(): void
    {
        $this->configureTripay();

        Http::fake([
            'tripay.test/*' => Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'T0001-REF-1',
                    'pay_code' => '1234567890',
                    'pay_url' => 'https://tripay.test/pay/1',
                    'expired_time' => now()->addDay()->getTimestamp(),
                    'fee_customer' => 2500,
                ],
            ]),
        ]);

        $charge = app(TripayGateway::class)->createCharge($this->intent(GatewayProvider::Tripay, 'BRIVA'));

        $this->assertSame('1234567890', $charge->payCode);
        $this->assertSame('T0001-REF-1', $charge->providerReference);
        $this->assertSame(2500.0, $charge->fee);
        $this->assertNotNull($charge->expiresAt);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === 'https://tripay.test/api-sandbox/transaction/create'
                && $request->hasHeader('Authorization', 'Bearer tripay-api-key')
                && $body['method'] === 'BRIVA'
                && $body['merchant_ref'] === 'PG260101-ABCDEFGHIJ'
                && $body['amount'] === 250000
                && $body['signature'] === hash_hmac('sha256', 'T0001PG260101-ABCDEFGHIJ250000', 'tripay-private-key');
        });
    }

    public function test_tripay_rejection_throws_gateway_exception(): void
    {
        $this->configureTripay();

        Http::fake([
            'tripay.test/*' => Http::response(['success' => false, 'message' => 'Channel tidak aktif'], 400),
        ]);

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('Channel tidak aktif');

        app(TripayGateway::class)->createCharge($this->intent(GatewayProvider::Tripay, 'BRIVA'));
    }

    public function test_tripay_requires_complete_credentials(): void
    {
        Config::set('payment_gateway.providers.tripay', ['enabled' => true, 'api_key' => 'x']);

        $this->assertFalse(app(TripayGateway::class)->isConfigured());
    }

    public function test_tripay_falls_back_to_webhook_route_when_callback_blank(): void
    {
        $this->configureTripay();
        Config::set('payment_gateway.providers.tripay.callback_url', null);

        Http::fake([
            'tripay.test/*' => Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'T0001-REF-1',
                    'pay_code' => '1234567890',
                    'pay_url' => 'https://tripay.test/pay/1',
                    'expired_time' => now()->addDay()->getTimestamp(),
                    'fee_customer' => 0,
                ],
            ]),
        ]);

        app(TripayGateway::class)->createCharge($this->intent(GatewayProvider::Tripay, 'BRIVA'));

        Http::assertSent(fn (Request $request): bool => $request->data()['callback_url']
            === route('webhooks.payment', ['provider' => 'tripay']));
    }

    public function test_tripay_verifies_callback_signature(): void
    {
        $this->configureTripay();

        $body = ['merchant_ref' => 'PG260101-ABCDEFGHIJ', 'status' => 'PAID', 'amount' => 250000];
        $raw = json_encode($body);
        $signature = hash_hmac('sha256', $raw, 'tripay-private-key');

        $valid = HttpRequest::create(
            '/webhook/pembayaran/tripay',
            'POST',
            [],
            [],
            [],
            ['HTTP_X_CALLBACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $raw,
        );

        $this->assertTrue(app(TripayGateway::class)->verifySignature($valid));

        $invalid = HttpRequest::create(
            '/webhook/pembayaran/tripay',
            'POST',
            [],
            [],
            [],
            ['HTTP_X_CALLBACK_SIGNATURE' => 'palsu', 'CONTENT_TYPE' => 'application/json'],
            $raw,
        );

        $this->assertFalse(app(TripayGateway::class)->verifySignature($invalid));
    }

    public function test_tripay_parses_paid_callback(): void
    {
        $this->configureTripay();

        $request = HttpRequest::create('/webhook/pembayaran/tripay', 'POST', [
            'merchant_ref' => 'PG260101-ABCDEFGHIJ',
            'reference' => 'T0001-REF-1',
            'status' => 'PAID',
            'amount' => 250000,
            'paid_at' => now()->getTimestamp(),
            'fee_merchant' => 1000,
        ]);

        $result = app(TripayGateway::class)->parseWebhook($request);

        $this->assertSame(PaymentIntentStatus::Paid, $result->status);
        $this->assertSame('PG260101-ABCDEFGHIJ', $result->reference);
        $this->assertSame(250000.0, $result->amount);
        $this->assertSame('T0001-REF-1', $result->providerReference);
    }

    public function test_midtrans_charge_maps_virtual_account(): void
    {
        $this->configureMidtrans();

        Http::fake([
            'midtrans.test/*' => Http::response([
                'status_code' => '201',
                'transaction_id' => 'mid-trans-1',
                'order_id' => 'PG260101-ABCDEFGHIJ',
                'va_numbers' => [['bank' => 'bri', 'va_number' => '2621500000000001']],
                'expiry_time' => now()->addDay()->format('Y-m-d H:i:s'),
            ]),
        ]);

        $charge = app(MidtransGateway::class)->createCharge($this->intent(GatewayProvider::Midtrans, 'bri_va'));

        $this->assertSame('2621500000000001', $charge->payCode);
        $this->assertSame('mid-trans-1', $charge->providerReference);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === 'https://midtrans.test/v2/charge'
                && $body['payment_type'] === 'bank_transfer'
                && $body['bank_transfer']['bank'] === 'bri'
                && $body['transaction_details']['gross_amount'] === 250000;
        });
    }

    public function test_midtrans_charge_maps_qris(): void
    {
        $this->configureMidtrans();

        Http::fake([
            'midtrans.test/*' => Http::response([
                'status_code' => '201',
                'transaction_id' => 'mid-trans-2',
                'qr_string' => 'QRIS-PAYLOAD',
                'actions' => [
                    ['name' => 'generate-qr-code', 'url' => 'https://midtrans.test/qr/2'],
                ],
            ]),
        ]);

        $charge = app(MidtransGateway::class)->createCharge($this->intent(GatewayProvider::Midtrans, 'qris'));

        $this->assertSame('QRIS-PAYLOAD', $charge->qrString);
        $this->assertSame('https://midtrans.test/qr/2', $charge->qrUrl);
    }

    public function test_midtrans_rejects_unsupported_channel(): void
    {
        $this->configureMidtrans();

        $this->expectException(GatewayException::class);

        app(MidtransGateway::class)->createCharge($this->intent(GatewayProvider::Midtrans, 'alfamart'));
    }

    public function test_midtrans_verifies_notification_signature(): void
    {
        $this->configureMidtrans();

        $payload = [
            'order_id' => 'PG260101-ABCDEFGHIJ',
            'status_code' => '200',
            'gross_amount' => '250000.00',
            'transaction_status' => 'settlement',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'midtrans-server-key',
        );

        $this->assertTrue(app(MidtransGateway::class)->verifySignature(
            HttpRequest::create('/webhook/pembayaran/midtrans', 'POST', $payload),
        ));

        $payload['signature_key'] = 'palsu';

        $this->assertFalse(app(MidtransGateway::class)->verifySignature(
            HttpRequest::create('/webhook/pembayaran/midtrans', 'POST', $payload),
        ));
    }

    public function test_midtrans_parses_settlement_notification(): void
    {
        $this->configureMidtrans();

        $result = app(MidtransGateway::class)->parseWebhook(HttpRequest::create(
            '/webhook/pembayaran/midtrans',
            'POST',
            [
                'order_id' => 'PG260101-ABCDEFGHIJ',
                'transaction_id' => 'mid-trans-1',
                'transaction_status' => 'settlement',
                'gross_amount' => '250000.00',
                'settlement_time' => now()->toDateTimeString(),
            ],
        ));

        $this->assertSame(PaymentIntentStatus::Paid, $result->status);
        $this->assertSame(250000.0, $result->amount);
        $this->assertSame('mid-trans-1', $result->providerReference);
    }

    public function test_midtrans_maps_expire_and_cancel(): void
    {
        $this->configureMidtrans();

        $expire = app(MidtransGateway::class)->parseWebhook(HttpRequest::create(
            '/webhook/pembayaran/midtrans',
            'POST',
            ['order_id' => 'PG260101-ABCDEFGHIJ', 'transaction_status' => 'expire'],
        ));

        $this->assertSame(PaymentIntentStatus::Expired, $expire->status);

        $cancel = app(MidtransGateway::class)->parseWebhook(HttpRequest::create(
            '/webhook/pembayaran/midtrans',
            'POST',
            ['order_id' => 'PG260101-ABCDEFGHIJ', 'transaction_status' => 'cancel'],
        ));

        $this->assertSame(PaymentIntentStatus::Cancelled, $cancel->status);
    }
}
