<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manual_page_renders_for_staff(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($user)
            ->get('/admin/admin-manual')
            ->assertOk()
            ->assertSee('Daily Routine');
    }

    public function test_admin_manual_requires_authentication(): void
    {
        $this->get('/admin/admin-manual')->assertRedirect('/admin/login');
    }
}
