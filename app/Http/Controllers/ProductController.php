<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $barbershopId = $request->user()->barbershop_id;

        $products = Product::where('barbershop_id', $barbershopId)
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                return array_merge($p->toArray(), [
                    'low_stock' => $p->isLowStock(),
                ]);
            });

        return response()->json(['products' => $products]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'category'     => 'nullable|string|max:100',
            'description'  => 'nullable|string',
            'price'        => 'nullable|numeric|min:0',
            'cost'         => 'nullable|numeric|min:0',
            'quantity'     => 'nullable|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'unit'         => 'nullable|string|max:20',
        ]);

        $product = Product::create([
            'barbershop_id' => $request->user()->barbershop_id,
            'name'          => $request->name,
            'category'      => $request->category ?? 'outros',
            'description'   => $request->description,
            'price'         => $request->price ?? 0,
            'cost'          => $request->cost ?? 0,
            'quantity'      => $request->quantity ?? 0,
            'min_quantity'  => $request->min_quantity ?? 5,
            'unit'          => $request->unit ?? 'un',
        ]);

        return response()->json(['product' => $product], 201);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize($request, $product);

        $request->validate([
            'name'         => 'sometimes|string|max:255',
            'category'     => 'nullable|string|max:100',
            'description'  => 'nullable|string',
            'price'        => 'nullable|numeric|min:0',
            'cost'         => 'nullable|numeric|min:0',
            'quantity'     => 'nullable|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'unit'         => 'nullable|string|max:20',
        ]);

        $product->update($request->only([
            'name', 'category', 'description',
            'price', 'cost', 'quantity', 'min_quantity', 'unit',
        ]));

        return response()->json(['product' => $product]);
    }

    public function adjustStock(Request $request, Product $product)
    {
        $this->authorize($request, $product);

        $request->validate([
            'quantity' => 'required|integer',
            'type'     => 'required|in:add,remove,set',
        ]);

        if ($request->type === 'add') {
            $product->increment('quantity', $request->quantity);
        } elseif ($request->type === 'remove') {
            $product->decrement('quantity', $request->quantity);
        } else {
            $product->update(['quantity' => $request->quantity]);
        }

        return response()->json([
            'product'   => $product->fresh(),
            'low_stock' => $product->fresh()->isLowStock(),
        ]);
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorize($request, $product);
        $product->delete();
        return response()->json(['success' => true]);
    }

    private function authorize(Request $request, Product $product)
    {
        if ($product->barbershop_id !== $request->user()->barbershop_id) {
            abort(403, 'Unauthorized');
        }
    }
}