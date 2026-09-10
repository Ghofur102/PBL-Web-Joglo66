<?php

namespace App\Services\Admin;

use App\Models\BookingDetail;
use App\Models\FieldPrice;
use App\Enums\BookingDetailStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingExtensionService
{
    public function extendSessionTime(int $detailId, int $extendMinutes = 30): array
    {
        return DB::transaction(function () use ($detailId, $extendMinutes) {
            $detail = BookingDetail::with('booking')->findOrFail($detailId);

            if (in_array($detail->status, [
                BookingDetailStatus::CANCELLED->value,
                BookingDetailStatus::FIELD_CLOSURE->value,
                BookingDetailStatus::CLOSED_FIELD_CANCELLED->value
            ], true)) {
                throw new HttpException(400, 'Sesi jadwal yang sudah dibatalkan/ditutup tidak dapat diperpanjang.');
            }

            $currentStart = Carbon::parse($detail->start_play_time);
            $currentEnd = Carbon::parse($detail->end_play_time);
            $newEnd = $currentEnd->copy()->addMinutes($extendMinutes);

            $playDate = $detail->play_date;
            $fieldId = $detail->booking->fk_field_id;

            $hasConflict = BookingDetail::query()
                ->whereHas('booking', fn($q) => $q->where('fk_field_id', $fieldId))
                ->where('play_date', $playDate)
                ->where('id', '!=', $detail->id)
                ->whereNotIn('status', [
                    BookingDetailStatus::CANCELLED->value,
                    BookingDetailStatus::FIELD_CLOSURE->value,
                    BookingDetailStatus::CLOSED_FIELD_CANCELLED->value
                ])
                ->where('start_play_time', '<', $newEnd->format('H:i:s'))
                ->where('end_play_time', '>', $currentEnd->format('H:i:s'))
                ->exists();

            if ($hasConflict) {
                throw new HttpException(422, 'Gagal memperpanjang. Slot waktu berikutnya sudah dipesan oleh tim lain.');
            }

            $newEndDateTime = $playDate . ' ' . $newEnd->format('H:i:s');
            $currentEndDateTime = $playDate . ' ' . $currentEnd->format('H:i:s');

            $hasClosureConflict = DB::table('field_closures')
                ->where('fk_field_id', $fieldId)
                ->where('field_closure_start_time', '<', $newEndDateTime)
                ->where('field_closure_end_time', '>', $currentEndDateTime)
                ->exists();

            if ($hasClosureConflict) {
                throw new HttpException(422, 'Gagal memperpanjang. Lapangan dijadwalkan tutup pada jam tersebut.');
            }

            $dayName = strtolower(Carbon::parse($playDate)->englishDayOfWeek);
            $hourlyRate = FieldPrice::where('fk_field_id', $fieldId)
                ->where('day_type', $dayName)
                ->where('start_time', '<=', $currentStart->format('H:i:s'))
                ->where('end_time', '>=', $currentEnd->format('H:i:s'))
                ->value('price');

            if (!$hourlyRate) {
                $hourlyRate = $detail->price; // Fallback ke harga per jam di booking detail jika aturan tidak ditemukan
            }

            $additionalPrice = (int) round(($hourlyRate / 60) * $extendMinutes);
            $newTotalPrice = $detail->price + $additionalPrice;

            $detail->update([
                'end_play_time' => $newEnd->format('H:i:s'),
                'price'         => $newTotalPrice,
            ]);

            Cache::forget("field_slots_{$fieldId}_{$playDate}");

            return [
                'detail_id'        => $detail->id,
                'old_end_time'     => $currentEnd->format('H:i'),
                'new_end_time'     => $newEnd->format('H:i'),
                'extend_minutes'   => $extendMinutes,
                'additional_price' => $additionalPrice,
                'total_session_price' => $newTotalPrice,
            ];
        });
    }
}
