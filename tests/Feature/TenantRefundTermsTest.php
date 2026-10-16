<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\FieldRefundRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class TenantRefundTermsTest extends TestCase
{
    private string $testDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDatabasePath = database_path('tenant_refund_terms_test.sqlite');
        if (file_exists($this->testDatabasePath)) {
            unlink($this->testDatabasePath);
        }
        touch($this->testDatabasePath);

        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = $this->testDatabasePath;
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => $sqlite,
            'database.connections.mysql_joglo66_app' => $sqlite,
        ]);
        DB::purge('sqlite');
        DB::purge('mysql_joglo66_app');

        $this->artisan('migrate:fresh', ['--database' => 'sqlite', '--force' => true])
            ->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::disconnect('mysql_joglo66_app');
        if (isset($this->testDatabasePath) && file_exists($this->testDatabasePath)) {
            unlink($this->testDatabasePath);
        }

        parent::tearDown();
    }

    public function test_booking_terms_reflect_active_field_rules(): void
    {
        $tenant = $this->createUser('tenant');
        $field = $this->createField($this->createUser('owner'));
        $this->createDefaultRules($field);

        $response = $this->actingAs($tenant)->post(route('tenant.booking.confirm-form'), [
            'field_id' => $field->id,
            'selected_slots' => json_encode([
                ['date' => now()->addDays(5)->toDateString(), 'jam' => '09:00', 'jam_akhir' => '10:00'],
            ]),
        ]);

        $response->assertOk()
            ->assertSee('minimal 72 jam')
            ->assertSee('100%')
            ->assertSee('Saya telah membaca dan menyetujui syarat');

        $field->refundRules()->delete();
        $field->refundRules()->createMany([
            ['rule_type' => 'cancel', 'min_hours_before' => 48, 'refund_percent' => 60, 'is_active' => true],
            ['rule_type' => 'reschedule', 'min_hours_before' => 24, 'refund_percent' => 35, 'is_active' => true],
            ['rule_type' => 'cancel', 'min_hours_before' => 12, 'refund_percent' => 10, 'is_active' => false],
        ]);

        $response = $this->post(route('tenant.booking.confirm-form'), [
            'field_id' => $field->id,
            'selected_slots' => json_encode([
                ['date' => now()->addDays(5)->toDateString(), 'jam' => '09:00', 'jam_akhir' => '10:00'],
            ]),
        ]);

        $response->assertOk()
            ->assertSee('minimal 48 jam')
            ->assertSee('60%')
            ->assertSee('minimal 24 jam')
            ->assertSee('35%')
            ->assertDontSee('minimal 12 jam')
            ->assertDontSee('minimal 72 jam');
    }

    public function test_booking_without_refund_terms_acceptance_is_rejected(): void
    {
        $tenant = $this->createUser('tenant');
        $field = $this->createField($this->createUser('owner'));

        $this->actingAs($tenant)
            ->from(route('tenant.booking.dashboard'))
            ->post(route('tenant.booking.store'), [
                'field_id' => $field->id,
                'team_name' => 'Tim Penguji',
                'booking_data' => json_encode([
                    now()->addDays(5)->toDateString() => [
                        ['jam' => '09:00', 'jam_akhir' => '10:00', 'harga' => 100000],
                    ],
                ]),
                'payment_type' => 'down payment',
            ])
            ->assertRedirect(route('tenant.booking.dashboard'))
            ->assertSessionHasErrors('refund_terms_accepted');

        $this->assertDatabaseCount('bookings', 0, 'mysql_joglo66_app');
    }

    public function test_cancel_and_reschedule_forms_show_field_policy_and_estimates(): void
    {
        $tenant = $this->createUser('tenant');
        $detail = $this->createDetail($tenant, 'active', now()->addDays(5)->toDateString());
        $detail->booking->field->refundRules()->createMany([
            ['rule_type' => 'cancel', 'min_hours_before' => 48, 'refund_percent' => 65, 'is_active' => true],
            ['rule_type' => 'reschedule', 'min_hours_before' => 24, 'refund_percent' => 40, 'is_active' => true],
        ]);

        $this->actingAs($tenant)
            ->get(route('tenant.booking.form.cancelled', $detail->id))
            ->assertOk()
            ->assertSee('minimal 48 jam')
            ->assertSee('Batas terakhir pengajuan:')
            ->assertSee('Estimasi persentase refund jika diajukan sekarang:')
            ->assertSee('65%');

        $this->get(route('tenant.booking.form.reschedule', $detail->id))
            ->assertOk()
            ->assertSee('minimal 24 jam')
            ->assertSee('Batas terakhir pengajuan:')
            ->assertSee('Estimasi persentase refund jika diajukan sekarang:')
            ->assertSee('40%');
    }

    public function test_late_cancel_request_is_rejected_by_the_server(): void
    {
        $tenant = $this->createUser('tenant');
        $detail = $this->createDetail($tenant, 'active', now()->addDay()->toDateString());
        $this->createDefaultRules($detail->booking->field);

        $this->actingAs($tenant)
            ->from(route('tenant.booking.form.cancelled', $detail->id))
            ->post(route('tenant.booking.process.cancelled'), [
                'detail_booking_id' => $detail->id,
                'reason' => 'Jadwal berubah',
            ])
            ->assertRedirect(route('tenant.booking.form.cancelled', $detail->id))
            ->assertSessionHas('error', 'Mohon maaf, pengajuan cancel ditolak karena telah melewati batas maksimal waktu pengajuan.');
    }

    public function test_late_reschedule_request_is_rejected_by_the_server(): void
    {
        $tenant = $this->createUser('tenant');
        $detail = $this->createDetail($tenant, 'active', now()->addDay()->toDateString());
        $this->createDefaultRules($detail->booking->field);

        $this->actingAs($tenant)
            ->from(route('tenant.booking.form.reschedule', $detail->id))
            ->post(route('tenant.booking.process.reschedule'), [
                'detail_booking_id' => $detail->id,
                'new_play_date' => now()->addDays(3)->toDateString(),
                'new_start_play_time' => '09:00',
                'new_end_play_time' => '10:00',
                'reason' => 'Jadwal berubah',
            ])
            ->assertRedirect(route('tenant.booking.form.reschedule', $detail->id))
            ->assertSessionHas('error', 'Mohon maaf, pengajuan reschedule ditolak karena telah melewati batas maksimal waktu pengajuan.');
    }

    public function test_unpaid_cancel_and_reschedule_requests_use_official_messages(): void
    {
        $tenant = $this->createUser('tenant');
        $detail = $this->createDetail($tenant, 'waiting', now()->addDays(5)->toDateString());
        $this->createDefaultRules($detail->booking->field);

        $this->actingAs($tenant)
            ->from(route('tenant.booking.form.cancelled', $detail->id))
            ->post(route('tenant.booking.process.cancelled'), [
                'detail_booking_id' => $detail->id,
                'reason' => 'Jadwal berubah',
            ])
            ->assertSessionHas('error', 'Pesanan yang belum dibayar tidak dapat diajukan cancel.');

        $this->from(route('tenant.booking.form.reschedule', $detail->id))
            ->post(route('tenant.booking.process.reschedule'), [
                'detail_booking_id' => $detail->id,
                'new_play_date' => now()->addDays(6)->toDateString(),
                'new_start_play_time' => '09:00',
                'new_end_play_time' => '10:00',
                'reason' => 'Jadwal berubah',
            ])
            ->assertSessionHas('error', 'Pesanan yang belum dibayar tidak dapat diajukan reschedule.');
    }

    public function test_other_tenant_cannot_view_cancellation_terms_for_transaction(): void
    {
        $tenant = $this->createUser('tenant');
        $otherTenant = $this->createUser('tenant');
        $detail = $this->createDetail($tenant, 'active', now()->addDays(5)->toDateString());
        $this->createDefaultRules($detail->booking->field);

        $this->actingAs($otherTenant)
            ->get(route('tenant.booking.form.cancelled', $detail->id))
            ->assertForbidden();
    }

    public function test_field_without_active_rules_shows_admin_contact_and_disables_cancel(): void
    {
        $tenant = $this->createUser('tenant');
        $detail = $this->createDetail($tenant, 'active', now()->addDays(5)->toDateString());
        $this->createDefaultRules($detail->booking->field);
        FieldRefundRule::query()->update(['is_active' => false]);

        $this->actingAs($tenant)
            ->get(route('tenant.booking.form.cancelled', $detail->id))
            ->assertOk()
            ->assertSee('Hubungi admin lapangan untuk informasi syarat dan ketentuan refund.')
            ->assertSee('Hubungi admin lapangan untuk kebijakan pembatalan.')
            ->assertSee('data-policy-eligible="false"', false)
            ->assertSee('disabled', false);

        $this->from(route('tenant.booking.form.cancelled', $detail->id))
            ->post(route('tenant.booking.process.cancelled'), [
                'detail_booking_id' => $detail->id,
                'reason' => 'Jadwal berubah',
            ])
            ->assertSessionHas('error', 'Hubungi admin lapangan untuk kebijakan pembatalan.');
    }

    private function createUser(string $role): User
    {
        return User::query()->create([
            'name' => ucfirst($role),
            'email' => $role.uniqid().'@example.test',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function createField(User $owner): Field
    {
        return Field::query()->create([
            'name' => 'Lapangan A',
            'category' => 'futsal',
            'fk_user_id' => $owner->id,
        ]);
    }

    private function createDefaultRules(Field $field): void
    {
        $field->refundRules()->createMany([
            ['rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 100, 'is_active' => true],
            ['rule_type' => 'reschedule', 'min_hours_before' => 72, 'refund_percent' => 100, 'is_active' => true],
        ]);
    }

    private function createDetail(User $tenant, string $status, string $playDate): BookingDetail
    {
        $field = $this->createField($this->createUser('owner'));
        $booking = Booking::query()->create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Tim Penguji',
            'customer_phone' => '-',
            'customer_email' => $tenant->email,
            'notes' => '-',
            'booking_date' => now()->toDateString(),
        ]);

        return BookingDetail::query()->create([
            'fk_booking_id' => $booking->id,
            'play_date' => $playDate,
            'start_play_time' => '09:00:00',
            'end_play_time' => '10:00:00',
            'price' => 100000,
            'status' => $status,
        ]);
    }
}
