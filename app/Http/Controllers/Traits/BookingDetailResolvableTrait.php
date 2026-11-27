<?php

namespace App\Http\Controllers\Traits;

use App\Models\BookingDetail;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait BookingDetailResolvableTrait
{
    private const UNAUTHORIZED_MESSAGE = 'Unauthorized field access.';

    private function resolveDetail(int|string $detailBookingId, ?object $user): BookingDetail
    {
        $detail = BookingDetail::query()->findOrFail($detailBookingId);

        if (! $this->checkFieldAccess($user, $detail->booking->fk_field_id)) {
            throw new HttpException(403, self::UNAUTHORIZED_MESSAGE);
        }

        return $detail;
    }
}
