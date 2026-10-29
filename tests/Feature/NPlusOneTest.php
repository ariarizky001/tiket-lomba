<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Expense;
use App\Models\Prize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NPlusOneTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function test_constant_query_count_on_sessions_index()
    {
        $event3 = Event::factory()->create();
        $this->createSessionsForEvent($event3, 3);
        $this->createInvoicesWithItemsForEvent($event3, 3, 2);

        $queryCount3 = $this->countQueries(function () use ($event3) {
            $this->getJson("/api/events/{$event3->id}/sessions");
        });

        $event15 = Event::factory()->create();
        $this->createSessionsForEvent($event15, 15);
        $this->createInvoicesWithItemsForEvent($event15, 15, 5);

        $queryCount15 = $this->countQueries(function () use ($event15) {
            $this->getJson("/api/events/{$event15->id}/sessions");
        });

        $this->assertEquals($queryCount3, $queryCount15, 'Jumlah query sessions index harus konstan');
    }

    /** @test */
    public function test_constant_query_count_on_invoices_index()
    {
        $event3 = Event::factory()->create();
        $this->createSessionsForEvent($event3, 3);
        $this->createInvoicesWithItemsForEvent($event3, 3, 2);

        $queryCount3 = $this->countQueries(function () use ($event3) {
            $this->getJson("/api/events/{$event3->id}/invoices");
        });

        $event15 = Event::factory()->create();
        $this->createSessionsForEvent($event15, 15);
        $this->createInvoicesWithItemsForEvent($event15, 15, 5);

        $queryCount15 = $this->countQueries(function () use ($event15) {
            $this->getJson("/api/events/{$event15->id}/invoices");
        });

        $this->assertEquals($queryCount3, $queryCount15, 'Jumlah query invoices index harus konstan');
    }

    /** @test */
    public function test_constant_query_count_on_report()
    {
        $event3 = Event::factory()->create();
        $this->createSessionsForEvent($event3, 3);
        $this->createInvoicesWithItemsForEvent($event3, 3, 2);
        $this->createPrizesForEvent($event3, 3);

        $queryCount3 = $this->countQueries(function () use ($event3) {
            $this->getJson("/api/events/{$event3->id}/report");
        });

        $event15 = Event::factory()->create();
        $this->createSessionsForEvent($event15, 15);
        $this->createInvoicesWithItemsForEvent($event15, 15, 5);
        $this->createPrizesForEvent($event15, 15);

        $queryCount15 = $this->countQueries(function () use ($event15) {
            $this->getJson("/api/events/{$event15->id}/report");
        });

        $this->assertEquals($queryCount3, $queryCount15, 'Jumlah query report harus konstan');
    }

    /** @test */
    public function test_constant_query_count_on_rekap_peserta()
    {
        $event3 = Event::factory()->create();
        $this->createSessionsForEvent($event3, 3);
        $this->createInvoicesWithItemsForEvent($event3, 3, 2);

        $queryCount3 = $this->countQueries(function () use ($event3) {
            $this->getJson("/api/events/{$event3->id}/rekap-peserta");
        });

        $event15 = Event::factory()->create();
        $this->createSessionsForEvent($event15, 15);
        $this->createInvoicesWithItemsForEvent($event15, 15, 5);

        $queryCount15 = $this->countQueries(function () use ($event15) {
            $this->getJson("/api/events/{$event15->id}/rekap-peserta");
        });

        $this->assertEquals($queryCount3, $queryCount15, 'Jumlah query rekap-peserta harus konstan');
    }

    /** @test */
    public function test_constant_query_count_on_bookkeeping()
    {
        $event3 = Event::factory()->create();
        $this->createSessionsForEvent($event3, 3);
        $this->createExpensesForEvent($event3, 3);

        $queryCount3 = $this->countQueries(function () use ($event3) {
            $this->getJson("/api/events/{$event3->id}/bookkeeping");
        });

        $event15 = Event::factory()->create();
        $this->createSessionsForEvent($event15, 15);
        $this->createExpensesForEvent($event15, 15);

        $queryCount15 = $this->countQueries(function () use ($event15) {
            $this->getJson("/api/events/{$event15->id}/bookkeeping");
        });

        $this->assertEquals($queryCount3, $queryCount15, 'Jumlah query bookkeeping harus konstan');
    }

    private function createSessionsForEvent(Event $event, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            EventSession::factory()->forEvent($event)->create([
                'harga' => 100000,
                'kuota' => 20,
            ]);
        }
    }

    private function createInvoicesWithItemsForEvent(Event $event, int $sessionCount, int $invoicesPerSession): void
    {
        $sessions = EventSession::where('event_id', $event->id)->get();
        
        foreach ($sessions as $session) {
            for ($j = 0; $j < $invoicesPerSession; $j++) {
                $invoice = Invoice::factory()->create([
                    'event_id' => $event->id,
                    'status' => 'lunas',
                ]);
                InvoiceItem::factory()->create([
                    'invoice_id' => $invoice->id,
                    'event_session_id' => $session->id,
                    'qty' => 1,
                    'harga' => $session->harga,
                    'subtotal' => $session->harga,
                ]);
            }
        }
    }

    private function createPrizesForEvent(Event $event, int $sessionCount): void
    {
        $sessions = EventSession::where('event_id', $event->id)->get();
        
        foreach ($sessions as $session) {
            Prize::factory()->create([
                'event_session_id' => $session->id,
                'juara' => 1,
                'nominal' => 500000,
            ]);
        }
    }

    private function createExpensesForEvent(Event $event, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Expense::factory()->create([
                'event_id' => $event->id,
                'nominal' => 100000,
            ]);
        }
    }

    private function countQueries(callable $callback): int
    {
        $queryCount = 0;
        
        DB::listen(function ($query) use (&$queryCount) {
            // Exclude framework internal queries
            if (!str_contains($query->sql, 'sqlite_master') && 
                !str_contains($query->sql, 'migrations') &&
                !str_contains($query->sql, 'password_resets') &&
                !str_contains($query->sql, 'personal_access_tokens')) {
                $queryCount++;
            }
        });

        $callback();

        return $queryCount;
    }
}