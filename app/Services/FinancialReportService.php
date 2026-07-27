<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\EmployeeSalary;
use App\Models\Payment;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Carbon\Carbon;

class FinancialReportService
{
    private string $formatDate = "Y-m-d H:i:s";
    private const MONTH_MAP = [
        1 => 'january', 2 => 'february', 3 => 'march', 4 => 'april',
        5 => 'may', 6 => 'june', 7 => 'july', 8 => 'august',
        9 => 'september', 10 => 'october', 11 => 'november', 12 => 'december',
    ];

    public function getMonthlyData(int $bulan, int $tahun): array
    {
        $monthEnum = self::MONTH_MAP[$bulan];
        $startDate = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $endDate   = Carbon::create($tahun, $bulan, 1)->endOfMonth();

        $payments = Payment::whereBetween('paid_at', [$startDate, $endDate])
            ->where('status', PaymentStatus::SUCCESS->value)
            ->with(['booking.field'])
            ->get();

        $totalDP         = $payments->filter(fn($p) => strtolower(trim($p->payment_type)) === PaymentType::DOWN_PAYMENT->value)->sum('amount');
        $totalPelunasan  = $payments->filter(fn($p) => strtolower(trim($p->payment_type)) === PaymentType::FINAL_PAYMENT->value)->sum('amount');
        $totalReschedule = $payments->filter(fn($p) => strtolower(trim($p->payment_type)) === PaymentType::RESCHEDULE_FEE->value)->sum('amount');
        $totalDPHangus   = $payments->filter(fn($p) => strtolower(trim($p->payment_type)) === 'dp hangus')->sum('amount');
        $totalAtribut    = $payments->filter(fn($p) => in_array(strtolower(trim($p->payment_type)), ['attribute rental', 'attribute']))->sum('amount');
        $totalRefund     = $payments->filter(fn($p) => strtolower(trim($p->payment_type)) === PaymentType::REFUND->value)->sum('amount');

        $grossIncome = $totalDP + $totalPelunasan + $totalReschedule + $totalDPHangus + $totalAtribut;
        $netIncome   = $grossIncome - $totalRefund;

        $salaries = EmployeeSalary::where('period_month', $monthEnum)
            ->where('period_year', $tahun)
            ->get();

        $totalGaji = $salaries->sum(fn ($s) => $s->amount_paid + $s->bonus - $s->deduction);
        $salaryExpenseIds = $salaries->pluck('fk_expense_id')->filter()->toArray();

        $expenses = Expense::whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when(!empty($salaryExpenseIds), fn ($query) => $query->whereNotIn('id', $salaryExpenseIds))
            ->get();

        $totalOperasional = $expenses->sum('amount');
        $totalPengeluaran = $totalOperasional + $totalGaji;

        $paymentDetails = $payments->map(function ($p) {
            $typeStr = strtolower(trim($p->payment_type));
            $isRefund = $typeStr === PaymentType::REFUND->value;
            $fieldName = $p->booking->field->name ?? 'Lapangan';

            return [
                'id'           => 'pay_' . $p->id,
                'date'         => Carbon::parse($p->paid_at)->format($this->formatDate),
                'type'         => $isRefund ? 'refund' : 'income',
                'payment_type' => $p->payment_type,
                'category'     => $p->payment_type,
                'title'        => $isRefund ? 'Pengembalian Dana (Refund)' : 'Pembayaran ' . ucwords(str_replace('_', ' ', $p->payment_type)),
                'description'  => 'Penyewaan Lapangan',
                'field_name'   => $fieldName,
                'method'       => strtoupper($p->method ?? 'CASH'),
                'amount'       => (int)$p->amount,
            ];
        });

        $expenseDetails = $expenses->map(fn($e) => [
            'id'           => 'exp_' . $e->id,
            'date'         => Carbon::parse($e->expense_date)->format($this->formatDate),
            'type'         => 'expense',
            'payment_type' => 'operational_expense',
            'category'     => $e->category,
            'title'        => $e->name ?? ('Pengeluaran ' . $e->category),
            'description'  => 'Pengeluaran Lapangan (' . $e->category . ')',
            'field_name'   => 'Operasional',
            'method'       => 'CASH',
            'amount'       => (int)$e->amount,
        ]);

        $salaryDetails = $salaries->map(function($s) use ($tahun, $bulan) {
            $dateObj = $s->payment_date ? Carbon::parse($s->payment_date) : Carbon::create($tahun, $bulan, date('t', strtotime("$tahun-$bulan-01")));
            return [
                'id'           => 'sal_' . $s->id,
                'date'         => $dateObj->format($this->formatDate),
                'type'         => 'expense',
                'payment_type' => 'salary',
                'category'     => 'Gaji',
                'title'        => 'Pembayaran Gaji Karyawan',
                'description'  => 'Gaji Karyawan Periode ' . $s->period_month,
                'field_name'   => 'Gaji',
                'method'       => 'TRANSFER',
                'amount'       => (int)($s->amount_paid + $s->bonus - $s->deduction),
            ];
        });

        $allTransactions = $paymentDetails
            ->concat($expenseDetails)
            ->concat($salaryDetails)
            ->sortByDesc('date')
            ->values()
            ->all();

        return [
            'summary' => [
                'gross_income'  => (int)$grossIncome,
                'total_refund'  => (int)$totalRefund,
                'net_income'    => (int)$netIncome,
                'total_expense' => (int)$totalPengeluaran,
                'net_profit'    => (int)($netIncome - $totalPengeluaran),
            ],
            'transactions' => $allTransactions,
            'details' => [
                'income' => [
                    'down_payment'     => (int)$totalDP,
                    'final_payment'    => (int)$totalPelunasan,
                    'reschedule_fee'   => (int)$totalReschedule,
                    'forsaken_dp'      => (int)$totalDPHangus,
                    'attribute_rental' => (int)$totalAtribut,
                ],
                'expense' => [
                    'operational' => (int)$totalOperasional,
                    'salary'      => (int)$totalGaji,
                ],
            ],
        ];
    }
}
