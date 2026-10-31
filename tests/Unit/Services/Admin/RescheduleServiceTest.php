<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingReschedule;
use App\Models\Field;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\GeneralBookingNotification;
use App\Services\Admin\RescheduleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RescheduleServiceTest extends TestCase
{
    protected RescheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RescheduleService();
    }

    public function test_execute(): void
    {
        Notification::fake();

        $tenant = User::factory()->create(['role' => 'tenant']);
        $admin = User::factory()->create(['role' => 'worker']);
        $field = Field::factory()->create();

        $booking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Garuda FC',
            'customer_phone' => '081234567890',
            'customer_email' => $tenant->email,
            'booking_date' => now()->toDateString(),
        ]);

        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => '2026-11-01',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $data = [
            'new_price' => 150000,
            'new_play_date' => '2026-11-05',
            'new_start_time' => '14:00:00',
            'new_end_time' => '15:00:00',
            'reason' => 'Perubahan jam bermain oleh admin',
            'financial_action' => 'Lunas',
            'reconciled_amount' => 50000,
        ];

        $this->service->execute($detail, $data, $admin);

        // Assert reschedule created with deposit required
        $this->assertDatabaseHas('booking_reschedules', [
            'fk_booking_detail_id' => $detail->id,
            'old_date' => '2026-11-01',
            'new_play_date' => '2026-11-05',
            'new_price' => 150000,
            'status_refund' => 'deposit required',
            'approval_status' => 'approved',
            'sender_by' => 'admin',
        ]);

        // Assert detail updated
        $detail->refresh();
        $this->assertSame('2026-11-05', $detail->play_date);
        $this->assertSame('14:00:00', $detail->start_play_time);
        $this->assertSame('15:00:00', $detail->end_play_time);
        $this->assertEquals(150000, $detail->price);
        $this->assertSame(BookingDetailStatus::RESCHEDULE->value, $detail->status);

        // Assert payment created for reconciled amount
        $this->assertDatabaseHas('payments', [
            'fk_booking_id' => $booking->id,
            'fk_booking_detail_id' => $detail->id,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'amount' => 50000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);

        // Assert notification sent to tenant
        Notification::assertSentTo($tenant, GeneralBookingNotification::class, function ($notification) use ($detail, $tenant) {
            $data = $notification->toArray($tenant);
            return $data['type'] === 'reschedule_by_admin'
                && $data['booking_id'] === $detail->fk_booking_id;
        });
    }

    public function test_approve(): void
    {
        Notification::fake();

        $tenant = User::factory()->create(['role' => 'tenant']);
        $admin = User::factory()->create(['role' => 'worker']);
        $field = Field::factory()->create();

        $booking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Elang FC',
            'booking_date' => now()->toDateString(),
        ]);

        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => '2026-11-01',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        // Case 1: Exception when no pending reschedule exists
        try {
            $this->service->approve($detail, $admin);
            $this->fail('Expected DomainException was not thrown');
        } catch (DomainException $e) {
            $this->assertSame('Tidak ada pengajuan reschedule yang menunggu persetujuan.', $e->getMessage());
        }

        $reschedule = BookingReschedule::create([
            'fk_booking_detail_id' => $detail->id,
            'old_date' => $detail->play_date,
            'new_play_date' => '2026-11-10',
            'new_start_play_time' => '16:00:00',
            'new_end_play_time' => '17:00:00',
            'new_price' => 120000,
            'reason' => 'Ingin ganti jam',
            'status_refund' => 'none',
            'approval_status' => 'pending',
            'sender_by' => 'tenant',
        ]);

        // Case 2: Exception when conflicting booking exists
        $otherBooking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Conflict FC',
            'booking_date' => now()->toDateString(),
        ]);
        $conflictDetail = BookingDetail::create([
            'fk_booking_id' => $otherBooking->id,
            'play_date' => '2026-11-10',
            'start_play_time' => '16:00:00',
            'end_play_time' => '17:00:00',
            'price' => 120000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        try {
            $this->service->approve($detail, $admin);
            $this->fail('Expected conflict DomainException was not thrown');
        } catch (DomainException $e) {
            $this->assertSame('Slot jadwal yang diajukan telah diambil oleh pemesanan lain.', $e->getMessage());
        }

        // Remove conflict to test successful approval
        $conflictDetail->delete();

        $this->service->approve($detail, $admin);

        $reschedule->refresh();
        $this->assertSame('approved', $reschedule->approval_status);

        $detail->refresh();
        $this->assertSame('2026-11-10', $detail->play_date);
        $this->assertSame('16:00:00', $detail->start_play_time);
        $this->assertSame('17:00:00', $detail->end_play_time);
        $this->assertEquals(120000, $detail->price);
        $this->assertSame(BookingDetailStatus::RESCHEDULE->value, $detail->status);

        Notification::assertSentTo($tenant, GeneralBookingNotification::class, function ($notification) use ($detail, $tenant) {
            $data = $notification->toArray($tenant);
            return $data['type'] === 'reschedule_approved'
                && $data['booking_id'] === $detail->fk_booking_id;
        });
    }

    public function test_reject(): void
    {
        Notification::fake();

        $tenant = User::factory()->create(['role' => 'tenant']);
        $admin = User::factory()->create(['role' => 'worker']);
        $field = Field::factory()->create();

        $booking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Rajawali FC',
            'booking_date' => now()->toDateString(),
        ]);

        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => '2026-11-01',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        // Case 1: Exception when no pending reschedule exists
        try {
            $this->service->reject($detail, 'Ditolak karena penuh', $admin);
            $this->fail('Expected DomainException was not thrown');
        } catch (DomainException $e) {
            $this->assertSame('Tidak ada pengajuan reschedule yang menunggu persetujuan.', $e->getMessage());
        }

        $reschedule = BookingReschedule::create([
            'fk_booking_detail_id' => $detail->id,
            'old_date' => $detail->play_date,
            'new_play_date' => '2026-11-12',
            'new_start_play_time' => '08:00:00',
            'new_end_play_time' => '09:00:00',
            'new_price' => 100000,
            'reason' => 'Perubahan jam',
            'status_refund' => 'none',
            'approval_status' => 'pending',
            'sender_by' => 'tenant',
        ]);

        $this->service->reject($detail, 'Jadwal penuh pada jam tersebut', $admin);

        $reschedule->refresh();
        $this->assertSame('rejected', $reschedule->approval_status);
        $this->assertSame('Jadwal penuh pada jam tersebut', $reschedule->rejection_reason);

        Notification::assertSentTo($tenant, GeneralBookingNotification::class, function ($notification) use ($detail, $tenant) {
            $data = $notification->toArray($tenant);
            return $data['type'] === 'reschedule_rejected'
                && $data['booking_id'] === $detail->fk_booking_id;
        });
    }
}
