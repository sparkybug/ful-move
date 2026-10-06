<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Run;
use App\Services\ShuttleService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function bookings(Request $request)
    {
        return view('student.bookings', ['bookings' => $request->user()->bookings()->with(['run.bus', 'run.origin', 'run.destination'])->latest()->paginate(12)]);
    }

    public function book(Request $request, Run $run, ShuttleService $service)
    {
        $data = $request->validate(['operation_key' => ['required', 'uuid'], 'confirmed' => ['accepted']]);
        $booking = $service->book($request->user(), $run->id, $data['operation_key']);

        return redirect()->route('tickets.show', $booking)->with('success', $booking->status === 'cancelled' ? 'This booking was already cancelled and refunded.' : 'Your seat is reserved. Show this ticket to your driver.');
    }

    public function ticket(Request $request, Booking $booking)
    {
        abort_unless($booking->student_id === $request->user()->id, 403);

        return view('student.ticket', ['booking' => $booking->load(['run.bus', 'run.origin', 'run.destination'])]);
    }

    public function cancel(Request $request, Booking $booking, ShuttleService $service)
    {
        $service->cancelBooking($request->user(), $booking->id);

        return back()->with('success', 'Ticket cancelled. The fare has been returned to your wallet.');
    }

    public function wallet(Request $request)
    {
        $wallet = $request->user()->wallet;

        return view('wallet', ['wallet' => $wallet, 'entries' => $wallet->entries()->with(['transfer.booking.run.bus', 'actor'])->latest('id')->paginate(20)]);
    }
}
