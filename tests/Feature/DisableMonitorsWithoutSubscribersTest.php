<?php

use App\Models\Monitor;
use App\Models\User;

describe('monitor:disable-without-subscribers', function () {
    it('disables monitors that have zero subscribers', function () {
        $user = User::factory()->create();

        $monitorWithSubscriber = Monitor::factory()->create([
            'is_public' => true,
            'uptime_check_enabled' => true,
        ]);
        $monitorWithSubscriber->users()->attach($user->id);

        $orphanMonitor = Monitor::factory()->create([
            'is_public' => true,
            'uptime_check_enabled' => true,
        ]);

        $this->artisan('monitor:disable-without-subscribers')
            ->expectsOutputToContain('Successfully disabled 1 monitor(s) without subscribers.')
            ->assertSuccessful();

        expect($monitorWithSubscriber->fresh()->uptime_check_enabled)->toBeTrue();
        expect($orphanMonitor->fresh()->uptime_check_enabled)->toBeFalse();
    });

    it('does not disable monitors in dry-run mode', function () {
        $orphanMonitor = Monitor::factory()->create([
            'is_public' => true,
            'uptime_check_enabled' => true,
        ]);

        $this->artisan('monitor:disable-without-subscribers --dry-run')
            ->expectsOutputToContain('DRY RUN: 1 monitor(s) without subscribers would be disabled.')
            ->assertSuccessful();

        expect($orphanMonitor->fresh()->uptime_check_enabled)->toBeTrue();
    });

    it('handles scenario when all monitors have subscribers', function () {
        $user = User::factory()->create();

        $monitor = Monitor::factory()->create([
            'is_public' => true,
            'uptime_check_enabled' => true,
        ]);
        $monitor->users()->attach($user->id);

        $this->artisan('monitor:disable-without-subscribers')
            ->expectsOutputToContain('No enabled monitors without subscribers found.')
            ->assertSuccessful();
    });
});
