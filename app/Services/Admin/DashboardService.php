<?php

namespace App\Services\Admin;

use App\Models\Payment;
use App\Models\BookingAttribute;
use App\Models\BookingDetail;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\BookingDetailStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function getDashboardData(array $fieldIds): array
    {
        $today = Carbon::today()->toDateString();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $paymentDateCol = Schema::hasColumn('payments', 'paid_at') ? 'paid_at' : 'created_at';
        $attributeDateCol = Schema::hasColumn('booking_attributes', 'transaction_date') ? 'transaction_date' : 'created_at';

        $todayPaymentQuery = Payment::query()
            ->where('status', PaymentStatus::SUCCESS->value)
            ->whereDate($paymentDateCol, $today);

        if (!empty($fieldIds)) {
            $todayPaymentQuery->whereHas('booking', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id', $fieldIds);
            });
        }

        $todayPayments = $todayPaymentQuery->get();
        $todayBookingIncome = $todayPayments->whereIn('payment_type', [
            PaymentType::DOWN_PAYMENT->value,
            PaymentType::FINAL_PAYMENT->value,
            PaymentType::RESCHEDULE_FEE->value,
        ])->sum('amount') - $todayPayments->where('payment_type', PaymentType::REFUND->value)->sum('amount');

        $todayAttributeQuery = BookingAttribute::query()
            ->whereDate($attributeDateCol, $today)
            ->whereNotIn('status', ['cancelled', 'batal', 'rejected']);

        if (!empty($fieldIds)) {
            $todayAttributeQuery->whereHas('attribute', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id', $fieldIds);
            });
        }

        $todayAttributeIncome = $todayAttributeQuery->sum('total');
        $todayIncome = $todayBookingIncome + $todayAttributeIncome;

        $monthlyPaymentQuery = Payment::query()
            ->where('status', PaymentStatus::SUCCESS->value)
            ->whereBetween($paymentDateCol, [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59']);

        if (!empty($fieldIds)) {
            $monthlyPaymentQuery->whereHas('booking', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id', $fieldIds);
            });
        }

        $monthlyPayments = $monthlyPaymentQuery->get();
        $monthlyBookingIncome = $monthlyPayments->whereIn('payment_type', [
            PaymentType::DOWN_PAYMENT->value,
            PaymentType::FINAL_PAYMENT->value,
            PaymentType::RESCHEDULE_FEE->value,
        ])->sum('amount') - $monthlyPayments->where('payment_type', PaymentType::REFUND->value)->sum('amount');

        $monthlyAttributeQuery = BookingAttribute::query()
            ->whereBetween($attributeDateCol, [$startOfMonth, $endOfMonth])
            ->whereNotIn('status', ['cancelled', 'batal', 'rejected']);

        if (!empty($fieldIds)) {
            $monthlyAttributeQuery->whereHas('attribute', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id', $fieldIds);
            });
        }

        $monthlyAttributeIncome = $monthlyAttributeQuery->sum('total');
        $monthlyIncome = $monthlyBookingIncome + $monthlyAttributeIncome;

        $todayScheduleQuery = BookingDetail::query()
            ->with(['booking.user', 'booking.field'])
            ->whereDate('play_date', $today)
            ->whereNotIn('status', [BookingDetailStatus::CANCELLED->value, BookingDetailStatus::CLOSED_FIELD_CANCELLED->value]);

        if (!empty($fieldIds)) {
            $todayScheduleQuery->whereHas('booking', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id', $fieldIds);
            });
        }

        $schedulesList = $todayScheduleQuery->get()->map(function ($detail) {
            return [
                'id'         => $detail->id,
                'team_name'  => $detail->booking->team_name ?? 'Tim',
                'field_name' => $detail->booking->field->name ?? 'Lapangan',
                'play_date'  => $detail->play_date,
                'start_time' => Carbon::parse($detail->start_play_time)->format('H:i'),
                'end_time'   => Carbon::parse($detail->end_play_time)->format('H:i'),
            ];
        })->toArray();

        return [
            'today_income'    => (int) max(0, $todayIncome),
            'monthly_income'  => (int) max(0, $monthlyIncome),
            'today_schedules' => count($schedulesList),
            'schedules'       => $schedulesList,
        ];
    }
}
