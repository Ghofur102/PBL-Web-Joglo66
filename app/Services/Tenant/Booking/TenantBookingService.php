<?php

namespace App\Services\Tenant\Booking;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\FieldPrice;
use App\Models\Payment;
use App\Models\Field;
use App\Services\DuitkuService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use UnexpectedValueException;
use App\Models\CoreDmlModel;

class TenantBookingService
{
    private const TIME_FORMAT = 'H:i:s';

    private const DATE_FORMAT = 'Y-m-d';

    public function calculateAndGroupSlots(int $fieldId, array $slotsRaw): array
    {
        $totalPrice = 0;
        $groupedSlots = [];

        foreach ($slotsRaw as $item) {
            $playDate = $item['date'];
            $startTime = Carbon::parse($item['jam'])->format(self::TIME_FORMAT);
            $endTime = Carbon::parse($item['jam_akhir'])->format(self::TIME_FORMAT);
            $dayType = strtolower(Carbon::parse($playDate)->format('l'));

            $fieldPrice = FieldPrice::query()
                ->where('fk_field_id', $fieldId)
                ->where('day_type', $dayType)
                ->whereTime('start_time', '<=', $startTime)
                ->whereTime('end_time', '>=', $endTime)
                ->first();

            $price = $fieldPrice ? (int) $fieldPrice->price : 0;
            $totalPrice += $price;

            if (! isset($groupedSlots[$playDate])) {
                $groupedSlots[$playDate] = [];
            }

            $groupedSlots[$playDate][] = [
                'jam' => $item['jam'],
                'jam_akhir' => $item['jam_akhir'],
                'harga' => $price,
            ];
        }

        ksort($groupedSlots);

        return [
            'totalPrice' => $totalPrice,
            'groupedSlots' => $groupedSlots,
        ];
    }

    public function processBookingTransaction(int $userId, $user, array $validated, array $groupedSlots, DuitkuService $duitkuService): array
    {
        $totalPrice = 0;
        $booking = null;
        $processBooking = DB::connection(CoreDmlModel::DML_CONNECTION)->transaction(function () use ($userId, $user, $validated, $groupedSlots, &$totalPrice, &$booking) {
            $this->validateSlotsAvailability((int) $validated['field_id'], $groupedSlots);

            $booking = Booking::create([
                'fk_user_id' => $userId,
                'fk_field_id' => $validated['field_id'],
                'team_name' => $validated['team_name'],
                'customer_phone' => $user ? ($user->phone_number ?? $user->phone ?? '-') : '-',
                'customer_email' => $user ? $user->email : '-',
                'notes' => $validated['notes'] ?? '-',
                'booking_date' => now()->format(self::DATE_FORMAT),
            ]);

            foreach ($groupedSlots as $playDate => $slots) {
                foreach ($slots as $slot) {
                    $totalPrice += $slot['harga'];

                    BookingDetail::create([
                        'fk_booking_id' => $booking->id,
                        'start_play_time' => $slot['jam'],
                        'end_play_time' => $slot['jam_akhir'],
                        'play_date' => $playDate,
                        'price' => $slot['harga'],
                        'status' => BookingDetailStatus::WAITING->value,
                    ]);
                    Cache::forget("tenant_nearest_bookings_field_{$validated['field_id']}");
                    Cache::forget("tenant_slots_field_{$validated['field_id']}_{$playDate}");
                }
            }

            return [
                'booking' => $booking,
            ];
        });

        try {
            $amountToPay = $validated['payment_type'] === PaymentType::DOWN_PAYMENT->value ? ($totalPrice / 2) : $totalPrice;
            $duitkuResponse = $duitkuService->createInvoice($booking, $amountToPay);

            Payment::create([
                'fk_booking_id' => $booking->id,
                'fk_booking_detail_id' => null,
                'reference_id' => $duitkuResponse->reference,
                'payment_url' => $duitkuResponse->paymentUrl ?? '-',
                'payment_type' => $validated['payment_type'],
                'method' => 'transfer',
                'amount' => $amountToPay,
                'status' => PaymentStatus::PENDING->value,
            ]);
        } catch (\Exception $e) {
            $booking->details()->delete();
            $booking->delete();
            throw new UnexpectedValueException('Gagal membuat pembayaran: '.$e->getMessage());
        }

        return $processBooking;
    }

    public function getBookingSuccessData(int $bookingId, int $userId): Booking
    {
        $booking = Booking::query()
            ->with(['details', 'field'])
            ->findOrFail($bookingId);

        if ($booking->fk_user_id !== $userId) {
            throw new AccessDeniedHttpException('Anda tidak memiliki otorisasi untuk melihat nota pemesanan ini.');
        }

        return $booking;
    }

    private function validateSlotsAvailability(int $fieldId, array $groupedSlots): void
    {
        foreach ($groupedSlots as $playDate => $slots) {
            foreach ($slots as $slot) {
                $this->checkBookingConflict($fieldId, $playDate, $slot);
                $this->checkFieldClosureConflict($fieldId, $playDate, $slot);
            }
        }
    }

    private function checkBookingConflict(int $fieldId, string $playDate, array $slot): void
    {
        Field::where('id', $fieldId)->lockForUpdate()->first();
        $isBooked = BookingDetail::query()->whereHas('booking', function ($query) use ($fieldId) {
            /** @var Builder $query */
            $query->where('fk_field_id', $fieldId);
        })
            ->where('play_date', $playDate)
            ->whereNotIn('status', [BookingDetailStatus::CANCELLED->value, 'failed', 'expired'])
            ->where('start_play_time', '<', $slot['jam_akhir'])
            ->where('end_play_time', '>', $slot['jam'])
            ->exists();

        if ($isBooked) {
            throw new UnexpectedValueException("Slot {$slot['jam']} - {$slot['jam_akhir']} pada {$playDate} sudah dipesan orang lain.");
        }
    }

    private function checkFieldClosureConflict(int $fieldId, string $playDate, array $slot): void
    {
        if (Schema::connection(CoreDmlModel::DML_CONNECTION)->hasTable('field_closures')) {
            $slotStartDT = Carbon::parse($playDate.' '.$slot['jam']);
            $slotEndDT = Carbon::parse($playDate.' '.$slot['jam_akhir']);

            $isClosed = DB::connection(CoreDmlModel::DML_CONNECTION)->table('field_closures')
                ->where('fk_field_id', $fieldId)
                ->where(function ($query) use ($slotStartDT, $slotEndDT) {
                    /** @var \Illuminate\Database\Query\Builder $query */
                    $query->where('field_closure_start_time', '<', $slotEndDT)
                        ->where('field_closure_end_time', '>', $slotStartDT);
                })->exists();

            if ($isClosed) {
                throw new UnexpectedValueException("Lapangan sedang ditutup pada slot {$slot['jam']} - {$slot['jam_akhir']} di tanggal {$playDate}.");
            }
        }
    }
}
