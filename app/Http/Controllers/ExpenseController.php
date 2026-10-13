<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Expense;
use App\Services\BookkeepingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    protected $bookkeepingService;

    public function __construct(BookkeepingService $bookkeepingService)
    {
        $this->bookkeepingService = $bookkeepingService;
    }

    public function index(Event $event)
    {
        $expenses = Expense::where('event_id', $event->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get(['id', 'tanggal', 'kategori', 'keterangan', 'penerima', 'nominal']);

        return response()->json($expenses);
    }

    public function store(Request $request, Event $event)
    {
        $categories = config('expense.categories');

        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'kategori' => 'required|in:' . implode(',', $categories),
            'keterangan' => 'required|string|max:255',
            'penerima' => 'nullable|string|max:150',
            'nominal' => 'required|integer|min:1|max:999999999',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $expense = Expense::create([
            'event_id' => $event->id,
            'tanggal' => $request->tanggal,
            'kategori' => $request->kategori,
            'keterangan' => $request->keterangan,
            'penerima' => $request->penerima,
            'nominal' => $request->nominal,
            'created_by' => auth()->id(),
        ]);

        return response()->json($expense, 201);
    }

    public function update(Request $request, Expense $expense)
    {
        $categories = config('expense.categories');

        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'kategori' => 'required|in:' . implode(',', $categories),
            'keterangan' => 'required|string|max:255',
            'penerima' => 'nullable|string|max:150',
            'nominal' => 'required|integer|min:1|max:999999999',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $expense->update([
            'tanggal' => $request->tanggal,
            'kategori' => $request->kategori,
            'keterangan' => $request->keterangan,
            'penerima' => $request->penerima,
            'nominal' => $request->nominal,
        ]);

        return response()->json($expense);
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return response()->json(null, 204);
    }

    public function bookkeeping(Event $event)
    {
        return response()->json($this->bookkeepingService->getBookkeeping($event));
    }
}
