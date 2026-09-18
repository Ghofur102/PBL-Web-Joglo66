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

        if (!empty($filters['search'])) {
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

        if (!empty($filters['date'])) {
            $query->whereDate('booking_date', $filters['date']);
        }

        if (!empty($filters['status'])) {
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

        $field = $booking->field;
        $minCancelDays = (int) ($field->min_cancel_days ?? 3);
        $minRescheduleDays = (int) ($field->min_reschedule_days ?? 3);
        $maxRescheduleTimes = (int) ($field->max_reschedule_times ?? 1);

        $globalPaidPerSession = $this->calculateGlobalPaidPerSession($booking);

        $booking->details->transform(function ($detail) use ($globalPaidPerSession, $minCancelDays, $minRescheduleDays, $maxRescheduleTimes) {
            return $this->hydrateDetailItem($detail, $globalPaidPerSession, $minCancelDays, $minRescheduleDays, $maxRescheduleTimes);
        });

        return $booking;
    }

    private function calculateGlobalPaidPerSession(Booking $booking): int
    {
        $totalSessions = max(1, $booking->details->count());
        $allSuccessfulPayments = $booking->payments->where('status', PaymentStatus::SUCCESS->value);

        return (int) round(
            $allSuccessfulPayments->whereNull('fk_booking_detail_id')
                ->whereIn(self::COL_PAYMENT_TYPE, [
                    PaymentType::DOWN_PAYMENT->value,
                    PaymentType::FINAL_PAYMENT->value,
                ])
                ->sum('amount') / $totalSessions
        );
    }

    private function hydrateDetailItem(
        BookingDetail $detail,
        int $globalPaidPerSession,
        int $minCancelDays,
        int $minRescheduleDays,
        int $maxRescheduleTimes
    ): BookingDetail {
        $detailPayments = $this->resolveDetailPayments($detail);
        $latestPayment = $detailPayments->sortByDesc(self::COL_CREATED_AT)->first();

        $this->hydrateDetailFinancials($detail, $detailPayments, $globalPaidPerSession);
        $calculatedOldPrice = $this->hydrateReschedulesHistory($detail, $detailPayments);

        $detail->initialPrice = $calculatedOldPrice;

        $this->hydrateDetailStatusesAndPermissions(
            $detail,
            $latestPayment,
            $minCancelDays,
            $minRescheduleDays,
            $maxRescheduleTimes
        );

        return $detail;
    }

    private function resolveDetailPayments(BookingDetail $detail): Collection
    {
        if ($detail->payment instanceof Collection) {
            return $detail->payment;
        }

        if ($detail->payment !== null) {
            return collect([$detail->payment]);
        }

        return collect();
    }

    private function hydrateDetailFinancials(BookingDetail $detail, Collection $detailPayments, int $globalPaidPerSession): void
    {
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
    }

    private function hydrateReschedulesHistory(BookingDetail $detail, Collection $detailPayments): int
    {
        $currentDetailPrice = (int) $detail->price;
        $calculatedOldPrice = $currentDetailPrice;

        if (!$detail->reschedules || $detail->reschedules->isEmpty()) {
            return $calculatedOldPrice;
        }

        foreach ($detail->reschedules as $rsc) {
            $rscPayment = $detailPayments->first(function ($p) {
                return in_array($p->payment_type, [PaymentType::RESCHEDULE_FEE->value, PaymentType::REFUND->value], true)
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

        return $calculatedOldPrice;
    }

    private function hydrateDetailStatusesAndPermissions(
        BookingDetail $detail,
        ?Payment $latestPayment,
        int $minCancelDays,
        int $minRescheduleDays,
        int $maxRescheduleTimes
    ): void {
        $cancellation = $detail->cancellation;
        $hasPendingCancellation = $cancellation && $cancellation->approval_status === 'pending';
        $isApprovedCancellation = $cancellation && $cancellation->approval_status === 'approved';
        $isRejectedCancellation = $cancellation && $cancellation->approval_status === 'rejected';
        $isCancelled = $isApprovedCancellation || ($detail->status === BookingDetailStatus::CANCELLED->value);

        $reschedules = $detail->reschedules ?? collect();
        $pendingReschedule = $reschedules->firstWhere('approval_status', 'pending');
        $hasPendingReschedule = $pendingReschedule !== null;
        $approvedReschedulesCount = $reschedules->where('approval_status', 'approved')->count();

        $detail->hasPendingCancellation = $hasPendingCancellation;
        $detail->isApprovedCancellation = $isApprovedCancellation;
        $detail->isRejectedCancellation = $isRejectedCancellation;
        $detail->isCancelled = $isCancelled;
        $detail->hasPendingReschedule = $hasPendingReschedule;
        $detail->pendingReschedule = $pendingReschedule;

        $detail->detailStatus = $this->determineDetailStatus(
            $detail,
            $latestPayment,
            $isCancelled,
            $hasPendingCancellation,
            $hasPendingReschedule
        );

        $detail->detailBadge = $this->getBadgeClass($detail->detailStatus);

        $playDate = Carbon::parse($detail->play_date)->startOfDay();
        $daysUntilPlay = now()->startOfDay()->diffInDays($playDate, false);
        $isPaid = in_array(strtolower($detail->status), ['active', 'booked', 'reschedule', 'success'], true);

        $canPerformAction = !$isCancelled && !$hasPendingCancellation && !$hasPendingReschedule && $isPaid;

        $detail->canCancel = $canPerformAction && ($daysUntilPlay >= $minCancelDays);
        $detail->canReschedule = $canPerformAction && ($daysUntilPlay >= $minRescheduleDays) && ($approvedReschedulesCount < $maxRescheduleTimes);
    }

    private function determineDetailStatus(
        BookingDetail $detail,
        ?Payment $latestPayment,
        bool $isCancelled,
        bool $hasPendingCancellation,
        bool $hasPendingReschedule
    ): string {
        if ($isCancelled) {
            return 'cancelled';
        }

        if ($hasPendingCancellation) {
            return 'menunggu pembatalan';
        }

        if ($hasPendingReschedule) {
            return 'menunggu reschedule';
        }

        return strtolower($detail->status ?? $latestPayment->status ?? PaymentStatus::PENDING->value);
    }

    private function appendFinancialSummary(Booking $booking): Booking
    {
        $totalDetails = $booking->details->count();
        $cancelledDetails = $booking->details->filter(function ($d) {
            return $d->status === BookingDetailStatus::CANCELLED->value
                || ($d->cancellation && $d->cancellation->approval_status === 'approved');
        })->count();

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

        $booking->tagihanAktif = $booking->details->filter(function ($d) {
            return $d->status !== BookingDetailStatus::CANCELLED->value
                && !($d->cancellation && $d->cancellation->approval_status === 'approved');
        })->sum('price') + $totalAttributesPrice;

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
            'menunggu pembatalan'     => 'bg-amber-50 text-amber-800 border-amber-300',
            'menunggu reschedule'     => 'bg-amber-50 text-amber-800 border-amber-300',
            'cancelled'               => 'bg-red-50 text-red-700 border-red-200',
            'field closure'           => 'bg-red-50 text-red-800 border-red-200',
            'closed field cancelled'  => 'bg-red-50 text-red-900 border-red-200',
            'closed field reschedule' => 'bg-amber-50 text-amber-900 border-amber-200',
        ];

        return $statusColors[$status] ?? 'bg-gray-50 text-gray-700 border-gray-200';
    }
}
