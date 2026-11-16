<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SlotController extends Controller
{
    public function index(): JsonResponse
    {
        $now = CarbonImmutable::now();
        $timezone = config('app.timezone');
        $slots = Slot::query()->with('booking:id,slot_id')->orderBy('starts_at')->get();

        return response()->json([
            'data' => $slots->map(fn (Slot $slot) => [
                'id' => $slot->id,
                'starts_at' => CarbonImmutable::createFromTimestampUTC($slot->starts_at)->setTimezone($timezone)->toIso8601String(),
                'ends_at' => CarbonImmutable::createFromTimestampUTC($slot->ends_at)->setTimezone($timezone)->toIso8601String(),
                'status' => $slot->starts_at <= $now->timestamp ? 'past' : ($slot->booking ? 'booked' : 'available'),
                'booking_id' => $slot->booking?->id,
            ]),
            'meta' => ['timezone' => $timezone, 'server_time' => $now->toIso8601String()],
        ])->header('Cache-Control', 'no-store');
    }
}
