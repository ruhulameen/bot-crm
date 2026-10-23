<?php

use App\Enums\UserRole;
use App\Models\License;
use App\Models\Product;
use App\Models\User;

test('every admin page renders for an admin', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $license = License::factory()->create();
    $product = Product::factory()->create();

    collect([
        route('admin.users.create'),
        route('admin.users.edit', $admin),
        route('admin.products.create'),
        route('admin.products.edit', $product),
        route('admin.licenses.create'),
        route('admin.licenses.edit', $license),
        route('admin.licenses.show', $license),
    ])->each(fn (string $url) => $this->actingAs($admin)->get($url)->assertOk());
});
