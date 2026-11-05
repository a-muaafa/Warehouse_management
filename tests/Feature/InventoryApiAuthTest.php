<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Authentication & Scope Tests
 *
 * Verifies that:
 * - Unauthenticated requests (no token) are rejected with 401
 * - Invalid tokens are rejected with 401
 * - Read-scoped tokens cannot reach write endpoints (403)
 * - Write-scoped tokens can reach both read and write endpoints
 * - per_page is capped and category_id / low_stock are validated
 */
class InventoryApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $readToken = 'test-read-token-abc123';

    private string $writeToken = 'test-write-token-xyz789';

    protected function setUp(): void
    {
        parent::setUp();

        // Override API tokens config for tests
        config(['api.tokens' => [
            ['token' => $this->readToken,  'scope' => 'read',  'name' => 'Test Read Client'],
            ['token' => $this->writeToken, 'scope' => 'write', 'name' => 'Test ERP Client'],
        ]]);
    }

    // -----------------------------------------------------------------------
    // 401 — Unauthenticated
    // -----------------------------------------------------------------------

    public function test_stock_balances_rejects_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/stock-balances');
        $response->assertStatus(401);
    }

    public function test_barcode_lookup_rejects_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/items/barcode/SOME-CODE');
        $response->assertStatus(401);
    }

    public function test_valuation_summary_rejects_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/valuation-summary');
        $response->assertStatus(401);
    }

    public function test_external_sync_rejects_unauthenticated_request(): void
    {
        $response = $this->postJson('/api/v1/transactions/external-sync', []);
        $response->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $response = $this->withToken('totally-invalid-token')->getJson('/api/v1/stock-balances');
        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // 403 — Read token cannot write
    // -----------------------------------------------------------------------

    public function test_read_token_cannot_reach_external_sync(): void
    {
        $dept = Department::create(['name' => 'Test Dept']);
        $item = Item::factory()->create(['barcode' => 'TEST-BC-001', 'department_id' => $dept->id]);

        $response = $this->withToken($this->readToken)->postJson('/api/v1/transactions/external-sync', [
            'barcode' => 'TEST-BC-001',
            'type' => 'in',
            'quantity' => 10,
            'movement_reason' => 'purchase',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('transactions', ['item_id' => $item->id]);
    }

    // -----------------------------------------------------------------------
    // 200 — Valid tokens reach correct endpoints
    // -----------------------------------------------------------------------

    public function test_read_token_can_access_stock_balances(): void
    {
        $response = $this->withToken($this->readToken)->getJson('/api/v1/stock-balances');
        $response->assertStatus(200)->assertJsonPath('status', 'success');
    }

    public function test_write_token_can_also_access_read_endpoints(): void
    {
        $response = $this->withToken($this->writeToken)->getJson('/api/v1/valuation-summary');
        $response->assertStatus(200)->assertJsonPath('status', 'success');
    }

    public function test_write_token_can_create_inbound_transaction(): void
    {
        $dept = Department::create(['name' => 'Test Dept']);
        $item = Item::factory()->create(['barcode' => 'TEST-IN-001', 'department_id' => $dept->id, 'quantity' => 0]);

        $response = $this->withToken($this->writeToken)->postJson('/api/v1/transactions/external-sync', [
            'barcode' => 'TEST-IN-001',
            'type' => 'in',
            'quantity' => 5,
            'movement_reason' => 'purchase',
        ]);

        $response->assertStatus(200)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('transactions', ['item_id' => $item->id, 'type' => 'in', 'quantity' => 5]);
    }

    // -----------------------------------------------------------------------
    // External sync — no user_id=1 fallback
    // -----------------------------------------------------------------------

    public function test_external_sync_does_not_assign_user_id_1(): void
    {
        $dept = Department::create(['name' => 'Test Dept']);
        Item::factory()->create(['barcode' => 'TEST-NO-USER', 'department_id' => $dept->id, 'quantity' => 0]);

        $this->withToken($this->writeToken)->postJson('/api/v1/transactions/external-sync', [
            'barcode' => 'TEST-NO-USER',
            'type' => 'in',
            'quantity' => 3,
            'movement_reason' => 'erp_sync',
        ]);

        // user_id should be null — never 1 — for external API transactions
        $this->assertDatabaseMissing('transactions', ['user_id' => 1]);
        $this->assertDatabaseHas('transactions', ['user_id' => null, 'type' => 'in']);
    }

    // -----------------------------------------------------------------------
    // Input validation
    // -----------------------------------------------------------------------

    public function test_per_page_capped_at_200(): void
    {
        $response = $this->withToken($this->readToken)->getJson('/api/v1/stock-balances?per_page=9999');
        // Should either cap silently (200 OK) or return 422 — never 500
        $this->assertNotEquals(500, $response->status());
        $response->assertStatus(422); // per_page max:200 validation
    }

    public function test_invalid_category_id_returns_422(): void
    {
        $response = $this->withToken($this->readToken)->getJson('/api/v1/stock-balances?category_id=99999');
        $response->assertStatus(422);
    }

    public function test_outbound_sync_insufficient_stock_returns_422(): void
    {
        $dept = Department::create(['name' => 'Test Dept']);
        Item::factory()->create(['barcode' => 'TEST-NO-STOCK', 'department_id' => $dept->id, 'quantity' => 0]);

        $response = $this->withToken($this->writeToken)->postJson('/api/v1/transactions/external-sync', [
            'barcode' => 'TEST-NO-STOCK',
            'type' => 'out',
            'quantity' => 50,
            'movement_reason' => 'sale',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('transactions', ['movement_reason' => 'sale']);
    }

    // -----------------------------------------------------------------------
    // Sanctum Token Expiration & Abilities + Hashed Integration Tokens
    // -----------------------------------------------------------------------

    public function test_expired_sanctum_token_returns_401(): void
    {
        $user = User::factory()->superAdmin()->create();
        $expiredToken = $user->createToken('erp-expired', ['read', 'write'], now()->subMinute())->plainTextToken;

        $this->withToken($expiredToken)
            ->getJson('/api/v1/stock-balances')
            ->assertStatus(401);
    }

    public function test_sanctum_read_only_token_returns_403_on_external_sync(): void
    {
        $dept = Department::create(['name' => 'Sanctum Dept']);
        Item::factory()->create(['barcode' => 'SANC-BC-01', 'department_id' => $dept->id, 'quantity' => 10]);

        $user = User::factory()->superAdmin()->create();
        $readOnlyToken = $user->createToken('erp-read-only', ['read'], now()->addDays(90))->plainTextToken;

        $this->withToken($readOnlyToken)
            ->postJson('/api/v1/transactions/external-sync', [
                'barcode' => 'SANC-BC-01',
                'type' => 'in',
                'quantity' => 5,
                'movement_reason' => 'purchase',
            ])
            ->assertStatus(403);
    }

    public function test_sha256_hashed_integration_token_authenticates_and_expired_hashed_token_returns_401(): void
    {
        $rawSecret = 'super-secret-erp-integration-key-2026';
        $expiredSecret = 'expired-secret-erp-key';

        config(['api.tokens' => [
            [
                'hash' => hash('sha256', $rawSecret),
                'scope' => 'read',
                'name' => 'Hashed ERP Client',
                'expires_at' => now()->addDays(30)->toDateTimeString(),
            ],
            [
                'hash' => hash('sha256', $expiredSecret),
                'scope' => 'read',
                'name' => 'Expired Hashed Client',
                'expires_at' => now()->subDay()->toDateTimeString(),
            ],
        ]]);

        $this->withToken($rawSecret)
            ->getJson('/api/v1/stock-balances')
            ->assertStatus(200);

        $this->withToken($expiredSecret)
            ->getJson('/api/v1/stock-balances')
            ->assertStatus(401);
    }
}
