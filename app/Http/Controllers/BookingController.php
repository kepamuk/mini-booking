<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slot = Slot::query()->findOrFail($data['slot_id']);

        if ($slot->starts_at <= now()->timestamp) {
            return response()->json(['message' => 'Это время уже наступило. Выберите будущий слот.'], 409);
        }

        try {
            // One atomic INSERT. The unique DB index arbitrates concurrent requests.
            // No read-then-write transaction or SQLite lock upgrade is necessary.
            $booking = Booking::query()->create($data);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Этот слот уже забронировали. Выберите другое время.'], 409);
        }

        return response()->json(['id' => $booking->id], 201);
    }

    public function destroy(int $id): Response
    {
        Booking::query()->whereKey($id)->delete();

        return response()->noContent();
    }
}
