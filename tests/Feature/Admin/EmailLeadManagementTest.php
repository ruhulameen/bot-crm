<?php

use App\Enums\LeadType;
use App\Enums\UserRole;
use App\Models\EmailLead;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function leadAdmin(): User
{
    return User::factory()->role(UserRole::Admin)->create();
}

test('redirects guests to login', function () {
    $this->get(route('admin.leads.email.index'))->assertRedirect(route('login'));
});

test('forbids regular users from every lead page and action', function (string $method, string $route) {
    $lead = EmailLead::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->call($method, route($route, ['lead' => $lead->id]))->assertForbidden();

    $this->assertModelExists($lead);
})->with([
    'list' => ['GET', 'admin.leads.email.index'],
    'add form' => ['GET', 'admin.leads.email.create'],
    'edit form' => ['GET', 'admin.leads.email.edit'],
    'import form' => ['GET', 'admin.leads.email.import.create'],
    'export' => ['GET', 'admin.leads.email.export'],
    'example file' => ['GET', 'admin.leads.email.example'],
    'delete' => ['DELETE', 'admin.leads.email.destroy'],
    'bulk delete' => ['DELETE', 'admin.leads.email.destroy-many'],
]);

test('shows the Leads menu group with an Email link to admins', function () {
    $this->actingAs(leadAdmin())->get(route('admin.leads.email.index'))
        ->assertOk()->assertSee('Leads')->assertSee('href="'.route('admin.leads.email.index').'"', false);
});

test('lists leads newest first with a count per type', function () {
    EmailLead::factory()->ofType(LeadType::Bell)->create(['number' => '+917001', 'created_at' => '2026-01-01 10:00:00']);
    EmailLead::factory()->ofType(LeadType::Bell)->create(['number' => '+917002', 'created_at' => '2026-01-03 10:00:00']);
    EmailLead::factory()->ofType(LeadType::Otp)->create(['number' => '+917003', 'created_at' => '2026-01-02 10:00:00']);

    $this->actingAs(leadAdmin())->get(route('admin.leads.email.index'))
        ->assertOk()
        ->assertSeeInOrder(['+917003', '+917002', '+917001'], false)
        ->assertSee('Bell 2')->assertSee('OTP 1')->assertSee('All 3');
});

test('shows the created date and how long ago each lead was added in separate columns', function () {
    $this->travelTo('2026-03-01 10:30:00');
    EmailLead::factory()->create(['number' => '+917001', 'created_at' => '2026-03-01 10:29:00']);
    EmailLead::factory()->create(['number' => '+917002', 'created_at' => '2026-02-26 08:15:00']);

    $this->actingAs(leadAdmin())->get(route('admin.leads.email.index'))->assertOk()
        ->assertSeeInOrder(['<th>Date</th>', '<th>Added</th>'], false)
        ->assertSeeInOrder(['+917001', '01 Mar 2026', '1 minute ago'], false)
        ->assertSeeInOrder(['+917002', '26 Feb 2026', '3 days ago'], false)
        ->assertDontSee('10:29</div>', false);
});

test('filters leads', function (array $filters, array $visible) {
    $account = License::factory()->create(['username' => 'alpha']);
    EmailLead::factory()->ofType(LeadType::Bell)->inRange('R1')->suppliedBy($account)->create(['number' => '+917001', 'created_at' => '2026-01-05 12:00:00']);
    EmailLead::factory()->ofType(LeadType::Otp)->inRange('R2')->create(['number' => '+917002', 'created_at' => '2026-02-05 12:00:00']);
    EmailLead::factory()->ofType(LeadType::Bell)->inRange('R2')->create(['number' => '+917003', 'created_at' => '2026-03-05 12:00:00']);

    $response = $this->actingAs(leadAdmin())->get(route('admin.leads.email.index', $filters))->assertOk();

    foreach (['+917001', '+917002', '+917003'] as $number) {
        in_array($number, $visible) ? $response->assertSee($number) : $response->assertDontSee($number);
    }
})->with([
    'number search' => [['q' => '7001'], ['+917001']],
    'range search' => [['q' => 'R2'], ['+917002', '+917003']],
    'username search' => [['q' => 'alph'], ['+917001']],
    'type' => [['type' => 'otp'], ['+917002']],
    'range' => [['range' => 'R2'], ['+917002', '+917003']],
    'username' => [['username' => 'alpha'], ['+917001']],
    'from date' => [['from' => '2026-02-01'], ['+917002', '+917003']],
    'to date, inclusive' => [['to' => '2026-02-05'], ['+917001', '+917002']],
]);

test('filters leads by customer', function () {
    $customer = User::factory()->create();
    EmailLead::factory()->create(['number' => '+917001', 'user_id' => $customer->id]);
    EmailLead::factory()->create(['number' => '+917002']);

    $this->actingAs(leadAdmin())->get(route('admin.leads.email.index', ['user' => $customer->id]))
        ->assertOk()->assertSee('+917001')->assertDontSee('+917002');
});

test('escapes lead content on the list', function () {
    EmailLead::factory()->create([
        'number' => '+9170<b>01', 'password' => '<script>alert(1)</script>', 'number_range' => '"><img src=x>', 'username' => '<i>bot</i>',
    ]);

    $this->actingAs(leadAdmin())->get(route('admin.leads.email.index'))->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x>', false)
        ->assertDontSee('<i>bot</i>', false)
        ->assertDontSee('+9170<b>01', false);
});

test('adds a lead tagged with the chosen account and its customer, storing the password encrypted', function () {
    $customer = User::factory()->create();
    $license = License::factory()->for($customer)->create(['username' => 'bot-one']);

    $this->actingAs(leadAdmin())->post(route('admin.leads.email.store'), [
        'number' => ' +91 7001 ', 'password' => 'S3cret!', 'type' => 'selection', 'number_range' => 'R1', 'license_id' => $license->id,
    ])->assertRedirect(route('admin.leads.email.index'))->assertSessionHas('status', 'Lead added.');

    $lead = EmailLead::sole();
    expect($lead->number)->toBe('+917001')
        ->and($lead->type)->toBe(LeadType::Selection)
        ->and($lead->number_range)->toBe('R1')
        ->and($lead->password)->toBe('S3cret!')
        ->and($lead->license_id)->toBe($license->id)
        ->and($lead->user_id)->toBe($customer->id)
        ->and($lead->username)->toBe('bot-one')
        ->and($lead->source)->toBe('manual');
});

test('prefers an explicitly chosen customer over the account customer', function () {
    $chosen = User::factory()->create();
    $license = License::factory()->create();

    $this->actingAs(leadAdmin())->post(route('admin.leads.email.store'), [
        'number' => '+917001', 'type' => 'bell', 'license_id' => $license->id, 'user_id' => $chosen->id,
    ]);

    expect(EmailLead::sole()->user_id)->toBe($chosen->id);
});

test('ignores source and tag fields posted to the add form', function () {
    $this->actingAs(leadAdmin())->post(route('admin.leads.email.store'), [
        'number' => '+917001', 'type' => 'bell', 'source' => 'api', 'username' => 'forged',
    ]);

    $lead = EmailLead::sole();
    expect($lead->source)->toBe('manual')->and($lead->username)->toBeNull();
});

test('rejects an incomplete lead with a message per field', function () {
    $this->actingAs(leadAdmin())->post(route('admin.leads.email.store'), [])
        ->assertSessionHasErrors(['number' => 'The number field is required.', 'type' => 'The type field is required.']);

    expect(EmailLead::count())->toBe(0);
});

test('rejects a number that already exists with the same type and range', function () {
    EmailLead::factory()->ofType(LeadType::Bell)->inRange('R1')->create(['number' => '+917001']);

    $this->actingAs(leadAdmin())->post(route('admin.leads.email.store'), ['number' => '+917001', 'type' => 'bell', 'number_range' => 'R1'])
        ->assertSessionHasErrors(['number' => 'This number already exists with the same type and range.']);

    expect(EmailLead::count())->toBe(1);
});

test('accepts the same number under a different type or range', function (string $type, string $range) {
    EmailLead::factory()->ofType(LeadType::Bell)->inRange('R1')->create(['number' => '+917001']);

    $this->actingAs(leadAdmin())->post(route('admin.leads.email.store'), ['number' => '+917001', 'type' => $type, 'number_range' => $range])
        ->assertSessionHasNoErrors();

    expect(EmailLead::count())->toBe(2);
})->with(['other type' => ['otp', 'R1'], 'other range' => ['bell', 'R2']]);

test('updates a lead and re-tags it', function () {
    $lead = EmailLead::factory()->suppliedBy(License::factory()->create(['username' => 'old-bot']))->create(['number' => '+917001']);
    $newLicense = License::factory()->create(['username' => 'new-bot']);

    $this->actingAs(leadAdmin())->put(route('admin.leads.email.update', $lead), [
        'number' => '+917001', 'password' => 'changed', 'type' => 'otp', 'number_range' => 'R9', 'license_id' => $newLicense->id,
    ])->assertRedirect(route('admin.leads.email.index'));

    $lead->refresh();
    expect($lead->password)->toBe('changed')->and($lead->type)->toBe(LeadType::Otp)->and($lead->number_range)->toBe('R9')
        ->and($lead->username)->toBe('new-bot')->and($lead->license_id)->toBe($newLicense->id)->and($lead->user_id)->toBe($newLicense->user_id);
});

test('keeps its own number valid when updating, but rejects another lead\'s number', function () {
    $lead = EmailLead::factory()->ofType(LeadType::Bell)->create(['number' => '+917001']);
    EmailLead::factory()->ofType(LeadType::Bell)->create(['number' => '+917002']);

    $this->actingAs(leadAdmin())->put(route('admin.leads.email.update', $lead), ['number' => '+917001', 'type' => 'bell'])->assertSessionHasNoErrors();
    $this->actingAs(leadAdmin())->put(route('admin.leads.email.update', $lead), ['number' => '+917002', 'type' => 'bell'])->assertSessionHasErrors('number');
});

test('deletes a lead', function () {
    $lead = EmailLead::factory()->create();

    $this->actingAs(leadAdmin())->delete(route('admin.leads.email.destroy', $lead))->assertRedirect(route('admin.leads.email.index'));

    $this->assertModelMissing($lead);
});

test('deletes only the selected leads in bulk', function () {
    [$first, $second, $kept] = EmailLead::factory()->count(3)->create();

    $this->actingAs(leadAdmin())->delete(route('admin.leads.email.destroy-many'), ['ids' => [$first->id, $second->id]])
        ->assertRedirect()->assertSessionHas('status', '2 leads deleted.');

    $this->assertModelMissing($first);
    $this->assertModelMissing($second);
    $this->assertModelExists($kept);
});

test('refuses a bulk delete without a selection', function () {
    $lead = EmailLead::factory()->create();

    $this->actingAs(leadAdmin())->delete(route('admin.leads.email.destroy-many'), [])->assertSessionHasErrors('ids');

    $this->assertModelExists($lead);
});

test('keeps the username tag after the account is deleted', function () {
    $license = License::factory()->create(['username' => 'gone-bot']);
    $lead = EmailLead::factory()->suppliedBy($license)->create();

    $license->delete();

    $lead->refresh();
    expect($lead->license_id)->toBeNull()->and($lead->username)->toBe('gone-bot');
});

test('stores the password as a decryptable ciphertext, never as plain text', function () {
    $lead = EmailLead::factory()->create(['password' => 'plain-secret']);

    $stored = DB::table('email_leads')->where('id', $lead->id)->value('password');

    expect($stored)->not->toBe('plain-secret')->and($lead->refresh()->password)->toBe('plain-secret');
    expect(Hash::isHashed($stored))->toBeFalse();
});
