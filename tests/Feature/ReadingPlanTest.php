<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-29 20:00:00', 'Asia/Tokyo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plan(User $user, string $status = 'in_progress', int $days = 0, ?Book $book = null): ReadingPlan
    {
        return $user->readingPlans()->create(['book_id' => ($book ?? Book::factory()->create())->id, 'target_date' => Carbon::today('Asia/Tokyo')->addDays($days), 'status' => $status]);
    }

    public function test_guest_routes_require_login(): void
    {
        foreach (['/reading-plans', '/reading-plans/create', '/notifications'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/reading-plans')->assertRedirect('/login');
    }

    public function test_creation_validation_duplicates_and_no_notification(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $this->actingAs($user);
        $this->post('/reading-plans', ['book_id' => $book->id, 'target_date' => '2026-09-29', 'status' => 'completed', 'user_id' => 999])->assertRedirect('/reading-plans');
        $plan = $user->readingPlans()->first();
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->post('/reading-plans', ['book_id' => $book->id, 'target_date' => '2026-09-30'])->assertSessionHasErrors('book_id');
        $this->post('/reading-plans', ['book_id' => 999, 'target_date' => '2026-09-28'])->assertSessionHasErrors(['book_id', 'target_date']);
        $this->post('/reading-plans', [])->assertSessionHasErrors(['book_id', 'target_date']);
        $plan->update(['status' => 'expired']);
        $this->post('/reading-plans', ['book_id' => $book->id, 'target_date' => '2026-09-30'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_list_is_scoped_sorted_paginated_and_filtered(): void
    {
        $user = User::factory()->create();
        $this->plan(User::factory()->create());
        for ($i = 11; $i >= 0; $i--) {
            $this->plan($user, 'in_progress', $i);
        }
        $this->plan($user, 'completed', 20);
        $response = $this->actingAs($user)->get('/reading-plans?status=in_progress')->assertOk();
        $plans = $response->viewData('readingPlans');
        $this->assertSame(12, $plans->total());
        $this->assertCount(10, $plans);
        $this->assertSame('2026-09-29', $plans[0]->target_date->toDateString());
        $response->assertSee('status=in_progress', false);
        $this->get('/reading-plans?status=invalid')->assertSessionHasErrors('status');
        $this->get('/reading-plans/create')->assertOk();
    }

    public function test_edit_restore_complete_idempotence_and_permissions(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user, 'expired', -3);
        $this->actingAs($user);
        $this->get('/reading-plans/'.$plan->id.'/edit')->assertOk();
        $this->put('/reading-plans/'.$plan->id, ['target_date' => '2026-10-01', 'book_id' => 999, 'status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->fresh()->status);
        $this->assertSame($plan->book_id, $plan->fresh()->book_id);
        $this->assertDatabaseCount('notifications', 0);
        $this->post('/reading-plans/'.$plan->id.'/complete')->assertRedirect();
        $completed = $plan->fresh()->completed_at;
        $this->assertNotNull($completed);
        $this->travel(1)->hours();
        $this->post('/reading-plans/'.$plan->id.'/complete');
        $this->assertTrue($completed->equalTo($plan->fresh()->completed_at));
        $this->get('/reading-plans/'.$plan->id.'/edit')->assertForbidden();
        $this->put('/reading-plans/'.$plan->id, ['target_date' => '2026-10-02'])->assertForbidden();
        $other = $this->plan(User::factory()->create());
        $this->get('/reading-plans/'.$other->id.'/edit')->assertForbidden();
        $this->put('/reading-plans/'.$other->id, [])->assertForbidden();
        $this->post('/reading-plans/'.$other->id.'/complete')->assertForbidden();
        $this->delete('/reading-plans/'.$other->id)->assertForbidden();
        $this->get('/reading-plans/999/edit')->assertNotFound();
    }

    public function test_update_duplicate_is_rejected_but_self_is_excluded(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $expired = $this->plan($user, 'expired', -3, $book);
        $active = $this->plan($user, 'in_progress', 1, $book);
        $this->actingAs($user);
        $this->put('/reading-plans/'.$expired->id, ['target_date' => '2026-10-01'])->assertSessionHasErrors('target_date');
        $this->put('/reading-plans/'.$active->id, ['target_date' => '2026-10-02'])->assertSessionHasNoErrors();
    }

    public function test_batch_expires_before_notifying_and_deduplicates_even_after_read(): void
    {
        $user = User::factory()->create();
        $before = $this->plan($user, 'in_progress', 3);
        $due = $this->plan($user);
        $after = $this->plan($user, 'in_progress', -3);
        $this->plan($user, 'in_progress', 7);
        $completed = $this->plan($user, 'completed', -3);
        $yesterday = $this->plan($user, 'in_progress', -1);
        $deleted = $this->plan($user);
        $deleted->delete();
        $this->artisan('reading-plans:remind')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 3);
        $this->assertSame(ReadingPlanStatus::Expired, $after->fresh()->status);
        $this->assertSame(ReadingPlanStatus::Expired, $yesterday->fresh()->status);
        $this->assertSame(ReadingPlanStatus::Completed, $completed->fresh()->status);
        $this->assertSame(ReadingPlanStatus::InProgress, $due->fresh()->status);
        $this->assertEqualsCanonicalizing(['three_days_before', 'on_due_date', 'three_days_after'], $user->notifications->pluck('data.timing')->all());
        $user->notifications->each->markAsRead();
        $this->artisan('reading-plans:remind')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 3);
        $this->actingAs($user)->delete('/reading-plans/'.$before->id)->assertRedirect('/reading-plans');
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseMissing('reading_plans', ['id' => $before->id]);
    }

    public function test_notifications_are_private_paginated_and_read_is_idempotent(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        for ($i = 0; $i < 21; $i++) {
            $user->notify(new ReadingPlanReminder($plan, 'on_due_date'));
        }
        $other = User::factory()->create();
        $other->notify(new ReadingPlanReminder($this->plan($other), 'on_due_date'));
        $response = $this->actingAs($user)->get('/notifications')->assertOk();
        $this->assertSame(21, $response->viewData('notifications')->total());
        $this->assertCount(20, $response->viewData('notifications'));
        $notification = $user->notifications()->first();
        $this->post('/notifications/'.$notification->id.'/read')->assertRedirect('/notifications');
        $readAt = $notification->fresh()->read_at;
        $this->assertNotNull($readAt);
        $this->travel(1)->hours();
        $this->post('/notifications/'.$notification->id.'/read')->assertRedirect('/notifications');
        $this->assertTrue($readAt->equalTo($notification->fresh()->read_at));
        $this->post('/notifications/'.$other->notifications()->first()->id.'/read')->assertNotFound();
    }

    public function test_schedule_and_seed_scenarios(): void
    {
        $this->seed();
        $this->assertDatabaseCount('reading_plans', 6);
        $plans = ReadingPlan::orderBy('id')->get();
        $this->assertSame('suzuki@example.com', $plans[5]->user->email);
        $this->assertSame('2026-09-24', $plans[4]->completed_at->toDateString());
        $this->artisan('reading-plans:remind')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 3);
        $events = app(Schedule::class)->events();
        $event = collect($events)->first(fn ($event) => str_contains($event->command,'reading-plans:remind'));
        $this->assertSame('0 20 * * *',$event->expression);
        $this->assertSame('Asia/Tokyo',$event->timezone);
    }
}
