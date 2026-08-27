<?php

use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get(route('activity-log.ringkasan'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the ringkasan aktivitas page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('activity-log.ringkasan'))
        ->assertSuccessful()
        ->assertSee('Ringkasan Aktivitas Sistem')
        ->assertSee('Data Ditambahkan')
        ->assertSee('Data Diubah')
        ->assertSee('Data Dihapus')
        ->assertSee('Total Aksi Data');
});

test('it calculates statistics correctly for the selected month and year', function () {
    $user = User::factory()->create();
    $targetDate = Carbon::create(2026, 5, 15, 10, 0, 0);

    Activity::query()->delete();

    // 2 Logins in May 2026 (excluded from pure data metrics)
    activity('auth')
        ->causedBy($user)
        ->createdAt($targetDate)
        ->log('User successfully logged in');

    activity('auth')
        ->causedBy($user)
        ->createdAt($targetDate)
        ->log('User successfully logged in');

    // 3 Created in May 2026
    activity()
        ->causedBy($user)
        ->performedOn(new Incoming(['id' => 101]))
        ->event('created')
        ->createdAt($targetDate)
        ->log('created');

    activity()
        ->causedBy($user)
        ->performedOn(new Outcoming(['id' => 201]))
        ->event('created')
        ->createdAt($targetDate)
        ->log('created');

    activity()
        ->causedBy($user)
        ->performedOn(new Incoming(['id' => 102]))
        ->event('created')
        ->createdAt($targetDate)
        ->log('created');

    // 1 Updated in May 2026
    activity()
        ->causedBy($user)
        ->performedOn(new Incoming(['id' => 101]))
        ->event('updated')
        ->createdAt($targetDate)
        ->log('updated');

    // 1 Deleted in May 2026
    activity()
        ->causedBy($user)
        ->performedOn(new Outcoming(['id' => 201]))
        ->event('deleted')
        ->createdAt($targetDate)
        ->log('deleted');

    // 1 Activity in another month (June 2026)
    activity()
        ->causedBy($user)
        ->performedOn(new Incoming(['id' => 999]))
        ->event('created')
        ->createdAt(Carbon::create(2026, 6, 1, 12, 0, 0))
        ->log('created');

    $response = $this->actingAs($user)
        ->get(route('activity-log.ringkasan', [
            'tahun' => 2026,
            'bulan' => 5,
        ]))
        ->assertSuccessful();

    $response->assertViewHas('totalCreated', 3);
    $response->assertViewHas('totalUpdated', 1);
    $response->assertViewHas('totalDeleted', 1);
    $response->assertViewHas('totalAktivitas', 5);
});

test('it supports viewing all months in a year', function () {
    $user = User::factory()->create();

    Activity::query()->delete();

    activity()
        ->causedBy($user)
        ->performedOn(new Incoming(['id' => 1]))
        ->event('created')
        ->createdAt(Carbon::create(2026, 1, 10, 8, 0, 0))
        ->log('created');

    activity()
        ->causedBy($user)
        ->performedOn(new Incoming(['id' => 2]))
        ->event('created')
        ->createdAt(Carbon::create(2026, 8, 20, 9, 0, 0))
        ->log('created');

    $response = $this->actingAs($user)
        ->get(route('activity-log.ringkasan', [
            'tahun' => 2026,
            'bulan' => 'semua',
        ]))
        ->assertSuccessful();

    $response->assertViewHas('totalCreated', 2);
    $response->assertViewHas('selectedMonth', 'semua');
});

test('it can filter statistics by specific user', function () {
    $userA = User::factory()->create(['name' => 'User A']);
    $userB = User::factory()->create(['name' => 'User B']);
    $date = Carbon::create(2026, 8, 1, 10, 0, 0);

    Activity::query()->delete();

    activity()
        ->causedBy($userA)
        ->performedOn(new Incoming(['id' => 1]))
        ->event('created')
        ->createdAt($date)
        ->log('created');

    activity()
        ->causedBy($userB)
        ->performedOn(new Incoming(['id' => 2]))
        ->event('created')
        ->createdAt($date)
        ->log('created');

    $response = $this->actingAs($userA)
        ->get(route('activity-log.ringkasan', [
            'tahun' => 2026,
            'bulan' => 8,
            'user_id' => $userA->id,
        ]))
        ->assertSuccessful();

    $response->assertViewHas('totalCreated', 1);
    $response->assertDontSee('Aktivitas Per Pengguna (Pelaku)');

    $responseAll = $this->actingAs($userA)
        ->get(route('activity-log.ringkasan', [
            'tahun' => 2026,
            'bulan' => 8,
        ]))
        ->assertSuccessful();

    $responseAll->assertSee('Aktivitas Per Pengguna (Pelaku)');
});

test('category breakdown sum matches top metrics and user breakdown exactly', function () {
    $user = User::factory()->create();
    $date = Carbon::create(2026, 8, 10, 12, 0, 0);

    Activity::query()->delete();

    // Create 1 Incoming
    activity()->causedBy($user)->performedOn(new Incoming(['id' => 1]))->event('created')->createdAt($date)->log('created');
    // Create 1 Outcoming
    activity()->causedBy($user)->performedOn(new Outcoming(['id' => 1]))->event('created')->createdAt($date)->log('created');
    // Create 1 API Token (log_name api-token, excluded from domain data stats)
    activity('api-token')->causedBy($user)->event('created')->createdAt($date)->log('created');
    // Delete 1 API Token (excluded from domain data stats)
    activity('api-token')->causedBy($user)->event('deleted')->createdAt($date)->log('deleted');

    $response = $this->actingAs($user)
        ->get(route('activity-log.ringkasan', [
            'tahun' => 2026,
            'bulan' => 8,
        ]))
        ->assertSuccessful();

    $categories = $response->viewData('categories');
    $categoryCreatedSum = collect($categories)->sum('created');
    $categoryDeletedSum = collect($categories)->sum('deleted');

    // Only Incoming and Outcoming count towards domain data creation
    expect($response->viewData('totalCreated'))->toBe(2)
        ->and($categoryCreatedSum)->toBe(2)
        ->and($response->viewData('totalDeleted'))->toBe(0)
        ->and($categoryDeletedSum)->toBe(0)
        ->and(count($categories))->toBe(5); // Only 5 domain categories
});

test('it renders drill-down links to activity log in ringkasan page', function () {
    $user = User::factory()->create();
    $date = Carbon::create(2026, 8, 10, 12, 0, 0);

    Activity::query()->delete();

    activity()->causedBy($user)->performedOn(new Incoming(['id' => 1]))->event('created')->createdAt($date)->log('created');

    $response = $this->actingAs($user)
        ->get(route('activity-log.ringkasan', [
            'tahun' => 2026,
            'bulan' => 8,
        ]))
        ->assertSuccessful();

    // Check that links contain subject_type and event query parameters
    $response->assertSee(route('activity-log', ['tahun' => 2026, 'bulan' => 8, 'subject_type' => 'incoming']));
    $response->assertSee(route('activity-log', ['tahun' => 2026, 'bulan' => 8, 'subject_type' => 'incoming', 'event' => 'created']));
});

test('activity log ajax supports filtering by subject_type and event', function () {
    $user = User::factory()->create();
    $date = Carbon::create(2026, 8, 10, 12, 0, 0);

    Activity::query()->delete();

    activity()->causedBy($user)->performedOn(new Incoming(['id' => 101]))->event('created')->createdAt($date)->log('created');
    activity()->causedBy($user)->performedOn(new Outcoming(['id' => 201]))->event('created')->createdAt($date)->log('created');
    activity()->causedBy($user)->performedOn(new Incoming(['id' => 101]))->event('updated')->createdAt($date)->log('updated');

    // Filter incoming only
    $response = $this->actingAs($user)
        ->getJson(route('activity-log', [
            'subject_type' => 'incoming',
            'tahun' => 2026,
            'bulan' => 8,
        ]))
        ->assertSuccessful();

    $json = $response->json();
    expect($json['recordsTotal'])->toBe(2);

    // Filter incoming and created event only
    $responseCreated = $this->actingAs($user)
        ->getJson(route('activity-log', [
            'subject_type' => 'incoming',
            'event' => 'created',
            'tahun' => 2026,
            'bulan' => 8,
        ]))
        ->assertSuccessful();

    $jsonCreated = $responseCreated->json();
    expect($jsonCreated['recordsTotal'])->toBe(1);
});
