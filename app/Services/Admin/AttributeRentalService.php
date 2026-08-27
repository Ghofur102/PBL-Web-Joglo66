<?php

namespace App\Services\Admin;

use App\Models\Attribute;
use App\Models\BookingAttribute;
use App\Models\BookingDetail;
use App\Enums\RentalStatus;
use App\Enums\GeneralStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AttributeRentalService
{
    public function getActiveBookings(array $fieldIds)
    {
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = BookingDetail::with(['booking.user', 'booking.field'])
            ->whereNotIn('status', ['cancelled', 'closed field cancelled', 'finish'])
            ->whereDate('play_date', '>=', Carbon::now()->toDateString());

        if (!empty($fieldIds)) {
            $query->whereHas('booking', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id', $fieldIds);
            });
        }

        return $query->orderBy('play_date')->orderBy('start_play_time')->get()->map(function ($detail) {
            return [
                'detail_id'      => $detail->id,
                'booking_id'     => $detail->fk_booking_id,
                'field_id'       => $detail->booking->fk_field_id,
                'field_name'     => $detail->booking->field->name ?? 'Unknown',
                'team_name'      => $detail->booking->team_name ?? 'Unknown',
                'customer_name'  => $detail->booking->user->name ?? 'Guest',
                'customer_phone' => $detail->booking->customer_phone ?? '',
                'play_date'      => Carbon::parse($detail->play_date)->format('Y-m-d'),
                'start_time'     => Carbon::parse($detail->start_play_time)->format('H:i'),
                'end_time'       => Carbon::parse($detail->end_play_time)->format('H:i'),
            ];
        });
    }

    public function executeRental(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $createdRentals = [];
            $formattedTransactionDate = Carbon::parse($data['transaction_date'])->format('Y-m-d');

            foreach ($data['items'] as $item) {
                $attribute = Attribute::where('id', $item['fk_attribute_id'])->lockForUpdate()->firstOrFail();

                if ($attribute->status === GeneralStatus::INACTIVE->value) {
                    throw new UnprocessableEntityHttpException("Atribut {$attribute->name} sedang tidak aktif.");
                }

                if ($attribute->stock < $item['quantity']) {
                    throw new UnprocessableEntityHttpException("Stok tidak mencukupi. Sisa stok {$attribute->name}: {$attribute->stock}");
                }

                $totalPrice = $attribute->price_hour * $item['quantity'] * $data['duration_hours'];

                $rental = BookingAttribute::create([
                    'fk_booking_detail_id' => $data['fk_booking_detail_id'],
                    'fk_attribute_id'      => $item['fk_attribute_id'],
                    'quantity'             => $item['quantity'],
                    'price'                => $attribute->price_hour,
                    'total'                => $totalPrice,
                    'transaction_date'     => $formattedTransactionDate,
                    'status'               => RentalStatus::BORROWED->value,
                    'customer_name'        => $data['customer_name'],
                    'customer_phone'       => $data['customer_phone'] ?? null,
                    'duration_hours'       => $data['duration_hours'],
                ]);

                $attribute->decrement('stock', $item['quantity']);

                $rental->load('attribute:id,name,type');
                $createdRentals[] = $rental;
            }

            return [
                'items'            => $createdRentals,
                'total_price'      => collect($createdRentals)->sum(fn($rental) => $rental->total),
                'customer_name'    => $data['customer_name'],
                'transaction_date' => $formattedTransactionDate,
            ];
        });
    }

    public function processReturn(BookingAttribute $rental): BookingAttribute
    {
        if ($rental->status === RentalStatus::RETURNED->value) {
            throw new UnprocessableEntityHttpException('Atribut ini sudah dikembalikan.');
        }

        DB::transaction(function () use ($rental) {
            $rental->update(['status' => RentalStatus::RETURNED->value]);

            $attribute = Attribute::find($rental->fk_attribute_id);
            if ($attribute) {
                $attribute->increment('stock', $rental->quantity);
            }
        });

        return $rental->fresh()->load('attribute:id,name,type');
    }

    public function getHistory(array $fieldIds, array $filters)
    {
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = BookingAttribute::with(['attribute:id,name,type,fk_field_id', 'bookingDetail.booking']);

        if (!empty($fieldIds)) {
            $query->whereHas('attribute', function ($q) use ($fieldIds) {
                $q->whereIn('fk_field_id', $fieldIds);
            });
        }

        if (!empty($filters['search'])) {
            $query->where('customer_name', 'LIKE', "%{$filters['search']}%");
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($filters['limit'] ?? 50);
    }
}
