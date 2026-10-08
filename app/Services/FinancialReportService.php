<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Expense;
use App\Models\BookingAttribute;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class FinancialReportService
{
    public function getMonthlyReport(int $month, int $year, array $filters = []): array
    {
        $fieldIds = $filters['field_ids'] ?? [];
        $startDate = $filters['start_date'] ?? Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        $reportType = $filters['report_type'] ?? 'all';

        $paymentDateCol = Schema::hasColumn('payments', 'paid_at') ? 'paid_at' : 'created_at';
        $expenseDateCol = Schema::hasColumn('expenses', 'expense_date') ? 'expense_date' : (Schema::hasColumn('expenses', 'date') ? 'date' : 'created_at');
        $attributeDateCol = Schema::hasColumn('booking_attributes', 'transaction_date') ? 'transaction_date' : 'created_at';

        $paymentQuery = Payment::query()
            ->where('status', PaymentStatus::SUCCESS->value)
            ->whereBetween($paymentDateCol, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if (!empty($fieldIds)) {
            $paymentQuery->whereHas('booking', function ($query) use ($fieldIds) {
                $query->whereIn('fk_field_id', $fieldIds);
            });
        }

        if ($reportType === 'rental') {
            $paymentQuery->whereIn('payment_type', [
                PaymentType::DOWN_PAYMENT->value,
                PaymentType::FINAL_PAYMENT->value,
                PaymentType::RESCHEDULE_FEE->value,
            ]);
        }

        if ($reportType === 'expense') {
            $paymentQuery->where('id', null);
        }

        $payments = $paymentQuery->get();

        $grossBookingIncome = $payments->whereIn('payment_type', [
            PaymentType::DOWN_PAYMENT->value,
            PaymentType::FINAL_PAYMENT->value,
            PaymentType::RESCHEDULE_FEE->value,
        ])->sum('amount');

        $totalRefund = $payments->where('payment_type', PaymentType::REFUND->value)->sum('amount');

        $attributeQuery = BookingAttribute::query()
            ->whereBetween($attributeDateCol, [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'batal', 'rejected']);

        if (!empty($fieldIds)) {
            $attributeQuery->whereHas('booking', function ($query) use ($fieldIds) {
                $query->whereIn('fk_field_id', $fieldIds);
            });
        }

        if ($reportType === 'rental') {
            $attributeQuery->where('id', null);
        }

        if ($reportType === 'expense') {
            $attributeQuery->where('id', null);
        }

        $attributes = $attributeQuery->get();
        $totalAttributeIncome = $attributes->sum('total');

        $expenseQuery = Expense::query()
            ->whereBetween($expenseDateCol, [$startDate, $endDate]);

        if (!empty($fieldIds)) {
            $expenseQuery->whereIn('fk_field_id', $fieldIds);
        }

        if ($reportType !== 'all' && $reportType !== 'expense' && $reportType !== 'field') {
            $expenseQuery->where('id', null);
        }

        $expenses = $expenseQuery->get();
        $totalExpense = $expenses->sum('amount');

        $grossIncome = $grossBookingIncome + $totalAttributeIncome;
        $netIncome = $grossIncome - $totalRefund;
        $netProfit = $netIncome - $totalExpense;

        $transactions = $this->buildTransactionList(
            $payments,
            $attributes,
            $expenses,
            $paymentDateCol,
            $attributeDateCol,
            $expenseDateCol,
            $reportType,
        );

        $summaryData = [
            'month' => $month,
            'year' => $year,
            'report_type' => $reportType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'gross_booking_income' => (int) $grossBookingIncome,
            'total_attribute_income' => (int) $totalAttributeIncome,
            'gross_income' => (int) $grossIncome,
            'total_refund' => (int) $totalRefund,
            'net_income' => (int) $netIncome,
            'total_expense' => (int) $totalExpense,
            'net_profit' => (int) $netProfit,
        ];

        return array_merge($summaryData, [
            'summary' => $summaryData,
            'transactions' => $transactions,
        ]);
    }

    private function buildTransactionList(
        $payments,
        $attributes,
        $expenses,
        string $pCol,
        string $aCol,
        string $eCol,
        string $reportType,
    ): array {
        $list = [];

        if (in_array($reportType, ['all', 'income', 'field', 'rental'], true)) {
            foreach ($payments as $payment) {
                $isRefund = $payment->payment_type === PaymentType::REFUND->value;

                if ($reportType === 'rental' && $isRefund) {
                    continue;
                }

                $list[] = [
                    'id' => 'PAY-' . $payment->id,
                    'title' => $isRefund ? 'Pengembalian Dana (Refund)' : 'Pembayaran Sewa Lapangan',
                    'description' => 'Ref: ' . ($payment->reference_id ?? '-'),
                    'amount' => (int) $payment->amount,
                    'type' => $isRefund ? 'refund' : 'income',
                    'date' => Carbon::parse($payment->{$pCol})->format('Y-m-d H:i'),
                    'field_name' => $payment->booking?->field?->name ?? null,
                ];
            }
        }

        if (in_array($reportType, ['all', 'income', 'field'], true)) {
            foreach ($attributes as $attribute) {
                $list[] = [
                    'id' => 'ATTR-' . $attribute->id,
                    'title' => 'Sewa Atribut: ' . ($attribute->customer_name ?? 'Pelanggan'),
                    'description' => 'Jumlah: ' . $attribute->quantity . ' pcs (' . ucfirst($attribute->status) . ')',
                    'amount' => (int) $attribute->total,
                    'type' => 'income',
                    'date' => Carbon::parse($attribute->{$aCol})->format('Y-m-d'),
                    'field_name' => $attribute->booking?->field?->name ?? null,
                ];
            }
        }

        if (in_array($reportType, ['all', 'expense', 'field'], true)) {
            foreach ($expenses as $expense) {
                $list[] = [
                    'id' => 'EXP-' . $expense->id,
                    'title' => 'Pengeluaran: ' . ($expense->category ?? 'Operasional'),
                    'description' => $expense->note ?? $expense->description ?? '-',
                    'amount' => (int) $expense->amount,
                    'type' => 'expense',
                    'date' => Carbon::parse($expense->{$eCol})->format('Y-m-d'),
                    'field_name' => $expense->field?->name ?? null,
                ];
            }
        }

        usort($list, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $list;
    }
}
