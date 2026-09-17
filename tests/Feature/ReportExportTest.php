<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_access_reports_page(): void
    {
        $dept = Department::create(['name' => 'قسم الأغذية']);
        $supervisor = User::factory()->create([
            'role' => 'supervisor',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($supervisor)->get(route('supervisor.reports.index'));
        $response->assertStatus(200);
        $response->assertSee(__('Export to Excel'));
    }

    public function test_supervisor_can_export_reports_to_csv_excel(): void
    {
        $dept = Department::create(['name' => 'قسم الأغذية']);
        $supervisor = User::factory()->create([
            'role' => 'supervisor',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $item = Item::create([
            'department_id' => $dept->id,
            'name' => 'تمر مدجول فاخر',
            'barcode' => 'TEST999888',
            'quantity' => 100,
            'unit' => 'كجم',
        ]);

        Transaction::create([
            'item_id' => $item->id,
            'user_id' => $supervisor->id,
            'type' => 'in',
            'quantity' => 50,
            'unit_price' => 20.00,
            'total_amount' => 1000.00,
            'movement_reason' => 'purchase',
            'notes' => 'تصدير اختبار',
        ]);

        $response = $this->actingAs($supervisor)->get(route('supervisor.reports.export'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="inventory_report_', $response->headers->get('content-disposition'));

        // Capture streamed content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Check UTF-8 BOM prefix \xEF\xBB\xBF and item data
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('تمر مدجول فاخر', $content);
    }
}
