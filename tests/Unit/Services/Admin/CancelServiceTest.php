<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\BookingDetailStatus;
use App\Models\Payment;
use App\Notifications\GeneralBookingNotification;
use App\Services\Admin\CancelService;
use DomainException;
use Illuminate\Support\Facades\Notification;
use Tests\Unit\Services\ServiceTestCase;
use Tests\Unit\Services\ServiceTestData;

class CancelServiceTest extends ServiceTestCase
{
    public function test_execute(): void
    {
        Notification::fake();
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $tenant = $this->createUser(
            ServiceTestData::ROLE_TENANT,
            ServiceTestData::EMAIL_TENANT_BUDI,
            ServiceTestData::CUSTOMER_BUDI,
        );

        foreach (
            [
                [ServiceTestData::CANCEL_REFUND_FULL, ServiceTestData::ONE, ServiceTestData::ONE],
                [ServiceTestData::CANCEL_REFUND_PARTIAL, ServiceTestData::ONE_THOUSAND, ServiceTestData::ONE],
                [ServiceTestData::CANCEL_REFUND_NONE, ServiceTestData::ONE_THOUSAND, ServiceTestData::ZERO],
                [ServiceTestData::CANCEL_REFUND_FULL, ServiceTestData::ZERO, ServiceTestData::ZERO],
            ] as [$statusRefund, $refundAmount, $expectedPaymentCount]
        ) {
            $detailForRefund = $this->createDetail($owner, $tenant);

            app(CancelService::class)->execute($detailForRefund, [
                'reason' => ServiceTestData::CANCEL_REASON,
                'status_refund' => $statusRefund,
                'refund_amount' => $refundAmount,
            ], $owner);

            $this->assertDatabaseHas('booking_cancelled', [
                'fk_booking_detail_id' => $detailForRefund->id,
                'reason' => ServiceTestData::CANCEL_REASON,
                'status_refund' => $statusRefund,
                'approval_status' => ServiceTestData::APPROVAL_APPROVED,
                'sender_by' => ServiceTestData::ROLE_ADMIN,
            ], self::DATABASE_CONNECTION_APP);

            $this->assertSame(
                $expectedPaymentCount,
                Payment::query()
                    ->where('fk_booking_detail_id', $detailForRefund->id)
                    ->where('payment_type', ServiceTestData::PAYMENT_TYPE_REFUND)
                    ->count(),
            );
            $this->assertSame(BookingDetailStatus::CANCELLED->value, $detailForRefund->fresh()->status);
        }

        Notification::assertSentTo($tenant, GeneralBookingNotification::class);
    }

    public function test_approve(): void
    {
        Notification::fake();
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $tenant = $this->createUser(
            ServiceTestData::ROLE_TENANT,
            ServiceTestData::EMAIL_TENANT_BUDI,
            ServiceTestData::CUSTOMER_BUDI,
        );
        $detail = $this->createDetail($owner, $tenant);
        $detail_cancellation = $this->createPendingCancellation($detail);
        $this->assertSame(ServiceTestData::APPROVAL_PENDING, $detail_cancellation->fresh()->approval_status);

        $pendingPayment = $this->createPendingPayment($detail);

        app(CancelService::class)->approve($detail, $owner, [
            'status_refund' => ServiceTestData::CANCEL_REFUND_PARTIAL,
            'refund_amount' => ServiceTestData::ONE_THOUSAND,
        ]);

        $this->assertSame(ServiceTestData::APPROVAL_APPROVED, $detail_cancellation->fresh()->approval_status);
        $this->assertSame(ServiceTestData::CANCEL_REFUND_PARTIAL, $detail_cancellation->fresh()->status_refund);
        $this->assertSame(BookingDetailStatus::CANCELLED->value, $detail->fresh()->status);
        $this->assertSame(ServiceTestData::PAYMENT_FAILED, $pendingPayment->fresh()->status);
        $this->assertSame(
            ServiceTestData::ONE,
            Payment::query()
                ->where('fk_booking_detail_id', $detail_cancellation->id)
                ->where('payment_type', ServiceTestData::PAYMENT_TYPE_REFUND)
                ->count(),
        );

        foreach (
            [
                [ServiceTestData::CANCEL_REFUND_FULL, ServiceTestData::ZERO, ServiceTestData::ZERO],
                [ServiceTestData::CANCEL_REFUND_FULL, ServiceTestData::ONE, ServiceTestData::ONE],
                [ServiceTestData::CANCEL_REFUND_NONE, ServiceTestData::ONE, ServiceTestData::ZERO],
            ] as [$statusRefund, $refundAmount, $expectedRefundCount]
        )
        {
            $detail = $this->createDetail($owner, $tenant);
            $detail_cancellation = $this->createPendingCancellation($detail);

            app(CancelService::class)->approve($detail, $owner, [
                'status_refund' => $statusRefund,
                'refund_amount' => $refundAmount,
            ]);

            $this->assertSame(ServiceTestData::APPROVAL_APPROVED, $detail_cancellation->fresh()->approval_status);
            $this->assertSame($statusRefund, $detail_cancellation->fresh()->status_refund);
            $this->assertSame(BookingDetailStatus::CANCELLED->value, $detail->fresh()->status);

            $this->assertSame(
                $expectedRefundCount,
                Payment::query()
                    ->where('fk_booking_detail_id', $detail_cancellation->id)
                    ->where('payment_type', ServiceTestData::PAYMENT_TYPE_REFUND)
                    ->count(),
            );
        }

        $detailWithoutCancellation = $this->createDetail($owner, $tenant);
        try {
            app(CancelService::class)->approve($detailWithoutCancellation, $owner);
            $this->fail('Approve seharusnya menolak detail tanpa pengajuan pending.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'Tidak ada pengajuan pembatalan yang menunggu persetujuan.',
                $exception->getMessage(),
            );
        }

        Notification::assertSentTo($tenant, GeneralBookingNotification::class);
    }

    public function test_reject(): void
    {
        Notification::fake();
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $tenant = $this->createUser(
            ServiceTestData::ROLE_TENANT,
            ServiceTestData::EMAIL_TENANT_BUDI,
            ServiceTestData::CUSTOMER_BUDI,
        );
        $detail = $this->createDetail($owner, $tenant);

        $cancellation = $this->createPendingCancellation($detail);

        app(CancelService::class)->reject($detail, ServiceTestData::REJECT_REASON, $owner);

        $this->assertSame(ServiceTestData::APPROVAL_REJECTED, $cancellation->fresh()->approval_status);
        $this->assertSame(ServiceTestData::REJECT_REASON, $cancellation->fresh()->rejection_reason);

        $detailWithoutCancellation = $this->createDetail($owner, $tenant);

        try {
            app(CancelService::class)->reject($detailWithoutCancellation, ServiceTestData::REJECT_REASON, $owner);
            $this->fail('Reject seharusnya menolak detail tanpa pengajuan pending.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'Tidak ada pengajuan pembatalan yang menunggu persetujuan.',
                $exception->getMessage(),
            );
        }

        Notification::assertSentTo($tenant, GeneralBookingNotification::class);
    }
}
