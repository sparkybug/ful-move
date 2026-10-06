<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\Fare;
use App\Models\Run;
use App\Services\ShuttleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        return view('driver.index', [
            'buses' => $request->user()->buses()->latest()->get(),
            'runs' => Run::with(['bus', 'origin', 'destination'])->withCount('activeBookings')->where('driver_id', $request->user()->id)->latest()->paginate(10),
            'fares' => Fare::with(['origin', 'destination'])->where('active', true)->whereHas('origin', fn ($q) => $q->where('active', true))->whereHas('destination', fn ($q) => $q->where('active', true))->get(),
        ]);
    }

    public function registerBus(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:40', 'unique:buses'],
            'type' => ['required', Rule::in(Bus::TYPES)], 'capacity' => ['required', 'integer', 'between:1,100'],
            'ownership_details' => ['required', 'string', 'max:255'],
        ]);
        $request->user()->buses()->create($data);

        return back()->with('success', 'Bus submitted. An admin will review it before you can open a run.');
    }

    public function openRun(Request $request, ShuttleService $service)
    {
        $data = $request->validate(['bus_id' => ['required', 'integer', 'exists:buses,id'], 'fare_id' => ['required', 'integer', 'exists:fares,id'], 'estimated_departure_at' => ['required', 'date', 'after:now'], 'at_terminal' => ['accepted']]);
        $run = $service->openRun($request->user(), $data['bus_id'], $data['fare_id'], $data['estimated_departure_at']);

        return redirect()->route('driver.runs.show', $run)->with('success', 'Boarding is open. Students can now book this bus.');
    }

    public function show(Request $request, Run $run)
    {
        abort_unless($run->driver_id === $request->user()->id, 403);

        return view('driver.run', ['run' => $run->load(['bus', 'origin', 'destination', 'bookings.student'])->loadCount('activeBookings')]);
    }

    public function update(Request $request, Run $run, ShuttleService $service)
    {
        $data = $request->validate(['walk_in_count' => ['nullable', 'integer', 'min:0', 'max:100'], 'estimated_departure_at' => ['nullable', 'date', 'after:now']]);
        $service->updateRun($request->user(), $run->id, isset($data['walk_in_count']) ? (int) $data['walk_in_count'] : null, $data['estimated_departure_at'] ?? null);

        return back()->with('success', 'Run updated. The student board will refresh automatically.');
    }

    public function board(Request $request, Booking $booking, ShuttleService $service)
    {
        $service->board($request->user(), $booking->id);

        return back()->with('success', 'Student checked in. Their seat was already reserved.');
    }

    public function transition(Request $request, Run $run, ShuttleService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['departed', 'arrived', 'cancelled'])]]);
        $service->transition($request->user(), $run->id, $data['status']);

        return back()->with('success', $data['status'] === 'cancelled' ? 'Run cancelled and all paid bookings refunded.' : 'Run marked '.$data['status'].'.');
    }
}
