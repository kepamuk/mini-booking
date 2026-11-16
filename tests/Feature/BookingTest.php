<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Database\Seeders\SlotSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00:00', 'Europe/Moscow'));
    }

    public function test_a_free_future_slot_can_be_booked(): void
    {
        $slot = $this->futureSlot();
        $response = $this->postJson('/api/bookings', $this->payload($slot));

        $response->assertCreated()->assertJsonStructure(['id']);
        $this->assertIsInt($response->json('id'));
        $this->assertDatabaseHas('bookings', ['id' => $response->json('id'), ...$this->payload($slot)]);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_a_second_booking_returns_409_and_does_not_create_another_booking(): void
    {
        $slot = $this->futureSlot();
        $this->postJson('/api/bookings', $this->payload($slot))->assertCreated();
        $this->postJson('/api/bookings', [
            ...$this->payload($slot), 'customer_name' => 'Другой сотрудник',
        ])->assertConflict()->assertJsonPath('message', 'Этот слот уже забронировали. Выберите другое время.');

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['slot_id' => $slot->id, 'customer_name' => 'Александр']);
    }

    public function test_past_slots_and_the_exact_current_second_cannot_be_booked(): void
    {
        foreach ([now()->subHour()->timestamp, now()->timestamp] as $start) {
            $slot = Slot::query()->create(['starts_at' => $start, 'ends_at' => $start + 3600]);
            $this->postJson('/api/bookings', $this->payload($slot))->assertConflict();
        }

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_validation_returns_field_errors_including_a_nonexistent_slot(): void
    {
        $this->postJson('/api/bookings', [
            'slot_id' => 99999, 'customer_name' => '  ', 'customer_email' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors(['slot_id', 'customer_name', 'customer_email']);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_cancellation_releases_the_slot_and_old_ids_do_not_cancel_a_new_booking(): void
    {
        $slot = $this->futureSlot();
        $first = $this->postJson('/api/bookings', $this->payload($slot))->assertCreated()->json('id');
        $this->deleteJson("/api/bookings/{$first}")->assertNoContent();
        $this->getJson('/api/slots')->assertJsonPath('data.0.status', 'available');

        $second = $this->postJson('/api/bookings', $this->payload($slot))->assertCreated()->json('id');
        $this->assertNotSame($first, $second);
        $this->deleteJson("/api/bookings/{$first}")->assertNoContent();
        $this->assertDatabaseHas('bookings', ['id' => $second]);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_slots_are_sorted_have_offsets_and_never_expose_personal_data(): void
    {
        $future = $this->futureSlot();
        $booking = Booking::query()->create($this->payload($future));
        $past = Slot::query()->create(['starts_at' => now()->timestamp, 'ends_at' => now()->addHour()->timestamp]);

        $response = $this->getJson('/api/slots')->assertOk()
            ->assertJsonPath('data.0.id', $past->id)
            ->assertJsonPath('data.0.status', 'past')
            ->assertJsonPath('data.1.id', $future->id)
            ->assertJsonPath('data.1.status', 'booked')
            ->assertJsonPath('data.1.booking_id', $booking->id)
            ->assertJsonPath('meta.timezone', 'Europe/Moscow')
            ->assertJsonMissingPath('data.1.customer_name')
            ->assertJsonMissingPath('data.1.customer_email')
            ->assertJsonMissingPath('data.1.booking');

        $this->assertStringEndsWith('+03:00', $response->json('data.1.starts_at'));
        $this->assertStringNotContainsString('alex@example.com', $response->getContent());
    }

    public function test_seeder_is_idempotent_keeps_bookings_and_adds_only_the_next_day(): void
    {
        $this->seed(SlotSeeder::class);
        $this->assertDatabaseCount('slots', 63);
        $slot = Slot::query()->orderByDesc('starts_at')->firstOrFail();
        $booking = Booking::query()->create($this->payload($slot));
        $ids = Slot::query()->orderBy('id')->pluck('id')->all();

        $this->seed(SlotSeeder::class);
        $this->assertSame($ids, Slot::query()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('slots', 63);
        $this->travel(1)->days();
        $this->seed(SlotSeeder::class);
        $this->assertDatabaseCount('slots', 72);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'slot_id' => $slot->id]);
        $this->assertDatabaseHas('slots', ['starts_at' => CarbonImmutable::parse('2026-11-16 09:00', 'Europe/Moscow')->timestamp]);
    }

    public function test_seeder_uses_the_configured_calendar_day_and_includes_weekends(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $this->travelTo(CarbonImmutable::parse('2026-11-20T23:30:00+00:00'));
        $this->seed(SlotSeeder::class);

        $slots = Slot::query()->orderBy('starts_at')->get();
        $this->assertCount(63, $slots);
        $this->assertSame(CarbonImmutable::parse('2026-11-21 09:00', 'Asia/Tokyo')->timestamp, $slots->first()->starts_at);
        $this->assertSame(CarbonImmutable::parse('2026-11-27 18:00', 'Asia/Tokyo')->timestamp, $slots->last()->ends_at);
        $this->assertTrue($slots->every(fn (Slot $slot) => $slot->ends_at - $slot->starts_at === 3600));
    }

    public function test_the_database_rejects_duplicates_even_when_the_controller_is_bypassed(): void
    {
        $slot = $this->futureSlot();
        Booking::query()->create($this->payload($slot));

        $this->expectException(UniqueConstraintViolationException::class);
        Booking::query()->create($this->payload($slot));
    }

    private function futureSlot(): Slot
    {
        $start = now()->addDay()->setTime(10, 0);

        return Slot::query()->create(['starts_at' => $start->timestamp, 'ends_at' => $start->addHour()->timestamp]);
    }

    private function payload(Slot $slot): array
    {
        return ['slot_id' => $slot->id, 'customer_name' => 'Александр', 'customer_email' => 'alex@example.com'];
    }
}
