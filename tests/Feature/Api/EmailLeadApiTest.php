<?php

use App\Enums\LeadType;
use App\Models\EmailLead;
use App\Models\License;
use App\Models\User;
use Database\Factories\LicenseFactory;

function leadPush(License $license, array $leads, array $overrides = []): array
{
    return ['product' => $license->product->slug, 'username' => $license->username, 'password' => LicenseFactory::PASSWORD, 'leads' => $leads, ...$overrides];
}

test('stores pushed leads tagged with the account, its customer, the source and the time', function () {
    $this->travelTo('2026-03-01 10:30:00');
    $customer = User::factory()->create();
    $license = License::factory()->for($customer)->create(['username' => 'bot-one']);

    $this->postJson(route('api.v1.leads.email.store'), leadPush($license, [
        ['number' => '+917001', 'password' => 'pw-1', 'type' => 'bell', 'range' => '7000-7999'],
        ['number' => '+917002', 'type' => 'Tap Code'],
    ]))->assertOk()->assertExactJson(['valid' => true, 'received' => 2, 'created' => 2, 'duplicates' => 0, 'invalid' => 0, 'errors' => []]);

    $this->assertDatabaseHas('email_leads', [
        'number' => '+917001', 'type' => 'bell', 'number_range' => '7000-7999',
        'license_id' => $license->id, 'user_id' => $customer->id, 'username' => 'bot-one', 'source' => 'api', 'created_at' => '2026-03-01 10:30:00',
    ]);
    $this->assertDatabaseHas('email_leads', ['number' => '+917002', 'type' => 'tap_code', 'number_range' => '', 'username' => 'bot-one']);
    expect(EmailLead::firstWhere('number', '+917001')->password)->toBe('pw-1');
});

test('reports duplicate and invalid rows without failing the batch', function () {
    $license = License::factory()->create();
    EmailLead::factory()->ofType(LeadType::Otp)->create(['number' => '+917001']);

    $this->postJson(route('api.v1.leads.email.store'), leadPush($license, [
        ['number' => '+917001', 'type' => 'otp'],
        ['number' => '+917002', 'type' => 'otp'],
        ['number' => '+917002', 'type' => 'otp'],
        ['number' => '', 'type' => 'otp'],
        ['number' => '+917003', 'type' => 'nonsense'],
    ]))->assertOk()->assertJson([
        'received' => 5, 'created' => 1, 'duplicates' => 2, 'invalid' => 2,
        'errors' => [
            ['row' => 4, 'message' => 'The number is missing.'],
            ['row' => 5, 'message' => 'The type "nonsense" is not valid (use bell, insert, otp, selection or tap_code).'],
        ],
    ]);

    expect(EmailLead::count())->toBe(2);
});

test('returns 401 and stores nothing for wrong credentials', function (array $override) {
    $license = License::factory()->create();

    $this->postJson(route('api.v1.leads.email.store'), leadPush($license, [['number' => '+917001', 'type' => 'bell']], $override))
        ->assertUnauthorized()->assertExactJson(['valid' => false, 'reason' => 'invalid_credentials']);

    expect(EmailLead::count())->toBe(0);
})->with([
    'wrong password' => [['password' => 'not-the-password']],
    'unknown username' => [['username' => 'nobody']],
]);

test('returns 403 and stores nothing for a revoked or expired account', function (string $state, string $reason) {
    $license = License::factory()->{$state}()->create();

    $this->postJson(route('api.v1.leads.email.store'), leadPush($license, [['number' => '+917001', 'type' => 'bell']]))
        ->assertForbidden()->assertExactJson(['valid' => false, 'reason' => $reason]);

    expect(EmailLead::count())->toBe(0);
})->with([['revoked', 'revoked'], ['expired', 'expired']]);

test('returns 422 when the leads are missing or exceed the limit', function () {
    config(['leads.api_max_per_request' => 2]);
    $license = License::factory()->create();
    $lead = ['number' => '+917001', 'type' => 'bell'];

    $this->postJson(route('api.v1.leads.email.store'), leadPush($license, []))->assertUnprocessable()->assertJsonValidationErrors('leads');
    $this->postJson(route('api.v1.leads.email.store'), leadPush($license, [$lead, $lead, $lead]))->assertUnprocessable()->assertJsonValidationErrors('leads');
    $this->postJson(route('api.v1.leads.email.store'), ['leads' => [$lead]])->assertUnprocessable()->assertJsonValidationErrors(['product', 'username', 'password']);

    expect(EmailLead::count())->toBe(0);
});

test('ignores tag fields a bot tries to set in the payload', function () {
    $other = License::factory()->create(['username' => 'victim']);
    $license = License::factory()->create(['username' => 'honest-bot']);

    $this->postJson(route('api.v1.leads.email.store'), leadPush($license, [[
        'number' => '+917001', 'type' => 'bell', 'license_id' => $other->id, 'user_id' => $other->user_id, 'username' => 'victim', 'source' => 'manual',
    ]]))->assertOk();

    $this->assertDatabaseHas('email_leads', ['number' => '+917001', 'license_id' => $license->id, 'username' => 'honest-bot', 'source' => 'api']);
});
