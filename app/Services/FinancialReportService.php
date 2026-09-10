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
    public function getMonthlyReport(int $month, int $year, array$fieldIds = []): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();$endDate = Carbon::createFromDate($year,$month, 1)->endOfMonth()->toDateString();

        $paymentDateCol = Schema::hasColumn('payments', 'paid_at') ? 'paid_at' : 'created_at';
        $expenseDateCol = Schema::hasColumn('expenses', 'expense_date') ? 'expense_date' : (Schema::hasColumn('expenses', 'date') ? 'date' : 'created_at');$attributeDateCol = Schema::hasColumn('booking_attributes', 'transaction_date') ? 'transaction_date' : 'created_at';

        $paymentQuery = Payment::query()
            ->where('status', PaymentStatus::SUCCESS->value)
            ->whereBetween($paymentDateCol, [$startDate . ' 00:00:00',$endDate . ' 23:59:59']);

        if (!empty($fieldIds)) {$paymentQuery->whereHas('booking', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id',$fieldIds);
            });
        }

        $payments =$paymentQuery->get();

        $grossBookingIncome =$payments->whereIn('payment_type', [
            PaymentType::DOWN_PAYMENT->value,
            PaymentType::FINAL_PAYMENT->value,
            PaymentType::RESCHEDULE_FEE->value,
        ])->sum('amount');

        $totalRefund =$payments->where('payment_type', PaymentType::REFUND->value)->sum('amount');

        $attributeQuery = BookingAttribute::query()
            ->whereBetween($attributeDateCol, [$startDate,$endDate])
            ->whereNotIn('status', ['cancelled', 'batal', 'rejected']);

        if (!empty($fieldIds)) {$attributeQuery->whereHas('attribute', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id',$fieldIds);
            });
        }

        $attributes =$attributeQuery->get();
        $totalAttributeIncome =$attributes->sum('total');

        $expenseQuery = Expense::query()
            ->whereBetween($expenseDateCol, [$startDate,$endDate]);

        if (!empty($fieldIds)) {
            $expenseQuery->whereIn('fk_field_id',$fieldIds);
        }

        $expenses =$expenseQuery->get();
        $totalExpense =$expenses->sum('amount');

        $grossIncome = $grossBookingIncome +$totalAttributeIncome;
        $netIncome = $grossIncome -$totalRefund;
        $netProfit = $netIncome -$totalExpense;

        $transactions =$this->buildTransactionList($payments,$attributes, $expenses,$paymentDateCol, $attributeDateCol,$expenseDateCol);

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

    private function buildTransactionList($payments,$attributes, $expenses,$pCol, $aCol,$eCol): array
    {
        $list = [];

        foreach ($payments as$p) {
            $isRefund =$p->payment_type === PaymentType::REFUND->value;
            $list[] = [
                'id'          => 'PAY-' . $p->id,
                'title'       => $isRefund ? 'Pengembalian Dana (Refund)' : 'Pembayaran Sewa Lapangan',
                'description' => 'Ref: ' . ($p->reference_id ?? '-'),
                'amount'      => (int) $p->amount,
                'type'        => $isRefund ? 'refund' : 'income',
                'date'        => Carbon::parse($p->{$pCol})->format('Y-m-d H:i'),
            ];
        }

        foreach ($attributes as $a) {$list[] = [
                'id'          => 'ATTR-' . $a->id,
                'title'       => 'Sewa Atribut: ' . ($a->customer_name ?? 'Pelanggan'),
                'description' => 'Jumlah: ' . $a->quantity . ' pcs (' . ucfirst($a->status) . ')',
                'amount'      => (int) $a->total,
                'type'        => 'income',
                'date'        => Carbon::parse($a->{$aCol})->format('Y-m-d'),
            ];
        }

        foreach ($expenses as $e) {$list[] = [
                'id'          => 'EXP-' . $e->id,
                'title'       => 'Pengeluaran: ' . ($e->category ?? 'Operasional'),
                'description' => $e->note ?? $e->description ?? '-',
                'amount'      => (int) $e->amount,
                'type'        => 'expense',
                'date'        => Carbon::parse($e->{$eCol})->format('Y-m-d'),
            ];
        }

        usort($list, fn($a,$b) => strcmp($b['date'],$a['date']));

        return $list;
    }
}
