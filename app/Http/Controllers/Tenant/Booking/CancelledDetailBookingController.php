<?php

namespace App\Http\Controllers\Tenant\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Booking\CancelBookingActionRequest;
use App\Services\Tenant\Booking\TenantCancelBookingService;
use App\Models\BookingDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Illuminate\Support\Facades\Cache;

class CancelledDetailBookingController extends Controller
{
    protected TenantCancelBookingService $cancelService;

    public function __construct(TenantCancelBookingService $cancelService)
    {
        $this->cancelService = $cancelService;
    }

    public function formInput($detail_booking_id): View
    {
        $detail = BookingDetail::query()->with('booking.field')->findOrFail($detail_booking_id);
        $this->authorizeAccess($detail);

        $cancellationData = $this->cancelService->getCancellationData($detail);

        return view('tenant.booking.cancel.index', array_merge([
            'detail' => $detail
        ], $cancellationData));
    }

    public function confirmation(CancelBookingActionRequest $request): View
    {
        $validated = $request->validated();
        $detail = BookingDetail::query()->with('booking')->findOrFail($validated['detail_booking_id']);
        $this->authorizeAccess($detail);

        $cancellationData = $this->cancelService->getCancellationData($detail);

        return view('tenant.booking.cancel.review', array_merge([
            'detail'    => $detail,
            'validated' => $validated
        ], $cancellationData));
    }

    public function process(CancelBookingActionRequest $request): RedirectResponse
    {
        $response = redirect()->back();
        $validated = $request->validated();

        try {
            $detail = BookingDetail::query()->with('booking')->findOrFail($validated['detail_booking_id']);
            $this->authorizeAccess($detail);

            $this->cancelService->processCancellation($detail, $validated['reason']);

            Cache::forget("tenant_nearest_bookings_field_{$request->field_id}");

            $response = redirect()->route('tenant.booking.history.show', $detail->fk_booking_id)
                ->with('success', 'Booking berhasil dibatalkan.');
        } catch (Throwable $e) {
            $response = redirect()->back()->with('error', 'Gagal membatalkan booking: ' . $e->getMessage());
        }

        return $response;
    }

    private function authorizeAccess(BookingDetail $detail): void
    {
        if ($detail->booking->fk_user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki akses ke booking ini.');
        }
    }
}
