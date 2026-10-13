<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentVerificationController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Payment::with(['invoice.property', 'user', 'verifier'])->latest();

        if (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereHas('invoice', function ($inv) use ($propertyIds) {
                $inv->whereIn('property_id', $propertyIds)
                    ->orWhereHas('booking', fn ($b) => $b->whereIn('property_id', $propertyIds));
            });
        }

        $statusFilter = $request->query('status', 'pending');
        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('payment_code', 'like', "%{$q}%")
                    ->orWhereHas('invoice', fn ($inv) => $inv->where('invoice_number', 'like', "%{$q}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
            });
        }

        $payments = $query->paginate(15)->withQueryString();

        return view('dashboard.payments.index', compact('payments', 'statusFilter'));
    }

    public function show(int $id): View
    {
        $user = Auth::user();
        $payment = Payment::with(['invoice.property', 'invoice.booking.room.roomType', 'user', 'verifier'])->findOrFail($id);

        if (!$this->paymentService->canAccessPaymentProof($payment, $user)) {
            abort(403, 'Anda tidak memiliki hak akses untuk memeriksa pembayaran properti ini.');
        }

        return view('dashboard.payments.show', compact('payment'));
    }

    public function streamProof(int $id): BinaryFileResponse
    {
        $user = Auth::user();
        $payment = Payment::with('invoice')->findOrFail($id);

        if (!$this->paymentService->canAccessPaymentProof($payment, $user)) {
            abort(403, 'Akses ke bukti pembayaran ini ditolak.');
        }

        if (!$payment->proof_photo || !Storage::disk('local')->exists($payment->proof_photo)) {
            abort(404, 'File bukti pembayaran fisik tidak ditemukan.');
        }

        $fullPath = Storage::disk('local')->path($payment->proof_photo);
        $mimeType = Storage::disk('local')->mimeType($payment->proof_photo) ?? 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $payment = Payment::with('invoice')->findOrFail($id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->paymentService->verifyPayment($payment, $user, $validated['notes'] ?? null);

            return redirect()->route('dashboard.payments.index')
                ->with('success', "Pembayaran {$payment->payment_code} sebesar Rp " . number_format($payment->amount, 0, ',', '.') . " berhasil disetujui.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $payment = Payment::with('invoice')->findOrFail($id);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->paymentService->rejectPayment(
                $payment,
                $user,
                $validated['rejection_reason'],
                $validated['notes'] ?? null
            );

            return redirect()->route('dashboard.payments.index')
                ->with('warning', "Pembayaran {$payment->payment_code} ditolak dengan alasan: {$validated['rejection_reason']}");
        } catch (DomainException|ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
