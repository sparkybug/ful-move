<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\Fare;
use App\Models\Run;
use App\Models\Terminal;
use App\Models\User;
use App\Models\WalletTransfer;
use App\Services\ShuttleService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'runs' => Run::with(['bus', 'driver', 'origin', 'destination'])->withCount(['activeBookings', 'bookings as cancelled_count' => fn ($q) => $q->where('status', 'cancelled')])->latest()->paginate(15),
            'stats' => ['boarding' => Run::where('status', 'boarding')->count(), 'departed' => Run::where('status', 'departed')->count(), 'bookings' => Booking::whereIn('status', ['booked', 'boarded'])->whereHas('run', fn ($q) => $q->where('status', 'boarding'))->count(), 'pending' => Bus::where('approval_status', 'pending')->count()],
        ]);
    }

    public function buses()
    {
        return view('admin.buses', ['buses' => Bus::with('driver')->orderByRaw("CASE WHEN approval_status = 'pending' THEN 0 ELSE 1 END")->latest()->paginate(20)]);
    }

    public function approve(Request $request, Bus $bus)
    {
        $data = $request->validate(['approval_status' => ['required', Rule::in(['approved', 'rejected'])]]);
        DB::transaction(function () use ($bus, $data) {
            $locked = Bus::whereKey($bus->id)->lockForUpdate()->firstOrFail();
            if ($data['approval_status'] === 'rejected' && $locked->runs()->whereIn('status', ['boarding', 'departed'])->exists()) {
                throw ValidationException::withMessages(['bus' => 'Finish or cancel the active run before rejecting this bus.']);
            }
            $locked->update($data);
        }, 5);

        return back()->with('success', 'Bus registration '.$data['approval_status'].'.');
    }

    public function settings()
    {
        return view('admin.settings', ['terminals' => Terminal::all(), 'fares' => Fare::with(['origin', 'destination'])->orderBy('bus_type')->get()]);
    }

    public function terminal(Request $request, ?Terminal $terminal = null)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('terminals')->ignore($terminal?->id)], 'active' => ['required', 'boolean']]);
        $terminal ? $terminal->update($data) : Terminal::create($data);

        return back()->with('success', 'Terminal saved.');
    }

    public function fare(Request $request)
    {
        $data = $request->validate(['origin_id' => ['required', 'exists:terminals,id'], 'destination_id' => ['required', 'exists:terminals,id', 'different:origin_id'], 'bus_type' => ['required', Rule::in(Bus::TYPES)], 'amount' => ['required', 'string'], 'active' => ['required', 'boolean']]);
        $amount = Money::parse($data['amount']);
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => 'The fare must be greater than zero.']);
        }
        Fare::updateOrCreate(['origin_id' => $data['origin_id'], 'destination_id' => $data['destination_id'], 'bus_type' => $data['bus_type']], ['amount_kobo' => $amount, 'active' => $data['active']]);

        return back()->with('success', 'Fare saved. Existing runs keep their original fare.');
    }

    public function students(Request $request)
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $request->string('search')->toString();

        return view('admin.students', ['students' => User::with('wallet')->where('role', 'student')->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('student_identifier', 'like', '%'.$search.'%')))->orderBy('name')->paginate(10)->withQueryString()]);
    }

    public function credit(Request $request, User $student, ShuttleService $service)
    {
        $data = $request->validate(['amount' => ['required', 'string'], 'reason' => ['required', 'string', 'max:255'], 'operation_key' => ['required', 'uuid']]);
        $service->credit($request->user(), $student, Money::parse($data['amount']), $data['reason'], $data['operation_key']);

        return back()->with('success', 'Internal transport credit issued to '.$student->name.'.');
    }

    public function cancelRun(Request $request, Run $run, ShuttleService $service)
    {
        $service->transition($request->user(), $run->id, 'cancelled');

        return back()->with('success', 'Run cancelled. Paid bookings have been refunded.');
    }

    public function reports(Request $request)
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $period = [$from.' 00:00:00', $to.' 23:59:59'];
        $base = WalletTransfer::whereBetween('created_at', $period);
        $totals = ['bookings' => (clone $base)->where('kind', 'booking')->sum('amount_kobo'), 'refunds' => (clone $base)->where('kind', 'refund')->sum('amount_kobo'), 'credits' => (clone $base)->where('kind', 'credit')->sum('amount_kobo')];
        $drivers = DB::table('wallet_entries as e')->join('wallets as w', 'w.id', '=', 'e.wallet_id')->join('users as u', 'u.id', '=', 'w.user_id')->where('u.role', 'driver')->whereBetween('e.created_at', $period)->select('u.name')->selectRaw("SUM(CASE WHEN e.type = 'credit' THEN e.amount_kobo ELSE -CAST(e.amount_kobo AS SIGNED) END) as net_kobo")->groupBy('u.id', 'u.name')->get();

        return view('admin.reports', ['transfers' => (clone $base)->with(['actor', 'recipient', 'booking.run.bus'])->latest('id')->paginate(25)->withQueryString(), 'totals' => $totals, 'drivers' => $drivers, 'from' => $from, 'to' => $to]);
    }
}
