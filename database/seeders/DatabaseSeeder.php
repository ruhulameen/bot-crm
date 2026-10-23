<?php

namespace Database\Seeders;

use App\Enums\LeadType;
use App\Enums\UserRole;
use App\Models\EmailLead;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->role(UserRole::Admin)->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $customer = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'user@example.com',
        ]);

        $oreo = Product::create(['name' => 'Oreo Bot', 'slug' => 'oreo-bot', 'description' => 'Automation bot']);
        $tools = Product::create(['name' => 'Number Tools', 'slug' => 'number-tools']);

        License::factory()->for($oreo)->for($customer)->online()->create(['username' => 'oreo-demo-online', 'password' => 'password'])->syncTagNames('vip');
        License::factory()->for($oreo)->for($customer)->offline()->expiringSoon(3)->create(['username' => 'oreo-demo-offline', 'password' => 'password']);
        License::factory()->for($tools)->for($customer)->create(['username' => 'tools-demo-new', 'password' => 'password'])->syncTagNames('trial, reseller');
        License::factory()->for($tools)->for($customer)->expired()->create(['username' => 'tools-demo-expired', 'password' => 'password']);
        License::factory()->for($tools)->for($customer)->revoked()->create(['username' => 'tools-demo-revoked', 'password' => 'password']);

        $botAccount = License::firstWhere('username', 'oreo-demo-online');
        foreach (LeadType::cases() as $index => $type) {
            EmailLead::factory()->count(2)->ofType($type)->inRange('5550100'.$index.'00-5550100'.$index.'99')->suppliedBy($botAccount, 'import')->create();
        }
    }
}
