<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Models\Terminal;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['origin' => ['nullable', 'integer', 'exists:terminals,id']]);
        $runs = Run::with(['bus', 'origin', 'destination'])->withCount('activeBookings')->where('status', 'boarding')
            ->when($request->filled('origin'), fn ($q) => $q->where('origin_id', $request->integer('origin')))
            ->orderBy('estimated_departure_at')->get();
        $data = ['runs' => $runs, 'terminals' => Terminal::where('active', true)->orderBy('name')->get()];

        return response()->view($request->boolean('fragment') ? 'partials.bus-list' : 'board', $data)
            ->header('Cache-Control', 'no-store');
    }

    public function show(Run $run)
    {
        $run->load(['bus', 'origin', 'destination'])->loadCount('activeBookings');

        return response()->view('run', compact('run'))->header('Cache-Control', 'no-store');
    }

    public function availability(Request $request, Run $run)
    {
        return response()->json([
            'status' => $run->status, 'seats_left' => $run->seats_left,
            'fare_kobo' => $run->fare_snapshot_kobo,
            'balance_kobo' => $request->user()?->wallet?->balance_kobo,
            'estimate' => $run->estimate_label,
        ])->header('Cache-Control', 'no-store');
    }
}
