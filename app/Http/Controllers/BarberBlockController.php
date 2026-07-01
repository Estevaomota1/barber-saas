<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use Illuminate\Http\Request;

class BarberBlockController extends Controller
{
    // GET /api/barbers/{barber}/blocks
    public function index(Request $request, $barberId)
    {
        try {
            $barber = Barber::where('id', $barberId)
                ->where('barbershop_id', $request->user()->barbershop_id)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'blocks'  => $barber->blocks()->orderBy('date')->orderBy('day_of_week')->get(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // POST /api/barbers/{barber}/blocks
    public function store(Request $request, $barberId)
    {
        try {
            $request->validate([
                'type'        => 'required|in:once,recurring',
                'date'        => 'required_if:type,once|date',
                'day_of_week' => 'required_if:type,recurring|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'start_time'  => 'required',
                'end_time'    => 'required|after:start_time',
                'reason'      => 'nullable|string',
            ]);

            $barber = Barber::where('id', $barberId)
                ->where('barbershop_id', $request->user()->barbershop_id)
                ->firstOrFail();

            $block = $barber->blocks()->create([
                'date'        => $request->type === 'once' ? $request->date : null,
                'day_of_week' => $request->type === 'recurring' ? $request->day_of_week : null,
                'start_time'  => $request->start_time,
                'end_time'    => $request->end_time,
                'reason'      => $request->reason,
            ]);

            return response()->json(['success' => true, 'block' => $block], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // DELETE /api/barbers/{barber}/blocks/{block}
    public function destroy(Request $request, $barberId, $blockId)
    {
        try {
            $barber = Barber::where('id', $barberId)
                ->where('barbershop_id', $request->user()->barbershop_id)
                ->firstOrFail();

            $barber->blocks()->where('id', $blockId)->firstOrFail()->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }
}