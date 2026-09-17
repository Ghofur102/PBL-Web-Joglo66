<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Field;
use App\Models\FieldWorker;
use App\Models\User;
use Illuminate\Database\Seeder;

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

        $field = Field::create([
            'name'                 => 'Joglo66 Mini Soccer',
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
    }
}
