<?php

use App\Models\SmsSuppression;
use App\Services\SmsSuppressionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

function signTwilioInboundWebhook(string $url, array $params, string $token): string
{
    ksort($params, SORT_STRING);

    $payload = $url;

    foreach ($params as $key => $value) {
        $payload .= $key.$value;
    }

    return base64_encode(hash_hmac('sha1', $payload, $token, true));
}

function postSignedTwilioInbound(Tests\TestCase $test, array $params)
{
    $signature = signTwilioInboundWebhook(
        config('services.twilio.inbound_webhook_url'),
        $params,
        config('services.twilio.auth_token'),
    );

    return $test->withHeader('X-Twilio-Signature', $signature)
        ->post('/api/twilio/inbound', $params);
}

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'services.twilio.auth_token' => 'test-auth-token',
        'services.twilio.inbound_webhook_url' => 'https://cobrocartera.abogadosencolombiasas.com/api/twilio/inbound',
    ]);

    DB::purge('sqlite');

    Schema::connection('sqlite')->create('sms_suppressions', function (Blueprint $table) {
        $table->id();
        $table->char('phone_hash', 64)->unique();
        $table->text('phone_e164');
        $table->string('source', 64);
        $table->string('reason', 64);
        $table->timestamp('first_suppressed_at');
        $table->timestamp('last_suppressed_at');
        $table->timestamps();
    });
});

test('stores a signed SALIR opt-out after normalizing accents whitespace case and E164 formatting', function () {
    $params = [
        'AccountSid' => 'AC'.str_repeat('b', 32),
        'Body' => "  sA\u{0301} LiR \n",
        'From' => ' +57 (301) 680-3926 ',
        'MessageSid' => 'SM'.str_repeat('a', 32),
        'To' => '+14709284068',
    ];

    $response = postSignedTwilioInbound($this, $params);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

    expect($response->getContent())
        ->toContain('<Response>')
        ->not->toContain('<Message');

    $this->assertDatabaseHas('sms_suppressions', [
        'source' => 'twilio_inbound_keyword',
        'reason' => 'keyword_salir',
    ]);

    expect(app(SmsSuppressionService::class)->isSuppressed('+57 301 680 3926'))
        ->toBeTrue();

    $stored = DB::table('sms_suppressions')->first();

    expect($stored->phone_hash)->toHaveLength(64);
    expect($stored->phone_e164)->not->toContain('+573016803926');
    expect(SmsSuppression::query()->firstOrFail()->phone_e164)
        ->toBe('+573016803926');
});

test('stores Advanced Opt-Out STOP even when the message body is not a local keyword', function () {
    $params = [
        'Body' => 'unsubscribe',
        'From' => '+573016803926',
        'MessageSid' => 'SM'.str_repeat('c', 32),
        'OptOutType' => ' stop ',
        'To' => '+14709284068',
    ];

    postSignedTwilioInbound($this, $params)->assertOk();

    $this->assertDatabaseHas('sms_suppressions', [
        'source' => 'twilio_advanced_opt_out',
        'reason' => 'opt_out_type_stop',
    ]);
});

test('rejects an inbound webhook with an invalid Twilio signature', function () {
    $params = [
        'Body' => 'STOP',
        'From' => '+573016803926',
        'MessageSid' => 'SM'.str_repeat('d', 32),
    ];

    $this->withHeader('X-Twilio-Signature', 'invalid-signature')
        ->post('/api/twilio/inbound', $params)
        ->assertForbidden();

    $this->assertDatabaseCount('sms_suppressions', 0);
});

test('records repeated STOP deliveries idempotently and preserves the first timestamp', function () {
    $this->travelTo('2026-08-20 14:00:00');

    $firstParams = [
        'Body' => ' STOP ',
        'From' => '+573016803926',
        'MessageSid' => 'SM'.str_repeat('e', 32),
    ];

    postSignedTwilioInbound($this, $firstParams)->assertOk();

    $firstSuppressedAt = DB::table('sms_suppressions')->value('first_suppressed_at');

    $this->travelTo('2026-08-20 14:05:00');

    $secondParams = [
        'Body' => "\tstO\u{0301}p\n",
        'From' => '+57 301 680 3926',
        'MessageSid' => 'SM'.str_repeat('f', 32),
    ];

    postSignedTwilioInbound($this, $secondParams)->assertOk();

    $this->assertDatabaseCount('sms_suppressions', 1);
    expect(DB::table('sms_suppressions')->value('first_suppressed_at'))
        ->toBe($firstSuppressedAt);
    expect(DB::table('sms_suppressions')->value('last_suppressed_at'))
        ->not->toBe($firstSuppressedAt);
});

test('returns empty TwiML without suppressing a non-keyword message', function () {
    $params = [
        'Body' => 'Necesito informacion',
        'From' => '+573016803926',
        'MessageSid' => 'SM'.str_repeat('1', 32),
    ];

    $response = postSignedTwilioInbound($this, $params);

    $response->assertOk();
    expect($response->getContent())
        ->toContain('<Response>')
        ->not->toContain('<Message');
    $this->assertDatabaseCount('sms_suppressions', 0);
});

test('rejects a malformed sender number instead of inferring an E164 identity', function () {
    $params = [
        'Body' => 'STOP',
        'From' => '3016803926',
        'MessageSid' => 'SM'.str_repeat('2', 32),
    ];

    postSignedTwilioInbound($this, $params)->assertUnprocessable();

    $this->assertDatabaseCount('sms_suppressions', 0);
});
