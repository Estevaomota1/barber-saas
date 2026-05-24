<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::where('barbershop_id', $request->user()->barbershop_id)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'services' => $services]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $service = Service::create([
            'barbershop_id' => $request->user()->barbershop_id,
            'name'          => $request->name,
            'price'         => $request->price,
            'duration'      => $request->duration,
            'description'   => $request->description,
            'active'        => true,
        ]);

        return response()->json(['success' => true, 'service' => $service]);
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name'        => 'sometimes|string',
            'price'       => 'sometimes|numeric|min:0',
            'duration'    => 'sometimes|integer|min:1',
            'description' => 'nullable|string',
            'active'      => 'sometimes|boolean',
        ]);

        $service->update($request->only(['name', 'price', 'duration', 'description', 'active']));

        return response()->json(['success' => true, 'service' => $service]);
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return response()->json(['success' => true]);
    }
}