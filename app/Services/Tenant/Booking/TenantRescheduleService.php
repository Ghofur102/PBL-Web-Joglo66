<?php

namespace App\Services\Tenant\Booking;

use App\Enums\BookingDetailStatus;
use App\Models\BookingCancelled;
use App\Models\BookingDetail;
use App\Models\BookingReschedule;
use App\Models\FieldPrice;
use App\Models\FieldWorker;
use App\Models\User;
use App\Notifications\GeneralBookingNotification;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use UnexpectedValueException;

class TenantRescheduleService
{
    private const DB_CONN = 'mysql_joglo66_app';

    public function getFormPreparationData(BookingDetail $detail, array $params): array
    {
        $selectedDate = $params['date'] ?? date('Y-m-d');
        $month = (int) ($params['month'] ?? date('m'));
        $year = (int) ($params['year'] ?? date('Y'));

        $calendar = $this->generateCalendar($month, $year);
        $prevMonth = Carbon::create($year, $month, 1)->subMonth();
        $nextMonth = Carbon::create($year, $month, 1)->addMonth();

        $slots = $this->getSlotsForDate((int) $detail->booking->fk_field_id, $selectedDate, $detail);

        return compact('calendar', 'month', 'year', 'selectedDate', 'prevMonth', 'nextMonth', 'slots');
    }

    public function validateAndPrepareReview(BookingDetail $detail, array $newSlot): array
    {
        $this->checkRescheduleRules($detail);
        $this->checkSlotConflict($detail, $newSlot);

        $newPrice = $this->getNewPrice($detail, $newSlot);
        $oldPrice = (int) $detail->price;
        $priceDiff = $newPrice - $oldPrice;

        return compact('newPrice', 'oldPrice', 'priceDiff');
    }

    public function executeReschedule(BookingDetail $detail, array $validated): void
    {
        $existingPendingReschedule = BookingReschedule::where('fk_booking_detail_id', $detail->id)
            ->where('approval_status', 'pending')
            ->exists();

        if ($existingPendingReschedule) {
            throw new DomainException('Pengajuan reschedule untuk booking ini sedang menunggu persetujuan admin.');
        }

        $existingPendingCancel = BookingCancelled::where('fk_booking_detail_id', $detail->id)
            ->where('approval_status', 'pending')
            ->exists();

        if ($existingPendingCancel) {
            throw new DomainException('Tidak dapat mengajukan reschedule karena sesi ini sedang menunggu persetujuan pembatalan.');
        }

        $review = $this->validateAndPrepareReview($detail, $validated);
        $tenantUser = Auth::user();
        $field = $detail->booking->field;

        DB::connection(self::DB_CONN)->transaction(function () use ($detail, $validated, $review, $tenantUser, $field) {
            BookingReschedule::create([
                'fk_booking_detail_id' => $detail->id,
                'old_date'             => $detail->play_date,
                'new_play_date'        => $validated['new_play_date'],
                'new_start_play_time'  => $validated['new_start_play_time'],
                'new_end_play_time'    => $validated['new_end_play_time'],
                'new_price'            => $review['newPrice'],
                'status_refund'        => $this->determineStatusRefund($review['priceDiff']),
                'reason'               => $validated['reason'],
                'approval_status'      => 'pending',
                'sender_by'            => 'tenant',
            ]);

            $workerUserIds = FieldWorker::query()
                ->where('fk_field_id', $field->id)
                ->pluck('fk_user_id');

            $workers = User::whereIn('id', $workerUserIds)->get();
            if ($workers->isNotEmpty()) {
                Notification::send($workers, new GeneralBookingNotification([
                    'title'             => 'Pengajuan Reschedule Jadwal',
                    'message'           => "Penyewa {$tenantUser->name} mengajukan pindah jadwal booking #{$detail->fk_booking_id} ke tanggal {$validated['new_play_date']} pukul {$validated['new_start_play_time']}.",
                    'type'              => 'reschedule_request',
                    'booking_id'        => $detail->fk_booking_id,
                    'booking_detail_id' => $detail->id,
                    'url'               => "/admin/detail-booking/{$detail->fk_booking_id}",
                    'sender_id'         => $tenantUser->id,
                    'sender_name'       => $tenantUser->name,
                    'sender_role'       => 'tenant',
                ]));
            }

            if ($field->fk_user_id) {
                $owner = User::find($field->fk_user_id);
                if ($owner) {
                    $owner->notify(new GeneralBookingNotification([
                        'title'             => 'Info Pengajuan Reschedule',
                        'message'           => "Penyewa {$tenantUser->name} mengajukan reschedule booking #{$detail->fk_booking_id} pada {$field->name}.",
                        'type'              => 'reschedule_info',
                        'booking_id'        => $detail->fk_booking_id,
                        'booking_detail_id' => $detail->id,
                        'url'               => "/owner/detail-booking/{$detail->fk_booking_id}",
                        'sender_id'         => $tenantUser->id,
                        'sender_name'       => $tenantUser->name,
                        'sender_role'       => 'tenant',
                    ]));
                }
            }
        });
    }

    public function checkRescheduleRules(BookingDetail $detail): void
    {
        $field = $detail->booking->field;
        $minRescheduleDays = (int) ($field->min_reschedule_days ?? 3);
        $maxRescheduleTimes = (int) ($field->max_reschedule_times ?? 1);

        $playDate = Carbon::parse($detail->play_date)->startOfDay();
        $daysUntilPlay = Carbon::now()->startOfDay()->diffInDays($playDate, false);

        if ($daysUntilPlay < $minRescheduleDays) {
            throw new UnexpectedValueException("Reschedule hanya bisa dilakukan minimal H-{$minRescheduleDays} sebelum jadwal bermain.");
        }

        if (strtolower($detail->status) === 'waiting') {
            throw new UnexpectedValueException('Fitur reschedule tidak tersedia. Silakan selesaikan pembayaran terlebih dahulu.');
        }

        $rescheduleCount = BookingReschedule::query()
            ->where('fk_booking_detail_id', $detail->id)
            ->where('approval_status', 'approved')
            ->count();

        if ($rescheduleCount >= $maxRescheduleTimes) {
            throw new UnexpectedValueException("Reschedule maksimal dapat dilakukan {$maxRescheduleTimes} kali.");
        }
    }

    private function checkSlotConflict(BookingDetail $detail, array $newSlot): void
    {
        $newStart = $newSlot['new_start_play_time'] . ':00';
        $newEnd = $newSlot['new_end_play_time'] . ':00';

        if ($newSlot['new_play_date'] === $detail->play_date && $newStart >= $detail->start_play_time && $newEnd <= $detail->end_play_time) {
            throw new UnexpectedValueException('Anda tidak bisa memilih waktu yang menjadi bagian dari jadwal Anda saat ini.');
        }

        $conflict = BookingDetail::query()
            ->whereHas('booking', fn ($q) => $q->where('fk_field_id', $detail->booking->fk_field_id))
            ->where('id', '!=', $detail->id)
            ->where('play_date', $newSlot['new_play_date'])
            ->whereNotIn('status', [BookingDetailStatus::CANCELLED->value, 'failed', 'expired'])
            ->where('start_play_time', '<', $newSlot['new_end_play_time'])
            ->where('end_play_time', '>', $newSlot['new_start_play_time'])
            ->exists();

        $isClosed = $this->isFieldClosedOnSlot($detail, $newSlot, $newStart, $newEnd);

        if ($conflict || $isClosed) {
            throw new UnexpectedValueException('Slot yang dipilih sudah dibooking atau lapangan sedang ditutup.');
        }
    }

    private function isFieldClosedOnSlot(BookingDetail $detail, array $newSlot, string $newStart, string $newEnd): bool
    {
        $isClosed = false;
        if (Schema::connection(self::DB_CONN)->hasTable('field_closures')) {
            $newStartDT = $newSlot['new_play_date'] . ' ' . $newStart;
            $newEndDT = $newSlot['new_play_date'] . ' ' . $newEnd;

            $isClosed = DB::connection(self::DB_CONN)->table('field_closures')
                ->where('fk_field_id', $detail->booking->fk_field_id)
                ->where(function ($query) use ($newStartDT, $newEndDT) {
                    /** @var Builder $query */
                    $query->where('field_closure_start_time', '<', $newEndDT)
                        ->where('field_closure_end_time', '>', $newStartDT);
                })->exists();
        }

        return $isClosed;
    }

    private function getNewPrice(BookingDetail $detail, array $newSlot): int
    {
        $dayName = strtolower(Carbon::parse($newSlot['new_play_date'])->englishDayOfWeek);
        $price = FieldPrice::query()
            ->where('fk_field_id', $detail->booking->fk_field_id)
            ->where('day_type', $dayName)
            ->where('start_time', '<=', $newSlot['new_start_play_time'])
            ->where('end_time', '>=', $newSlot['new_end_play_time'])
            ->value('price');

        if (!$price) {
            throw new UnexpectedValueException('Harga untuk jadwal baru tidak ditemukan.');
        }

        return (int) $price;
    }

    private function determineStatusRefund(int $priceDiff): string
    {
        if ($priceDiff > 0) {
            return 'deposit required';
        } elseif ($priceDiff < 0) {
            return 'refund required';
        }
        return 'none';
    }

    private function generateCalendar(int $month, int $year): array
    {
        $firstDay = Carbon::create($year, $month, 1);
        $start = $firstDay->copy()->startOfWeek(Carbon::SUNDAY);
        $end = $firstDay->copy()->lastOfMonth()->endOfWeek(Carbon::SATURDAY);

        $days = [];
        for ($current = $start->copy(); $current <= $end; $current->addDay()) {
            $days[] = [
                'date'           => $current->format('Y-m-d'),
                'day'            => (int) $current->format('j'),
                'isCurrentMonth' => $current->month === $month,
                'isToday'        => $current->isToday(),
                'isPast'         => $current->isPast() && !$current->isToday(),
            ];
        }

        return $days;
    }

    private function getSlotsForDate(int $fieldId, string $date, BookingDetail $originalDetail): array
    {
        $dayName = strtolower(Carbon::parse($date)->englishDayOfWeek);
        $priceRules = FieldPrice::query()
            ->where('fk_field_id', $fieldId)
            ->where('day_type', $dayName)
            ->orderBy('start_time')
            ->get();

        $occupied = BookingDetail::query()
            ->whereHas('booking', fn ($q) => $q->where('fk_field_id', $fieldId))
            ->where('id', '!=', $originalDetail->id)
            ->where('play_date', $date)
            ->whereNotIn('status', [BookingDetailStatus::CANCELLED->value, 'failed', 'expired'])
            ->get(['start_play_time', 'end_play_time']);

        $closures = $this->getClosuresForDate($fieldId, $date);

        return $this->buildHourlySlots($priceRules, $occupied, $closures, $date, $originalDetail);
    }

    private function getClosuresForDate(int $fieldId, string $date): array
    {
        $closures = [];
        if (Schema::connection(self::DB_CONN)->hasTable('field_closures')) {
            $closures = DB::connection(self::DB_CONN)->table('field_closures')
                ->where('fk_field_id', $fieldId)
                ->where('field_closure_start_time', '<=', $date . ' 23:59:59')
                ->where('field_closure_end_time', '>=', $date . ' 00:00:00')
                ->get()
                ->toArray();
        }

        return $closures;
    }

    private function buildHourlySlots($priceRules, $occupied, $closures, string $date, BookingDetail $originalDetail): array
    {
        $slots = [];
        foreach ($priceRules as $rule) {
            $start = Carbon::parse($rule->start_time);
            $end = Carbon::parse($rule->end_time);

            for ($current = $start->copy(); $current < $end; $current->addHour()) {
                $slotStartDB = $current->format('H:i:s');
                $slotEndDB = $current->copy()->addHour()->format('H:i:s');

                $isOccupiedByOther = $occupied->contains(fn ($b) => $slotStartDB < $b->end_play_time && $slotEndDB > $b->start_play_time);
                $isClosed = $this->checkSlotClosureConflict($closures, $date, $slotStartDB, $slotEndDB);
                $isOriginalSlot = ($date === $originalDetail->play_date) && ($slotStartDB >= $originalDetail->start_play_time && $slotEndDB <= $originalDetail->end_play_time);

                $slots[] = [
                    'start'        => $current->format('H:i'),
                    'end'          => $current->copy()->addHour()->format('H:i'),
                    'price'        => $rule->price,
                    'is_available' => !$isOccupiedByOther && !$isClosed && !$isOriginalSlot,
                    'is_original'  => $isOriginalSlot,
                    'is_closed'    => $isClosed,
                ];
            }
        }

        return $slots;
    }

    private function checkSlotClosureConflict(array $closures, string $date, string $start, string $end): bool
    {
        $isClosed = false;
        foreach ($closures as $closure) {
            if ($date . ' ' . $start < $closure->field_closure_end_time && $date . ' ' . $end > $closure->field_closure_start_time) {
                $isClosed = true;
                break;
            }
        }

        return $isClosed;
    }
}
