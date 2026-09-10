<?php

namespace App\Services\Tenant\Booking;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TenantHistoryService
{
    private const COL_STATUS = 'status';
    private const COL_CREATED_AT = 'created_at';
    private const COL_PAYMENT_TYPE = 'payment_type';

    public function getAvailablePaymentStatuses(): array
    {
        return Payment::query()
            ->select(self::COL_STATUS)
            ->whereNotNull(self::COL_STATUS)
            ->distinct()
            ->pluck(self::COL_STATUS)
            ->toArray();
    }

    public function getPaginatedHistory(int $userId, array $filters): LengthAwarePaginator
    {
        $query = Booking::query()
            ->with(['payments', 'details.attributes'])
            ->where('fk_user_id', $userId);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                /** @var Builder $q */
                $q->where('team_name', 'like', "%{$search}%")
                    ->orWhereHas('payments', function ($pq) use ($search) {
                        /** @var Builder $pq */
                        $pq->where('reference_id', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['date'])) {
            $query->whereDate('booking_date', $filters['date']);
        }

        if (! empty($filters['status'])) {
            $status = $filters['status'];
            $query->whereHas('payments', function ($q) use ($status) {
                /** @var Builder $q */
                $q->where(self::COL_STATUS, $status);
            });
        }

        $paginator = $query->latest(self::COL_CREATED_AT)->paginate(10)->withQueryString();

        $paginator->getCollection()->transform(function ($trx) {
            /** @var Booking $trx */
            return $this->appendFinancialSummary($trx);
        });

        return $paginator;
    }

    public function getBookingDetail(int $userId, int $bookingId): Booking
    {
        $booking = Booking::query()
            ->with([
                'field',
                'details.payment',
                'payments',
                'details.attributes',
                'details.reschedules',
                'details.cancellation',
            ])
            ->where('fk_user_id', $userId)
            ->findOrFail($bookingId);

        $this->appendFinancialSummary($booking);

        $totalSessions = max(1, $booking->details->count());
        $allSuccessfulPayments = $booking->payments->where('status', PaymentStatus::SUCCESS->value);

        $globalPaidPerSession = (int) round(
            $allSuccessfulPayments->whereNull('fk_booking_detail_id')
                ->whereIn(self::COL_PAYMENT_TYPE, [
                    PaymentType::DOWN_PAYMENT->value,
                    PaymentType::FINAL_PAYMENT->value,
                ])
                ->sum('amount') / $totalSessions
        );

        $booking->details->transform(function ($detail) use ($globalPaidPerSession) {
            /** @var BookingDetail $detail */
            $detailPayments = $detail->payment instanceof Collection
                ? $detail->payment
                : ($detail->payment ? collect([$detail->payment]) : collect());

            $latestPayment = $detailPayments->sortByDesc(self::COL_CREATED_AT)->first();

            $specificInitialPaid = $detailPayments->where('status', PaymentStatus::SUCCESS->value)
                ->whereIn(self::COL_PAYMENT_TYPE, [
                    PaymentType::DOWN_PAYMENT->value,
                    PaymentType::FINAL_PAYMENT->value,
                ])
                ->sum('amount');

            $detail->initialPaid = (int) ($globalPaidPerSession + $specificInitialPaid);

            $detail->rescheduleFee = (int) $detailPayments->where(self::COL_PAYMENT_TYPE, PaymentType::RESCHEDULE_FEE->value)
                ->where('status', PaymentStatus::SUCCESS->value)
                ->sum('amount');

            $detail->totalDanaMasukSesi = $detail->initialPaid + $detail->rescheduleFee;

            $detail->refundAmount = (int) $detailPayments->where(self::COL_PAYMENT_TYPE, PaymentType::REFUND->value)
                ->where('status', PaymentStatus::SUCCESS->value)
                ->sum('amount');

            $detail->danaHangus = max(0, $detail->totalDanaMasukSesi - $detail->refundAmount);

            $currentDetailPrice = (int) $detail->price;
            $calculatedOldPrice = $currentDetailPrice;

            if ($detail->reschedules && $detail->reschedules->isNotEmpty()) {
                foreach ($detail->reschedules as $rsc) {
                    $rscPayment = $detailPayments->first(function ($p) {
                        return in_array($p->payment_type, [PaymentType::RESCHEDULE_FEE->value, PaymentType::REFUND->value])
                            || str_starts_with($p->reference_id ?? '', 'RSCH-')
                            || str_starts_with($p->reference_id ?? '', 'REF-')
                            || str_starts_with($p->reference_id ?? '', 'RSC-');
                    });

                    $diffAmount = $rscPayment ? (int) $rscPayment->amount : 0;
                    $rsc->diffAmount = $diffAmount;

                    $statusRefundNorm = strtolower($rsc->status_refund ?? 'none');
                    if (str_contains($statusRefundNorm, 'refund')) {
                        $rsc->adjustmentType = 'refund';
                        $calculatedOldPrice = $currentDetailPrice + $diffAmount;
                    } elseif (str_contains($statusRefundNorm, 'deposit')) {
                        $rsc->adjustmentType = 'fee';
                        $calculatedOldPrice = max(0, $currentDetailPrice - $diffAmount);
                    } else {
                        $rsc->adjustmentType = 'none';
                        $calculatedOldPrice = $currentDetailPrice;
                    }

                    $rsc->oldPrice = $calculatedOldPrice;
                    $rsc->newPrice = $currentDetailPrice;
                }
            }

            $detail->initialPrice = $calculatedOldPrice;

            $detail->detailStatus = strtolower($detail->status ?? $latestPayment->status ?? PaymentStatus::PENDING->value);
            $detail->detailBadge = $this->getBadgeClass($detail->detailStatus);

            $playDate = Carbon::parse($detail->play_date)->startOfDay();
            $daysUntilPlay = now()->startOfDay()->diffInDays($playDate, false);

            $isPaid = in_array($detail->detailStatus, ['active', 'success', 'reschedule']);

            $detail->canReschedule = ($daysUntilPlay >= 3) && $isPaid;
            $detail->canCancel = ($daysUntilPlay >= 3) && $isPaid;
            $detail->alreadyRescheduled = ($detail->status === BookingDetailStatus::RESCHEDULE->value);

            return $detail;
        });

        return $booking;
    }

    private function appendFinancialSummary(Booking $booking): Booking
    {
        $totalDetails = $booking->details->count();
        $cancelledDetails = $booking->details->where(self::COL_STATUS, BookingDetailStatus::CANCELLED->value)->count();

        $booking->mainPayment = $booking->payments
            ->where(self::COL_PAYMENT_TYPE, '!=', PaymentType::REFUND->value)
            ->sortByDesc(self::COL_CREATED_AT)
            ->first();

        if ($totalDetails > 0 && $totalDetails === $cancelledDetails) {
            $booking->overallStatus = BookingDetailStatus::CANCELLED->value;
        } else {
            $booking->overallStatus = strtolower($booking->mainPayment->status ?? 'unknown');
        }

        $totalAttributesPrice = $booking->details->sum(function ($detail) {
            return $detail->attributes ? $detail->attributes->sum('total') : 0;
        });

        $booking->tagihanAktif = $booking->details->where(self::COL_STATUS, '!=', BookingDetailStatus::CANCELLED->value)->sum('price') + $totalAttributesPrice;
        $booking->uangMasuk = $booking->payments->where(self::COL_STATUS, PaymentStatus::SUCCESS->value)->where(self::COL_PAYMENT_TYPE, '!=', PaymentType::REFUND->value)->sum('amount');
        $booking->uangRefund = $booking->payments->where(self::COL_STATUS, PaymentStatus::SUCCESS->value)->where(self::COL_PAYMENT_TYPE, PaymentType::REFUND->value)->sum('amount');

        $uangNet = $booking->getAttributes()['uangMasuk'] ?? ($booking->uangMasuk - $booking->uangRefund);
        $booking->sisaTagihan = max(0, $booking->tagihanAktif - $uangNet);
        $booking->badgeClass = $this->getBadgeClass($booking->overallStatus);

        return $booking;
    }

    private function getBadgeClass(string $status): string
    {
        $statusColors = [
            'success'                 => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'pending'                 => 'bg-amber-50 text-amber-700 border-amber-200',
            'failed'                  => 'bg-rose-50 text-rose-700 border-rose-200',
            'expired'                 => 'bg-rose-50 text-rose-700 border-rose-200',
            'booked'                  => 'bg-blue-50 text-blue-700 border-blue-200',
            'active'                  => 'bg-blue-50 text-blue-700 border-blue-200',
            'reschedule'              => 'bg-amber-50 text-amber-800 border-amber-200',
            'cancelled'               => 'bg-red-50 text-red-700 border-red-200',
            'field closure'           => 'bg-red-50 text-red-800 border-red-200',
            'closed field cancelled'  => 'bg-red-50 text-red-900 border-red-200',
            'closed field reschedule' => 'bg-amber-50 text-amber-900 border-amber-200',
        ];

        return $statusColors[$status] ?? 'bg-gray-50 text-gray-700 border-gray-200';
    }
}
