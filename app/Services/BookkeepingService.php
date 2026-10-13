<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class BookkeepingService
{
    public function getIncome(Event $event): int
    {
        return (int) DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.event_id', $event->id)
            ->where('invoices.status', 'lunas')
            ->sum('invoice_items.subtotal');
    }

    public function getTotalPrizes(Event $event): int
    {
        return (int) DB::table('prizes')
            ->join('event_sessions', 'prizes.event_session_id', '=', 'event_sessions.id')
            ->where('event_sessions.event_id', $event->id)
            ->sum('prizes.nominal');
    }

    public function getTotalExpenses(Event $event): int
    {
        return (int) Expense::where('event_id', $event->id)
            ->sum('nominal');
    }

    public function getExpensesByCategory(Event $event): array
    {
        $result = DB::table('expenses')
            ->where('event_id', $event->id)
            ->where('deleted_at', null)
            ->groupBy('kategori')
            ->selectRaw('kategori, SUM(nominal) as total')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        return array_map(fn ($item) => [
            'kategori' => $item->kategori,
            'total' => (int) $item->total,
        ], $result);
    }

    public function getUnpaidTotal(Event $event): int
    {
        return (int) DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.event_id', $event->id)
            ->where('invoices.status', 'belum_lunas')
            ->sum('invoice_items.subtotal');
    }

    public function getBookkeeping(Event $event): array
    {
        $income = $this->getIncome($event);
        $prizes = $this->getTotalPrizes($event);
        $expenses = $this->getTotalExpenses($event);
        $unpaid = $this->getUnpaidTotal($event);

        return [
            'pemasukan' => $income,
            'belum_lunas' => $unpaid,
            'hadiah' => $prizes,
            'pengeluaran' => $expenses,
            'saldo' => $income - $prizes - $expenses,
            'per_kategori' => $this->getExpensesByCategory($event),
        ];
    }
}
