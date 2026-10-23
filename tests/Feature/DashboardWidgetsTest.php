<?php

use App\Enums\UserRole;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('admin dashboard lists expiring licenses and offline bots', function () {
    $soon = License::factory()->expiringSoon(2)->create();
    License::factory()->offline()->create(['username' => 'quiet-bot']);

    $this->actingAs(User::factory()->role(UserRole::Admin)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($soon->username)
        ->assertSee('quiet-bot');
});

test('users do not see admin widgets', function () {
    $soon = License::factory()->expiringSoon(2)->create();

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertDontSee($soon->username);
});

test('the dashboard runs the same number of queries however many bots are offline', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $countQueries = function () use ($admin) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        return count(DB::getQueryLog());
    };
    License::factory()->count(2)->offline()->create();
    $withTwo = $countQueries();

    License::factory()->count(6)->offline()->create();

    expect($countQueries())->toBe($withTwo);
});
