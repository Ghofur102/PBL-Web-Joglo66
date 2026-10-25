<?php

namespace Tests\Unit\Services\Tenant;

use App\Models\User;
use App\Services\Tenant\NotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class NotificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.connections.sqlite');
        $connection['database'] = ':memory:';
        config([
            'database.default' => 'mysql_joglo66_app',
            'database.connections.mysql_joglo66_app' => $connection,
        ]);
        DB::purge('mysql_joglo66_app');

        Schema::connection('mysql_joglo66_app')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql_joglo66_app')->create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_get_paginated_notifications(): void
    {
        $user = User::create(['name' => 'Tenant', 'email' => 'tenant@example.com']);
        $this->addNotification($user);
        $this->addNotification($user);

        $page = (new NotificationService)->getPaginatedNotifications($user, 1);

        $this->assertCount(1, $page->items());
        $this->assertSame(2, $page->total());
        $this->assertSame(1, $page->perPage());
    }

    public function test_get_unread_count(): void
    {
        $user = User::create(['name' => 'Tenant', 'email' => 'tenant@example.com']);
        $this->addNotification($user);
        $this->addNotification($user, now());

        $this->assertSame(1, (new NotificationService)->getUnreadCount($user));
    }

    public function test_mark_as_read(): void
    {
        $user = User::create(['name' => 'Tenant', 'email' => 'tenant@example.com']);
        $notification = $this->addNotification($user);

        $result = (new NotificationService)->markAsRead($user, $notification);

        $this->assertNotNull($result);
        $this->assertNotNull($result->fresh()->read_at);
        $this->assertNull((new NotificationService)->markAsRead($user, (string) Str::uuid()));
    }

    public function test_mark_all_as_read(): void
    {
        $user = User::create(['name' => 'Tenant', 'email' => 'tenant@example.com']);
        $this->addNotification($user);
        $this->addNotification($user);

        (new NotificationService)->markAllAsRead($user);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    private function addNotification(User $user, ?Carbon $readAt = null): string
    {
        $id = (string) Str::uuid();
        DB::connection('mysql_joglo66_app')->table('notifications')->insert([
            'id' => $id,
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'read_at' => $readAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
