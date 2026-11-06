<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Field;
use App\Models\FieldPrice;
use App\Models\FieldRefundRule;
use App\Models\FieldWorker;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class FieldSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('role', UserRole::OWNER->value)->first();

        if (!$owner) {
            $owner = User::create([
                'name'              => 'Owner Joglo66',
                'email'             => 'owner@joglo66.com',
                'password'          => 'password',
                'phone'             => '081234567890',
                'role'              => UserRole::OWNER->value,
                'email_verified_at' => now(),
            ]);
        }

        $field = Field::firstOrCreate([
            'name' => 'Joglo66 Mini Soccer',
        ], [
            'description'          => 'Lapangan rumput sintetis premium standar FIFA dengan fasilitas lampu sorot malam dan tribun penonton nyaman.',
            'image_url'            => 'storage/fields/joglo66_minisoccer.jpg',
            'category'             => 'mini soccer',
            'fk_user_id'           => $owner->id,
            'min_cancel_days'      => 3,
            'min_reschedule_days'  => 3,
            'max_reschedule_times' => 1,
        ]);

        $worker = User::where('role', UserRole::WORKER->value)->first();

        if ($worker) {
            FieldWorker::firstOrCreate([
                'fk_user_id'  => $worker->id,
                'fk_field_id' => $field->id,
            ]);
        }

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $day) {
            FieldPrice::firstOrCreate([
                'fk_field_id' => $field->id,
                'day_type'    => $day,
                'start_time'  => '06:00:00',
                'end_time'    => '23:00:00',
            ], [
                'price'       => in_array($day, ['saturday', 'sunday'], true) ? 200000 : 150000,
            ]);
        }

        if (Schema::hasTable('field_refund_rules')) {
            $defaultRefundRules = [
                ['rule_type' => 'cancel', 'min_hours_before' => 72, 'refund_percent' => 100],
                ['rule_type' => 'cancel', 'min_hours_before' => 48, 'refund_percent' => 50],
                ['rule_type' => 'cancel', 'min_hours_before' => 24, 'refund_percent' => 25],
                ['rule_type' => 'reschedule', 'min_hours_before' => 72, 'refund_percent' => 100],
                ['rule_type' => 'reschedule', 'min_hours_before' => 48, 'refund_percent' => 50],
                ['rule_type' => 'reschedule', 'min_hours_before' => 24, 'refund_percent' => 25],
            ];

            foreach ($defaultRefundRules as $rule) {
                FieldRefundRule::firstOrCreate([
                    'fk_field_id'      => $field->id,
                    'rule_type'        => $rule['rule_type'],
                    'min_hours_before' => $rule['min_hours_before'],
                ], [
                    'refund_percent'   => $rule['refund_percent'],
                    'is_active'        => true,
                ]);
            }
        }
    }
}
