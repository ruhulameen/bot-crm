<?php

use App\Enums\UserRole;
use App\Models\License;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

test('tags are created, normalized and attached when issuing a license', function () {
    $this->actingAs($this->admin)->post(route('admin.licenses.store'), [
        'product_id' => Product::factory()->create()->id, 'days' => 30, 'username' => 'tagged-user', 'tags' => ' VIP, reseller ,vip,, ',
    ])->assertRedirect();

    expect(License::firstOrFail()->tags->pluck('name')->sort()->values()->all())->toBe(['reseller', 'vip'])
        ->and(Tag::count())->toBe(2);
});

test('existing tags are reused and updating replaces the set', function () {
    $license = License::factory()->create();
    $license->syncTagNames('vip, trial');

    $this->actingAs($this->admin)->put(route('admin.licenses.update', $license), ['username' => $license->username, 'tags' => 'vip, partner'])->assertRedirect();

    expect($license->refresh()->tags->pluck('name')->sort()->values()->all())->toBe(['partner', 'vip'])
        ->and(Tag::where('name', 'vip')->count())->toBe(1);

    $this->actingAs($this->admin)->put(route('admin.licenses.update', $license), ['username' => $license->username, 'tags' => ''])->assertRedirect();
    expect($license->refresh()->tags)->toBeEmpty();
});

test('licenses can be filtered and searched by tag', function () {
    $tagged = License::factory()->create();
    $tagged->syncTagNames('zq-special');
    $plain = License::factory()->create();

    $tag = Tag::firstWhere('name', 'zq-special');

    $this->actingAs($this->admin)->get(route('admin.licenses.index', ['tag' => $tag->id]))
        ->assertSee($tagged->username)->assertDontSee($plain->username);
    $this->actingAs($this->admin)->get(route('admin.licenses.index', ['q' => 'zq-spec']))
        ->assertSee($tagged->username)->assertDontSee($plain->username);
});

test('tags show on the list, detail and form pages', function () {
    $license = License::factory()->create();
    $license->syncTagNames('vip');

    $this->actingAs($this->admin)->get(route('admin.licenses.index'))->assertSee('vip');
    $this->actingAs($this->admin)->get(route('admin.licenses.show', $license))->assertSee('vip');
    $this->actingAs($this->admin)->get(route('admin.licenses.edit', $license))->assertSee('vip');
});

test('deleting a license detaches its tags but keeps the tag', function () {
    $license = License::factory()->create();
    $license->syncTagNames('vip');

    $license->delete();

    expect(Tag::count())->toBe(1)->and(Tag::first()->licenses)->toBeEmpty();
});
