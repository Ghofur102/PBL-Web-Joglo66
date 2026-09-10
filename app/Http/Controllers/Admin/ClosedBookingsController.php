<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingDetail;
use App\Enums\BookingDetailStatus;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;

class ClosedBookingsController extends Controller
{
    use FieldAccessTrait;

    public function __invoke(Request $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            $query = BookingDetail::query()
                ->whereIn('status', [
                    BookingDetailStatus::FIELD_CLOSURE->value,
                    BookingDetailStatus::CLOSED_FIELD_CANCELLED->value,
                    BookingDetailStatus::CLOSED_FIELD_RESCHEDULE->value
                ])
                ->with(['booking.user', 'booking.field'])
                ->orderBy('play_date', 'desc')
                ->orderBy('start_play_time');

            if ($user && $user->role === 'worker') {
                $fieldIds = $this->getAccessibleFieldIds($user);
                $query->whereHas('booking', function($q) use ($fieldIds) {
                    $q->whereIn('fk_field_id', $fieldIds);
                });
            }

            if ($request->filled('field_id')) {
                $query->whereHas('booking', function($q) use ($request) {
                    $q->where('fk_field_id', $request->field_id);
                });
            }

            if ($request->filled('date')) {
                $query->where('play_date', $request->date);
            }

            $data = [
                'success'         => true,
                'closed_bookings' => $query->get(),
            ];
        } catch (Throwable $e) {
            $status = 500;
            $data = [
                'success' => false, 
                'message' => 'Gagal memuat riwayat penutupan: ' . $e->getMessage()
            ];
        }

        return response()->json($data, $status);
    }
}
