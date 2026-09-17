<?php

namespace App\Http\Controllers\Tenant\Booking;

use App\Services\DuitkuService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Booking\ConfirmBookingRequest;
use App\Http\Requests\Tenant\Booking\StoreTenantBookingRequest;
use App\Models\Field;
use App\Services\Tenant\Booking\TenantBookingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class BookingController extends Controller
{
    private const ROUTE_DASHBOARD = 'tenant.booking.dashboard';

    protected TenantBookingService $bookingService;

    public function __construct(TenantBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function createForm(Request $request)
    {
        $fieldId = $request->query('field_id');

        if (! $fieldId) {
            $response = redirect()->route(self::ROUTE_DASHBOARD)->with('info', 'Silakan pilih lapangan terlebih dahulu');
        } else {
            $field = Field::query()->findOrFail($fieldId);
            $response = view('tenant.booking.create', ['field' => $field]);
        }

        return $response;
    }

    public function confirmForm(ConfirmBookingRequest $request): View
    {
        $field = Field::query()->findOrFail($request->field_id);
        $selectedSlotsRaw = json_decode($request->selected_slots, true);

        $calculatedData = $this->bookingService->calculateAndGroupSlots($field->id, $selectedSlotsRaw);

        return view('tenant.booking.confirmation', [
            'field' => $field,
            'groupedSlots' => $calculatedData['groupedSlots'],
            'totalPrice' => $calculatedData['totalPrice'],
        ]);
    }

    public function store(StoreTenantBookingRequest $request, DuitkuService $duitkuService): RedirectResponse|View
    {
        $response = null;
        $user = Auth::user();
        $userId = $user ? $user->id : 1;
        $groupedSlots = json_decode($request->booking_data, true);

        if (empty($groupedSlots)) {
            $response = redirect()->route(self::ROUTE_DASHBOARD)->with('error', 'Pilih minimal satu slot pemesanan.');
        } else {
            $fieldId = $request->validated()['field_id'] ?? $request->field_id;

            // 1. Amankan slot menggunakan Redis Atomic Lock sebelum menyentuh database
            $lockResult = $this->acquireSlotLocks((int)$fieldId, $groupedSlots);

            if (!$lockResult['success']) {
                $this->releaseSlotLocks($lockResult['locks']);
                $response = redirect()->route(self::ROUTE_DASHBOARD)
                    ->with('error', 'Salah satu slot jam pilihan Anda baru saja diproses oleh orang lain. Silakan pilih slot waktu yang lain.');
            } else {
                try {
                    $result = $this->bookingService->processBookingTransaction(
                        (int)$userId,
                        $user,
                        $request->validated(),
                        $groupedSlots,
                        $duitkuService
                    );

                    $booking = $result['booking'];
                    $this->clearBookingCache((int)$fieldId, $groupedSlots);

                    $payment = \App\Models\Payment::query()->where('fk_booking_id', $booking->id)->first();

                    $response = view('tenant.booking.checkout', [
                        'booking'     => $booking,
                        'reference'   => $payment ? $payment->reference_id : '',
                        'amountToPay' => $payment ? $payment->amount : 0,
                    ]);
                } catch (Throwable $e) {
                    $response = redirect()->route(self::ROUTE_DASHBOARD)->with('error', 'Transaksi gagal: ' . $e->getMessage());
                } finally {
                    // 4. WAJIB: Lepaskan semua kunci lokal setelah proses selesai
                    $this->releaseSlotLocks($lockResult['locks']);
                }
            }
        }

        return $response;
    }

    /**
     * Mengamankan Atomic Lock berdasarkan struktur data bersarang (Date sebagai Key)
     */
    private function acquireSlotLocks(int $fieldId, array $groupedSlots): array
    {
        $acquiredLocks = [];
        $isAllLocked = true;

        foreach ($groupedSlots as $date => $slots) {
            foreach ($slots as $slot) {
                $lockKey = "lock_field_{$fieldId}_date_{$date}_slot_" . $slot['jam'];
                $lock = Cache::lock($lockKey, 15);

                if ($lock->get()) {
                    $acquiredLocks[] = $lock;
                } else {
                    $isAllLocked = false;
                    break 2; // Keluar dari kedua tingkat perulangan sekaligus (Mereduksi Kompleksitas)
                }
            }
        }

        return [
            'success' => $isAllLocked,
            'locks'   => $acquiredLocks
        ];
    }

    private function releaseSlotLocks(array $locks): void
    {
        foreach ($locks as $activeLock) {
            $activeLock->release();
        }
    }

    private function clearBookingCache(int $fieldId, array $groupedSlots): void
    {
        foreach (array_keys($groupedSlots) as $date) {
            $cleanDate = \Carbon\Carbon::parse($date)->format('Y-m-d');
            Cache::forget("tenant_slots_field_{$fieldId}_{$cleanDate}");
        }
        Cache::forget("tenant_nearest_bookings_field_{$fieldId}");
    }

    public function success($booking_id): View|RedirectResponse
    {
        try {
            $booking = $this->bookingService->getBookingSuccessData((int) $booking_id, Auth::id());

            return view('tenant.booking.success', compact('booking'));
        } catch (Throwable $e) {
            return redirect()->route('tenant.booking.dashboard')
                ->with('error', $e->getMessage());
        }
    }
}
