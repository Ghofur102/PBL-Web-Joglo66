<?php

namespace App\Services\Admin;

use App\Models\Field;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Payment;
use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingService
{
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    public function getBookingList(array $fieldIds, array $filters): array
    {
        $today = Carbon::now()->format('Y-m-d');
        $query = Booking::query()->with(['user', 'details', 'payments', 'field']);

        if (!empty($fieldIds)) {
            $query->whereIn('fk_field_id', $fieldIds);
        }

        if (!empty($filters['field_id'])) {
            $query->where('fk_field_id', $filters['field_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('team_name', 'LIKE', "%{$filters['search']}%");
        }

        $bookings = $query->get();
        $todayBookings = [];
        $upcomingBookings = [];

        foreach ($bookings as $booking) {
            $fieldName = $booking->field->name ?? 'Unknown Field';

            foreach ($booking->details as $detail) {
                $this->categorizeDetail($todayBookings, $upcomingBookings, $booking, $detail, $fieldName, $today, $filters);
            }
        }

        usort($todayBookings, fn($a, $b) => strcmp($a['sort_datetime'], $b['sort_datetime']));
        usort($upcomingBookings, fn($a, $b) => strcmp($a['sort_datetime'], $b['sort_datetime']));

        $closedAffectedCount = $this->countClosedAffectedBookings($fieldIds, $filters['field_id'] ?? null);

        $limit = $filters['limit'] ?? 20;
        return [
            'today'                 => array_slice($todayBookings, 0, $limit),
            'upcoming'              => array_slice($upcomingBookings, 0, $limit),
            'closed_affected_count' => $closedAffectedCount,
        ];
    }

    public function createBooking(array $payload): Booking
    {
        $this->validateAndCalculateDetails($payload['field_id'], $payload['details']);

        return DB::transaction(function () use ($payload) {
            $booking = Booking::create([
                'fk_user_id'      => $payload['user_id'],
                'fk_field_id'     => $payload['field_id'],
                'team_name'       => $payload['team_name'],
                'booking_date'    => $payload['booking_date'],
                'customer_phone'  => $payload['customer_phone'] ?? null,
                'customer_email'  => $payload['customer_email'] ?? null,
                'notes'           => $payload['notes'] ?? null,
            ]);

            foreach ($payload['details'] as $detail) {
                BookingDetail::create([
                    'fk_booking_id'   => $booking->id,
                    'start_play_time' => $detail['start_play_time'],
                    'end_play_time'   => $detail['end_play_time'],
                    'play_date'       => $detail['play_date'],
                    'price'           => $detail['price'],
                    'status'          => BookingDetailStatus::WAITING->value,
                ]);
            }

            return $booking;
        });
    }

    public function getBookingDetailInfo(BookingDetail $detail): array
    {
        $start = Carbon::parse($detail->start_play_time);
        $end = Carbon::parse($detail->end_play_time);
        $duration = max(1, $start->diffInHours($end));

        $allPayments = $detail->booking->payments->where('status', PaymentStatus::SUCCESS->value);

        $totalBookingPaid = $allPayments->whereIn('payment_type', [
            PaymentType::DOWN_PAYMENT->value,
            PaymentType::FINAL_PAYMENT->value,
            PaymentType::RESCHEDULE_FEE->value
        ])->sum('amount');

        $totalBookingRefund = $allPayments->where('payment_type', PaymentType::REFUND->value)->sum('amount');

        $totalSessionsCount = $detail->booking->details->count();

        $globalPaid = $this->calculateGlobalPaidForBooking($allPayments);

        $allocatedGlobalDp = $totalSessionsCount > 0 ? ($globalPaid / $totalSessionsCount) : 0;

        $sessions = $detail->booking->details->map(function ($item) use ($allPayments, $allocatedGlobalDp) {
            $specificPaid = $allPayments->where('fk_booking_detail_id', $item->id)
                ->whereIn('payment_type', [
                    PaymentType::DOWN_PAYMENT->value,
                    PaymentType::FINAL_PAYMENT->value,
                    PaymentType::RESCHEDULE_FEE->value
                ])
                ->sum('amount');

            $specificRefund = $allPayments->where('payment_type', PaymentType::REFUND->value)
                ->where('fk_booking_detail_id', $item->id)
                ->sum('amount');

            $sessionPaid = $allocatedGlobalDp + $specificPaid - $specificRefund;

            $remainingPayment = $this->isClosedOrCancelledStatus($item->status)
                ? 0
                : max(0, $item->price - $sessionPaid);

            return [
                'id'                => $item->id,
                'play_date'         => Carbon::parse($item->play_date)->format('d M Y'),
                'start_time'        => Carbon::parse($item->start_play_time)->format('H:i'),
                'end_time'          => Carbon::parse($item->end_play_time)->format('H:i'),
                'price'             => (int)$item->price,
                'status'            => $item->status,
                'total_paid'        => (int)$sessionPaid,
                'remaining_payment' => (int)$remainingPayment,
                'refund_amount'     => (int)$specificRefund
            ];
        });

        $closures = DB::table('field_closures')
            ->where('fk_field_id', $detail->booking->fk_field_id)
            ->get(['field_closure_start_time', 'field_closure_end_time']);

        return [
            'booking_id' => $detail->booking->id,
            'user_info' => [
                'name'      => $detail->booking->team_name ?? 'Guest',
                'email'     => $detail->booking->customer_email ?? '-',
                'phone'     => $detail->booking->customer_phone ?? '-',
                'team_name' => $detail->booking->team_name ?? '-',
                'notes'     => $detail->booking->notes ?? '-',
            ],
            'field_info' => [
                'id'        => $detail->booking->fk_field_id,
                'name'      => $detail->booking->field->name ?? 'Unknown Field',
                'category'  => $detail->booking->field->category ?? 'Unknown Category',
                'image_url' => $detail->booking->field->image_url,
            ],
            'service_info' => [
                'duration'           => $duration,
                'price_per_hour'     => $detail->price / $duration,
                'total_price'        => (int)$detail->booking->details->sum(fn($d) => $d->price),
                'total_down_payment' => (int)($totalBookingPaid - $totalBookingRefund),
            ],
            'payment_details' => [
                'total_price'    => (int)$detail->booking->details->sum(fn($d) => $d->price),
                'total_paid'     => (int)($totalBookingPaid - $totalBookingRefund),
                'payment_method' => $allPayments->last()->method ?? 'cash',
            ],
            'sessions'       => $sessions,
            'field_closures' => $closures
        ];
    }

    public function executeRefundOverpayment(BookingDetail $detail): void
    {
        $totalPaid = $this->calculateTotalPaidForDetail($detail->booking, $detail);
        $overpayment = $totalPaid - $detail->price;

        if ($overpayment <= 0) {
            throw new HttpException(400, 'Tidak ada kelebihan pembayaran pada sesi ini.');
        }

        DB::transaction(function () use ($detail, $overpayment) {
            Payment::create([
                'fk_booking_id'        => $detail->fk_booking_id,
                'fk_booking_detail_id' => $detail->id,
                'reference_id'         => 'RFD-' . strtoupper(Str::random(8)),
                'payment_type'         => PaymentType::REFUND->value,
                'method'               => 'cash',
                'amount'               => $overpayment,
                'status'               => PaymentStatus::SUCCESS->value,
                'paid_at'              => now(),
            ]);
        });
    }

    public function calculateTotalPaidForDetail($booking, $detail): float
    {
        $allPayments = $booking->payments->where('status', PaymentStatus::SUCCESS->value);
        $totalBookingPaid = $allPayments->whereIn('payment_type', [
            PaymentType::DOWN_PAYMENT->value,
            PaymentType::FINAL_PAYMENT->value,
            PaymentType::RESCHEDULE_FEE->value
        ])->sum(fn($p) => $p->amount);

        $totalBookingRefund = $allPayments->where('payment_type', PaymentType::REFUND->value)->sum(fn($p) => $p->amount);
        $totalDetailsCount = $booking->details->count();

        if ($totalDetailsCount === 1) {
            return $totalBookingPaid - $totalBookingRefund;
        }

        $specificPaid = $allPayments->where('fk_booking_detail_id', $detail->id)->whereIn('payment_type', [
            PaymentType::DOWN_PAYMENT->value,
            PaymentType::FINAL_PAYMENT->value,
            PaymentType::RESCHEDULE_FEE->value
        ])->sum(fn($p) => $p->amount);

        $specificRefund = $allPayments->where('fk_booking_detail_id', $detail->id)->where('payment_type', PaymentType::REFUND->value)->sum(fn($p) => $p->amount);

        $genericPaid = $this->calculateGlobalPaidForBooking($allPayments);

        return ($specificPaid - $specificRefund) + ($genericPaid / $totalDetailsCount);
    }

    private function calculateGlobalPaidForBooking($allPayments): float
    {
        return (float) $allPayments->whereNull('fk_booking_detail_id')
            ->whereIn('payment_type', [
                PaymentType::DOWN_PAYMENT->value,
                PaymentType::FINAL_PAYMENT->value,
                PaymentType::RESCHEDULE_FEE->value
            ])
            ->sum(fn($p) => $p->amount);
    }

    private function isClosedOrCancelledStatus(string $status): bool
    {
        $normalized = strtolower($status);
        $nonChargeableStatuses = [
            strtolower(BookingDetailStatus::FIELD_CLOSURE->value),
            strtolower(BookingDetailStatus::CANCELLED->value),
            strtolower(BookingDetailStatus::CLOSED_FIELD_CANCELLED->value),
            strtolower(BookingDetailStatus::CLOSED_FIELD_RESCHEDULE->value),
        ];

        return in_array($normalized, $nonChargeableStatuses, true);
    }

    private function shouldSkipDetail(string $playDate, ?string $startDate, ?string $endDate): bool
    {
        if ($startDate && $endDate) {
            return $playDate < $startDate || $playDate > $endDate;
        }
        if ($startDate) {
            return $playDate !== $startDate;
        }
        return false;
    }

    private function buildBookingItem($booking, $detail, string $fieldName): array
    {
        $totalPaid = $this->calculateTotalPaidForDetail($booking, $detail);
        $remainingPayment = $this->isClosedOrCancelledStatus($detail->status)
            ? 0
            : max(0, $detail->price - $totalPaid);

        $refundAmount = $booking->payments->where('status', PaymentStatus::SUCCESS->value)
            ->where('payment_type', PaymentType::REFUND->value)
            ->where('fk_booking_detail_id', $detail->id)
            ->sum(fn($p) => $p->amount);

        return [
            'id'                => $detail->id,
            'sort_datetime'     => $detail->play_date . ' ' . $detail->start_play_time,
            'date'              => Carbon::parse($detail->play_date)->format('d'),
            'month'             => Carbon::parse($detail->play_date)->format('M'),
            'year'              => Carbon::parse($detail->play_date)->format('Y'),
            'title'             => "{$booking->team_name}",
            'tenant_name'       => "{$booking->user->name}",
            'time'              => Carbon::parse($detail->start_play_time)->format('H:i') . ' - ' . Carbon::parse($detail->end_play_time)->format('H:i'),
            'description'       => $fieldName,
            'price'             => $detail->price,
            'status'            => $detail->status,
            'total_paid'        => $totalPaid,
            'remaining_payment' => $remainingPayment,
            'refund_amount'     => $refundAmount
        ];
    }

    private function categorizeDetail(array &$todayBookings, array &$upcomingBookings, $booking, $detail, string $fieldName, string $today, array $filters): void
    {
        if ($this->shouldSkipDetail($detail->play_date, $filters['start_date'] ?? null, $filters['end_date'] ?? null)) {
            return;
        }

        $bookingItem = $this->buildBookingItem($booking, $detail, $fieldName);

        if ($this->hasActiveFilters($filters) || $detail->play_date === $today) {
            $todayBookings[] = $bookingItem;
            return;
        }

        if ($detail->play_date > $today) {
            $upcomingBookings[] = $bookingItem;
        }
    }

    private function hasActiveFilters(array $filters): bool
    {
        return !empty($filters['start_date']) || !empty($filters['end_date']) || !empty($filters['search']);
    }

    private function countClosedAffectedBookings(array $fieldIds, mixed $filterFieldId): int
    {
        $query = BookingDetail::query()->where('status', BookingDetailStatus::FIELD_CLOSURE->value);

        if (!empty($fieldIds)) {
            $query->whereHas('booking', fn($q) => $q->whereIn('fk_field_id', $fieldIds));
        }

        if (!empty($filterFieldId)) {
            $query->whereHas('booking', fn($q) => $q->where('fk_field_id', $filterFieldId));
        }

        return $query->count();
    }

    private function validateAndCalculateDetails(int $fieldId, array $details): int
    {
        $totalPrice = 0;
        $todayDate = Carbon::now()->format('Y-m-d');
        $currentTime = Carbon::now()->format('H:i');

        foreach ($details as $index => $detail) {
            if ($detail['start_play_time'] >= $detail['end_play_time']) {
                throw new HttpException(400, "Detail #{$index} has invalid time range.");
            }
            if ($detail['play_date'] < $todayDate) {
                throw new HttpException(400, 'Tidak dapat memesan untuk tanggal yang sudah lewat.');
            }
            if ($detail['play_date'] === $todayDate && $detail['start_play_time'] <= $currentTime) {
                throw new HttpException(400, 'Waktu main sudah terlewat untuk hari ini.');
            }
            if ($this->hasFieldConflict($fieldId, $detail)) {
                throw new HttpException(400, 'Lapangan sudah dipesan atau sedang ditutup pada slot waktu tersebut.');
            }
            if (!$this->validateFieldPrice($fieldId, $detail)) {
                throw new HttpException(400, 'Validasi harga gagal untuk detail booking.');
            }

            $totalPrice += $detail['price'];
        }

        return $totalPrice;
    }

    private function hasFieldConflict(int $fieldId, array $detail): bool
    {
        $slotStart = Carbon::parse($detail['play_date'] . ' ' . $detail['start_play_time'])->format(self::DATE_TIME_FORMAT);
        $slotEnd = Carbon::parse($detail['play_date'] . ' ' . $detail['end_play_time'])->format(self::DATE_TIME_FORMAT);

        $isClosed = DB::table('field_closures')
            ->where('fk_field_id', $fieldId)
            ->where('field_closure_start_time', '<', $slotEnd)
            ->where('field_closure_end_time', '>', $slotStart)
            ->exists();

        if ($isClosed) {
            return true;
        }

        return BookingDetail::query()
            ->whereHas('booking', function ($query) use ($fieldId) {
                $query->where('fk_field_id', $fieldId);
            })
            ->where('play_date', $detail['play_date'])
            ->whereNotIn('status', [BookingDetailStatus::CANCELLED->value, BookingDetailStatus::FIELD_CLOSURE->value, BookingDetailStatus::CLOSED_FIELD_CANCELLED->value])
            ->where('start_play_time', '<', $detail['end_play_time'])
            ->where('end_play_time', '>', $detail['start_play_time'])
            ->exists();
    }

    private function validateFieldPrice(int $fieldId, array $detail): bool
    {
        $dayName = strtolower(date('l', strtotime($detail['play_date'])));

        $price = DB::table('field_prices')
            ->where('fk_field_id', $fieldId)
            ->where('day_type', $dayName)
            ->where('start_time', '<=', $detail['start_play_time'])
            ->where('end_time', '>=', $detail['end_play_time'])
            ->value('price');

        if (!is_null($price) && (int)$price !== (int)$detail['price']) {
            return false;
        }

        return true;
    }
}
