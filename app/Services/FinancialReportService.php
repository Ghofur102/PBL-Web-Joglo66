<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Expense;
use App\Models\BookingAttribute;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Carbon\Carbon;

class FinancialReportService
{
    protected string $paymentDateCol;
    protected string $expenseDateCol;
    protected string $attributeDateCol;

    public function __construct()
    {
        $this->paymentDateCol = 'paid_at';
        $this->expenseDateCol = 'expense_date';
        $this->attributeDateCol = 'transaction_date';
    }

    public function getMonthlyReport(int $month, int $year, array $fieldIds = []): array
    {
        $startDateTime = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateTimeString();
        $endDateTime = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay()->toDateTimeString();

        $paymentQuery = Payment::query()
            ->where('status', PaymentStatus::SUCCESS->value)
            ->whereBetween($this->paymentDateCol, [$startDateTime, $endDateTime]);

        if (!empty($fieldIds)) {
            $paymentQuery->whereHas('booking', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id', $fieldIds);
            });
        }

        $grossBookingIncome = (clone $paymentQuery)
            ->whereIn('payment_type', [
                PaymentType::DOWN_PAYMENT->value,
                PaymentType::FINAL_PAYMENT->value,
                PaymentType::RESCHEDULE_FEE->value,
            ])->sum('amount');

        $totalRefund = (clone $paymentQuery)
            ->where('payment_type', PaymentType::REFUND->value)
            ->sum('amount');

        $attributeQuery = BookingAttribute::query()
            ->whereBetween($this->attributeDateCol, [$startDateTime, $endDateTime])
            ->whereNotIn('status', ['cancelled', 'batal', 'rejected']);

        if (!empty($fieldIds)) {
            $attributeQuery->whereHas('attribute', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id', $fieldIds);
            });
        }

        $totalAttributeIncome = (clone $attributeQuery)->sum('total');

        $expenseQuery = Expense::query()
            ->whereBetween($this->expenseDateCol, [$startDateTime, $endDateTime]);

        if (!empty($fieldIds)) {
            $expenseQuery->whereIn('fk_field_id', $fieldIds);
        }

        $totalExpense = (clone $expenseQuery)
            ->selectRaw('SUM(quantity * unit_price) as total')
            ->value('total') ?? 0;

        $grossIncome = $grossBookingIncome + $totalAttributeIncome;
        $netIncome = $grossIncome - $totalRefund;
        $netProfit = $netIncome - $totalExpense;

        $paymentsData = $paymentQuery->get();
        $attributesData = $attributeQuery->get();
        $expensesData = $expenseQuery->get();

        $transactions = $this->buildTransactionList($paymentsData, $attributesData, $expensesData);

        $summaryData = [
            'month'                  => $month,
            'year'                   => $year,
            'gross_booking_income'   => (int) $grossBookingIncome,
            'total_attribute_income' => (int) $totalAttributeIncome,
            'gross_income'           => (int) $grossIncome,
            'total_refund'           => (int) $totalRefund,
            'net_income'             => (int) $netIncome,
            'total_expense'          => (int) $totalExpense,
            'net_profit'             => (int) $netProfit,
        ];

        return array_merge($summaryData, [
            'summary'      => $summaryData,
            'transactions' => $transactions,
        ]);
    }

    protected function buildTransactionList($payments, $attributes, $expenses): array
    {
        $mappedPayments = $payments->map(function ($item) {
            return [
                'id'               => $item->id,
                'transaction_date' => $item->{$this->paymentDateCol},
                'amount'           => $item->amount,
                'transaction_type' => 'payment',
                'description'      => $item->payment_type,
            ];
        });

        $mappedAttributes = $attributes->map(function ($item) {
            return [
                'id'               => $item->id,
                'transaction_date' => $item->{$this->attributeDateCol},
                'amount'           => $item->total,
                'transaction_type' => 'attribute',
                'description'      => $item->status,
            ];
        });

        $mappedExpenses = $expenses->map(function ($item) {
            return [
                'id'               => $item->id,
                'transaction_date' => $item->{$this->expenseDateCol},
                'amount'           => $item->quantity * $item->unit_price,
                'transaction_type' => 'expense',
                'description'      => $item->note,
            ];
        });

        return $mappedPayments
            ->concat($mappedAttributes)
            ->concat($mappedExpenses)
            ->sortByDesc('transaction_date')
            ->values()
            ->toArray();
    }
}
