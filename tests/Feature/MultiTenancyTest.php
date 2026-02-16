<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    public function test_owner_can_see_all_shops(): void
    {
        $owner = \App\Models\User::factory()->create(['role' => 'owner', 'shop_id' => null]);
        $this->actingAs($owner);

        \App\Models\Shop::create(['name' => 'Shop 1', 'address' => 'addr1', 'phone' => '123']);
        \App\Models\Shop::create(['name' => 'Shop 2', 'address' => 'addr2', 'phone' => '456']);

        $this->assertCount(2, \App\Models\Shop::all());
    }

    public function test_admin_can_only_see_own_shop(): void
    {
        $shop1 = \App\Models\Shop::create(['name' => 'Shop 1', 'address' => 'addr1', 'phone' => '123']);
        $shop2 = \App\Models\Shop::create(['name' => 'Shop 2', 'address' => 'addr2', 'phone' => '456']);

        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'shop_id' => $shop1->id]);
        $this->actingAs($admin);

        $shops = \App\Models\Shop::all();
        $this->assertCount(1, $shops);
        $this->assertEquals($shop1->id, $shops->first()->id);
    }

    public function test_owner_can_see_all_users(): void
    {
        $owner = \App\Models\User::factory()->create(['role' => 'owner', 'shop_id' => null]);
        $this->actingAs($owner);

        $shop1 = \App\Models\Shop::create(['name' => 'Shop 1', 'address' => 'addr1', 'phone' => '123']);
        \App\Models\User::factory()->create(['role' => 'admin', 'shop_id' => $shop1->id]);

        $shop2 = \App\Models\Shop::create(['name' => 'Shop 2', 'address' => 'addr2', 'phone' => '456']);
        \App\Models\User::factory()->create(['role' => 'admin', 'shop_id' => $shop2->id]);

        // Owner + Admin1 + Admin2 = 3 users
        $this->assertCount(3, \App\Models\User::all());
    }

    public function test_admin_can_only_see_shop_users(): void
    {
        $shop1 = \App\Models\Shop::create(['name' => 'Shop 1', 'address' => 'addr1', 'phone' => '123']);
        $admin1 = \App\Models\User::factory()->create(['role' => 'admin', 'shop_id' => $shop1->id]);
        $designer1 = \App\Models\User::factory()->create(['role' => 'designer', 'shop_id' => $shop1->id]);

        $shop2 = \App\Models\Shop::create(['name' => 'Shop 2', 'address' => 'addr2', 'phone' => '456']);
        $admin2 = \App\Models\User::factory()->create(['role' => 'admin', 'shop_id' => $shop2->id]);

        $this->actingAs($admin1);

        $users = \App\Models\User::all();
        // Admin1 should see Admin1 + Designer1 = 2 users
        $this->assertCount(2, $users);
        $this->assertTrue($users->contains($admin1));
        $this->assertTrue($users->contains($designer1));
        $this->assertFalse($users->contains($admin2));
    }
}
