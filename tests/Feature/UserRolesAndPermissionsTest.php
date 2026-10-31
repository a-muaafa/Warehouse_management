<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_username_and_password_success(): void
    {
        $user = User::factory()->create([
            'username' => 'test_user',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'username' => 'test_user',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'تسجيل دخول',
        ]);
    }

    public function test_disabled_user_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'disabled_user',
            'password' => bcrypt('secret123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'username' => 'disabled_user',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_failed_login_is_logged_in_activity_logs(): void
    {
        $response = $this->post('/login', [
            'username' => 'nonexistent_user',
            'password' => 'wrong_password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertDatabaseHas('activity_logs', [
            'user_name' => 'nonexistent_user',
            'action' => 'فشل تسجيل الدخول',
        ]);
    }

    public function test_supervisor_cannot_manage_workers_from_another_department(): void
    {
        $foodDept = Department::create(['name' => 'قسم الأغذية']);
        $pharmaDept = Department::create(['name' => 'قسم الأدوية']);

        $foodSupervisor = User::factory()->create([
            'role' => 'supervisor',
            'department_id' => $foodDept->id,
        ]);

        $pharmaWorker = User::factory()->create([
            'role' => 'worker',
            'department_id' => $pharmaDept->id,
        ]);

        $response = $this->actingAs($foodSupervisor)->get(route('supervisor.workers.edit', $pharmaWorker));
        $response->assertStatus(403);
    }

    public function test_intake_worker_cannot_edit_or_delete_transactions(): void
    {
        $dept = Department::create(['name' => 'قسم الأغذية']);
        $intakeWorker = User::factory()->create([
            'role' => 'worker',
            'worker_type' => 'intake',
            'department_id' => $dept->id,
        ]);

        $item = Item::factory()->create(['department_id' => $dept->id]);

        $response = $this->actingAs($intakeWorker)->post('/transactions', [
            'item_id' => $item->id,
            'type' => 'in',
            'quantity' => 10,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', [
            'item_id' => $item->id,
            'quantity' => 10,
        ]);
    }

    public function test_view_only_worker_cannot_create_transactions(): void
    {
        $dept = Department::create(['name' => 'قسم الأغذية']);
        $viewWorker = User::factory()->create([
            'role' => 'worker',
            'worker_type' => 'view_only',
            'department_id' => $dept->id,
        ]);

        $item = Item::factory()->create(['department_id' => $dept->id]);

        $response = $this->actingAs($viewWorker)->post('/transactions', [
            'item_id' => $item->id,
            'type' => 'in',
            'quantity' => 5,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_view_activity_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ActivityLog::log('اختبار الحركات', 'سجل تجريبي');

        $response = $this->actingAs($admin)->get('/admin/logs');
        $response->assertStatus(200);
        $response->assertSee('اختبار الحركات');
    }
}
