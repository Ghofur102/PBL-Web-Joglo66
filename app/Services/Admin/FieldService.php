<?php

namespace App\Services\Admin;

use App\Models\Field;
use App\Models\FieldPrice;
use App\Models\FieldClosure;
use App\Models\BookingDetail;
use App\Enums\GeneralStatus;
use App\Enums\BookingDetailStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FieldService
{
    private const TIME_FORMAT = 'H:i:s';

    public function getFieldList(array $fieldIds, ?string $search, int $limit): array
    {
        $query = Field::query();

        if (!empty($fieldIds)) {
            $query->whereIn('id', $fieldIds);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                /** @var \Illuminate\Database\Eloquent\Builder $q */
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('category', 'LIKE', "%{$search}%");
            });
        }

        return $query->limit($limit)->get()->map(function ($field) {
            /** @var Field $field */
            return [
                'id'       => $field->id,
                'name'     => $field->name,
                'category' => $field->category,
                'location' => $field->location ?? 'N/A',
                'price'    => $field->price ?? 0,
                'image'    => $field->image ?? null,
                'status'   => $field->status ?? GeneralStatus::ACTIVE->value
            ];
        })->toArray();
    }

    public function updateFieldAndPricing(Field $field, array $data, $request): Field
    {
        return DB::transaction(function () use ($field, $data, $request) {
            $fieldData = array_intersect_key($data, array_flip(['name', 'description', 'category']));

            $newImageUrl = $this->processImageUpdate($field, $request);
            if ($newImageUrl) {
                $fieldData['image_url'] = $newImageUrl;
            }

            if (!empty($fieldData)) {
                $field->update($fieldData);
            }

            if ($request->has('pricing_rules')) {
                $this->processPricingRulesUpdate($field, $request->pricing_rules);
            }

            return $field->fresh(['fieldPrices']);
        });
    }

    public function checkSlotAvailability(int $fieldId, string $date): array
    {
        $dayName = strtolower(Carbon::parse($date)->englishDayOfWeek);
        $fieldPrices = FieldPrice::where('fk_field_id', $fieldId)->where('day_type', $dayName)->get();

        $occupied = BookingDetail::whereHas('booking', function ($query) use ($fieldId) {
                /** @var \Illuminate\Database\Eloquent\Builder $query */
                $query->where('fk_field_id', $fieldId);
            })
            ->where('play_date', $date)
            ->whereNotIn('status', [
                BookingDetailStatus::CANCELLED->value,
                BookingDetailStatus::FIELD_CLOSURE->value,
                BookingDetailStatus::CLOSED_FIELD_CANCELLED->value,
                BookingDetailStatus::CLOSED_FIELD_RESCHEDULE->value
            ])
            ->get(['start_play_time', 'end_play_time']);

        $closures = $this->getFieldClosuresForDate($fieldId, $date);

        $availableSlots = [];

        foreach ($fieldPrices as $pricing) {
            /** @var FieldPrice $pricing */
            $start = Carbon::parse($pricing->start_time);
            $end = Carbon::parse($pricing->end_time);
            $current = $start->copy();

            while ($current < $end) {
                $slotStart = $current->format(self::TIME_FORMAT);
                $nextHour = $current->copy()->addHour();
                $slotEnd = $nextHour->format(self::TIME_FORMAT);

                if ($nextHour > $end) {
                    break;
                }

                $slotStartDateTime = $date . ' ' . $slotStart;
                $slotEndDateTime = $date . ' ' . $slotEnd;

                $isOccupiedByBooking = $this->isSlotOccupied($slotStart, $slotEnd, $occupied);
                $isClosedByClosure = $this->isSlotClosedByClosure($slotStartDateTime, $slotEndDateTime, $closures);

                $availableSlots[] = [
                    'start'        => $slotStart,
                    'end'          => $slotEnd,
                    'price'        => $pricing->price,
                    'is_available' => !$isOccupiedByBooking && !$isClosedByClosure,
                    'is_closed'    => $isClosedByClosure,
                ];

                $current->addHour();
            }
        }

        return [
            'field_id'              => $fieldId,
            'date'                  => $date,
            'total_available_slots' => count($availableSlots),
            'available_slots'       => $availableSlots,
        ];
    }

    public function executeFieldClosure(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $closure = FieldClosure::create([
                'fk_user_id'               => $userId,
                'fk_field_id'              => $data['fk_field_id'],
                'field_closure_start_time' => $data['field_closure_start_time'],
                'field_closure_end_time'   => $data['field_closure_end_time'],
                'reason'                   => $data['reason'],
            ]);

            BookingDetail::whereHas('booking', function ($query) use ($data) {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    $query->where('fk_field_id', $data['fk_field_id']);
                })
                ->whereRaw('TIMESTAMP(play_date, start_play_time) < ? && TIMESTAMP(play_date, end_play_time) > ?', [
                    $data['field_closure_end_time'],
                    $data['field_closure_start_time'],
                ])
                ->where('status', '!=', BookingDetailStatus::CANCELLED->value)
                ->update(['status' => BookingDetailStatus::FIELD_CLOSURE->value]);

            $affectedBookings = BookingDetail::whereHas('booking', function ($query) use ($data) {
                    /** @var \Illuminate\Database\Eloquent\Builder $query */
                    $query->where('fk_field_id', $data['fk_field_id']);
                })
                ->whereRaw('TIMESTAMP(play_date, start_play_time) < ? && TIMESTAMP(play_date, end_play_time) > ?', [
                    $data['field_closure_end_time'],
                    $data['field_closure_start_time'],
                ])
                ->where('status', BookingDetailStatus::FIELD_CLOSURE->value)
                ->with('booking.user')
                ->get();

            return [
                'closure'           => $closure,
                'affected_bookings' => $affectedBookings
            ];
        });
    }

    private function getFieldClosuresForDate(int $fieldId, string $date): \Illuminate\Support\Collection
    {
        $dayStart = Carbon::parse($date)->startOfDay()->format('Y-m-d H:i:s');
        $dayEnd = Carbon::parse($date)->endOfDay()->format('Y-m-d H:i:s');

        return DB::table('field_closures')
            ->where('fk_field_id', $fieldId)
            ->where('field_closure_start_time', '<', $dayEnd)
            ->where('field_closure_end_time', '>', $dayStart)
            ->get(['field_closure_start_time', 'field_closure_end_time']);
    }

    private function isSlotClosedByClosure(string $slotStartDateTime, string $slotEndDateTime, $closures): bool
    {
        foreach ($closures as $closure) {
            if ($closure->field_closure_start_time < $slotEndDateTime && $closure->field_closure_end_time > $slotStartDateTime) {
                return true;
            }
        }
        return false;
    }

    private function processImageUpdate(Field $field, $request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }

        $this->deleteOldFieldImage($field->image_url);

        $imagePath = $request->file('image')->store('fields', 'public');
        return 'storage/' . $imagePath;
    }

    private function deleteOldFieldImage(?string $imageUrl): void
    {
        if (empty($imageUrl)) {
            return;
        }

        $oldImagePath = str_replace('storage/', '', $imageUrl);
        if (Storage::disk('public')->exists($oldImagePath)) {
            Storage::disk('public')->delete($oldImagePath);
        }
    }

    private function processPricingRulesUpdate(Field $field, $pricingRules): void
    {
        $rules = is_string($pricingRules)
            ? json_decode($pricingRules, true)
            : $pricingRules;

        if (empty($rules)) {
            return;
        }

        if ($this->hasPricingOverlaps($rules)) {
            throw new HttpException(422, 'Terdapat jadwal harga yang bentrok pada hari yang sama.');
        }

        FieldPrice::where('fk_field_id', $field->id)->delete();

        foreach ($rules as $rule) {
            FieldPrice::create([
                'fk_field_id' => $field->id,
                'day_type'    => strtolower($rule['day_type']),
                'start_time'  => $rule['start_time'],
                'end_time'    => $rule['end_time'],
                'price'       => $rule['price'],
            ]);
        }
    }

    private function hasPricingOverlaps(array $rules): bool
    {
        $groupedByDay = collect($rules)->groupBy(fn($r) => strtolower($r['day_type']));

        foreach ($groupedByDay as $dayRules) {
            $sortedRules = collect($dayRules)->sortBy('start_time')->values()->all();
            $count = count($sortedRules);

            for ($i = 0; $i < $count - 1; $i++) {
                $currentEnd = $sortedRules[$i]['end_time'];
                $nextStart = $sortedRules[$i + 1]['start_time'];

                if ($currentEnd > $nextStart) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isSlotOccupied(string $slotStart, string $slotEnd, $occupied): bool
    {
        foreach ($occupied as $booking) {
            /** @var BookingDetail $booking */
            $cond1 = ($slotStart >= $booking->start_play_time && $slotStart < $booking->end_play_time);
            $cond2 = ($slotEnd > $booking->start_play_time && $slotEnd <= $booking->end_play_time);
            $cond3 = ($slotStart <= $booking->start_play_time && $slotEnd >= $booking->end_play_time);

            if ($cond1 || $cond2 || $cond3) {
                return true;
            }
        }
        return false;
    }
}
