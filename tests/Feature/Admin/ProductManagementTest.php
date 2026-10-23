<?php

use App\Enums\UserRole;
use App\Models\License;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

test('users cannot access products', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.products.index'))->assertForbidden();
});

test('products can be listed and filtered', function () {
    Product::factory()->create(['name' => 'Alpha Bot']);
    Product::factory()->inactive()->create(['name' => 'Beta Bot']);

    $this->actingAs($this->admin)->get(route('admin.products.index', ['active' => '0']))
        ->assertSee('Beta Bot')->assertDontSee('Alpha Bot');
    $this->actingAs($this->admin)->get(route('admin.products.index', ['q' => 'alpha']))
        ->assertSee('Alpha Bot')->assertDontSee('Beta Bot');
});

test('product slug is generated from the name when blank', function () {
    $this->actingAs($this->admin)->post(route('admin.products.store'), ['name' => 'Oreo Super Bot', 'is_active' => '1'])
        ->assertRedirect(route('admin.products.index'));

    expect(Product::firstWhere('slug', 'oreo-super-bot'))->not->toBeNull();
});

test('product slug must be unique', function () {
    Product::factory()->create(['slug' => 'taken']);

    $this->actingAs($this->admin)->post(route('admin.products.store'), ['name' => 'X', 'slug' => 'taken'])
        ->assertSessionHasErrors('slug');
});

test('products can be updated', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.products.update', $product), ['name' => 'Renamed', 'slug' => $product->slug])
        ->assertRedirect(route('admin.products.index'));

    expect($product->refresh()->name)->toBe('Renamed')->and($product->is_active)->toBeFalse();
});

test('a product without licenses can be deleted but one with licenses cannot', function () {
    $free = Product::factory()->create();
    $used = License::factory()->create()->product;

    $this->actingAs($this->admin)->delete(route('admin.products.destroy', $free))->assertRedirect(route('admin.products.index'));
    $this->assertModelMissing($free);

    $this->actingAs($this->admin)->delete(route('admin.products.destroy', $used))->assertSessionHas('error');
    $this->assertModelExists($used);
});
