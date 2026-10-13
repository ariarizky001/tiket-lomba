<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create();
    }

    public function test_get_expense_categories()
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/expense-categories');
        
        $response->assertStatus(200)
            ->assertJsonCount(7)
            ->assertJsonFragment(['Gaji']);
    }

    public function test_create_expense()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson("/api/events/{$this->event->id}/expenses", [
            'tanggal' => '2026-10-13',
            'kategori' => 'Gaji',
            'keterangan' => 'Gaji juri sesi A',
            'penerima' => 'John Doe',
            'nominal' => 100000,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['kategori' => 'Gaji', 'nominal' => 100000]);
        
        $this->assertDatabaseHas('expenses', [
            'event_id' => $this->event->id,
            'keterangan' => 'Gaji juri sesi A',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_create_expense_validation()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson("/api/events/{$this->event->id}/expenses", [
            'tanggal' => 'invalid',
            'kategori' => 'Invalid',
            'keterangan' => '',
            'nominal' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.tanggal', ['The tanggal field must be a valid date.'])
            ->assertJsonPath('errors.kategori', ['The selected kategori is invalid.'])
            ->assertJsonPath('errors.keterangan', ['The keterangan field is required.']);
    }

    public function test_update_expense()
    {
        Sanctum::actingAs($this->user);
        
        $expense = Expense::factory()->create(['event_id' => $this->event->id]);
        
        $response = $this->putJson("/api/expenses/{$expense->id}", [
            'tanggal' => '2026-10-14',
            'kategori' => 'Reimburse',
            'keterangan' => 'Updated keterangan',
            'penerima' => 'Jane Doe',
            'nominal' => 50000,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['keterangan' => 'Updated keterangan', 'nominal' => 50000]);
    }

    public function test_delete_expense_soft_delete()
    {
        Sanctum::actingAs($this->user);
        
        $expense = Expense::factory()->create(['event_id' => $this->event->id]);
        $expenseId = $expense->id;
        
        $response = $this->deleteJson("/api/expenses/{$expenseId}");
        
        $response->assertStatus(204);
        
        $this->assertNotNull(Expense::withTrashed()->find($expenseId)->deleted_at, 'deleted_at should be set');
        $this->assertCount(0, Expense::where('id', $expenseId)->get(), 'Soft deleted record should not appear in normal query');
    }

    public function test_get_expenses_list()
    {
        Sanctum::actingAs($this->user);
        
        Expense::factory()->count(3)->create(['event_id' => $this->event->id]);
        
        $response = $this->getJson("/api/events/{$this->event->id}/expenses");
        
        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_bookkeeping_calculation()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create(['event_id' => $this->event->id, 'harga' => 100000]);
        
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id, 'status' => 'lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 3,
            'harga' => 100000,
            'subtotal' => 300000,
        ]);
        
        Expense::factory()->create([
            'event_id' => $this->event->id,
            'kategori' => 'Gaji',
            'nominal' => 100000,
        ]);
        
        Expense::factory()->create([
            'event_id' => $this->event->id,
            'kategori' => 'Reimburse',
            'nominal' => 50000,
        ]);
        
        $response = $this->getJson("/api/events/{$this->event->id}/bookkeeping");
        
        $response->assertStatus(200)
            ->assertJsonPath('pemasukan', 300000)
            ->assertJsonPath('hadiah', 0)
            ->assertJsonPath('pengeluaran', 150000)
            ->assertJsonPath('saldo', 150000);
        
        $this->assertCount(2, $response->json('per_kategori'));
    }

    public function test_bookkeeping_income_same_as_report()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create(['event_id' => $this->event->id, 'harga' => 100000]);
        
        $invoice1 = Invoice::factory()->create(['event_id' => $this->event->id, 'status' => 'lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice1->id,
            'event_session_id' => $session->id,
            'qty' => 2,
            'harga' => 100000,
            'subtotal' => 200000,
        ]);
        
        $invoice2 = Invoice::factory()->create(['event_id' => $this->event->id, 'status' => 'belum_lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice2->id,
            'event_session_id' => $session->id,
            'qty' => 1,
            'harga' => 100000,
            'subtotal' => 100000,
        ]);
        
        $reportResponse = $this->getJson("/api/events/{$this->event->id}/report");
        $bookkeepingResponse = $this->getJson("/api/events/{$this->event->id}/bookkeeping");
        
        $this->assertEquals(
            $reportResponse->json('ringkasan.pemasukan'),
            $bookkeepingResponse->json('pemasukan')
        );
    }

    public function test_delete_event_with_expenses_rejected()
    {
        Sanctum::actingAs($this->user);
        
        Expense::factory()->create(['event_id' => $this->event->id]);
        
        $response = $this->deleteJson("/api/events/{$this->event->id}");
        
        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Event sudah memiliki data pembukuan dan tidak dapat dihapus']);
    }

    public function test_protected_endpoints_require_auth()
    {
        $this->getJson('/api/expense-categories')->assertStatus(401);
        $this->getJson("/api/events/{$this->event->id}/expenses")->assertStatus(401);
        $this->postJson("/api/events/{$this->event->id}/expenses", [])->assertStatus(401);
    }

    public function test_bookkeeping_no_n1()
    {
        Sanctum::actingAs($this->user);
        
        $session = EventSession::factory()->create(['event_id' => $this->event->id]);
        $invoice = Invoice::factory()->create(['event_id' => $this->event->id, 'status' => 'lunas']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'event_session_id' => $session->id,
            'qty' => 1,
            'harga' => 100000,
        ]);
        
        Expense::factory()->count(5)->create(['event_id' => $this->event->id]);
        
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/events/{$this->event->id}/bookkeeping");
        $queries5 = count(DB::getQueryLog());
        DB::disableQueryLog();
        
        Expense::factory()->count(15)->create(['event_id' => $this->event->id]);
        
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/events/{$this->event->id}/bookkeeping");
        $queries20 = count(DB::getQueryLog());
        
        $this->assertEquals($queries5, $queries20, 'Query count should remain constant');
    }
}
