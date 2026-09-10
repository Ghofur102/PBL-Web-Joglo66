<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Field;

class FieldSeeder extends Seeder
{
    public function run(): void
    {
        $field = Field::create([
            'name' => 'Joglo66 Mini Soccer',
            'description' => 'Lapangan rumput sintetis premium standar FIFA dengan fasilitas lampu sorot malam dan tribun penonton nyaman.',
            'image_url' => 'storage/fields/joglo66_minisoccer.jpg',
            'category' => 'Mini Soccer',
        ]);

        $worker = User::where('role', 'worker')->first();

        if ($worker) {
            DB::table('field_admins')->insert([
                'fk_user_id' => $worker->id,
                'fk_field_id' => $field->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
