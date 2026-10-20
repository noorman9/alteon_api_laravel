<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $cart = $user->cart()->first();

        if (!$cart) {
            $cart = $user->cart()->create();
        }

        $cart->load('items.product');

        $items = $cart->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'price' => (float) $item->product->price,
                'image' => $item->product->image,
                'quantity' => $item->quantity,
                'stock' => $item->product->stock,
                'subtotal' => (float) $item->product->price
                    * $item->quantity,
                
            ];
        });

        return response()->json([
            'cart_id' => $cart->id,
            'items' => $items->values(),
            'total' => $items->sum('subtotal'),
        ]);
    }

    public function store(Request $request)
{
    $request->validate([
        'product_id' => 'required|integer|exists:products,id',
        'quantity' => 'required|integer|min:1',
    ]);

    $user = $request->user();

    $cart = $user->cart()->first();

    if (!$cart) {
        $cart = $user->cart()->create();
    }

    $item = $cart->items()
        ->where('product_id', $request->product_id)
        ->first();

    $product = Product::find($request->product_id);

if (!$product) {
    return response()->json([
        'message' => 'Produk tidak ditemukan',
    ], 404);
}

$item = $cart->items()
    ->where('product_id', $request->product_id)
    ->first();

if ($product->stock <= 0) {
    return response()->json([
        'message' => "Stok {$product->name} sedang habis",
    ], 422);
}

if ($item) {
    $newQuantity = $item->quantity + $request->quantity;

    if ($newQuantity > $product->stock) {
        return response()->json([
            'message' => "Stok {$product->name} hanya tersedia {$product->stock}",
        ], 422);
    }

    $item->quantity = $newQuantity;
    $item->save();
} else {
    if ($request->quantity > $product->stock) {
        return response()->json([
            'message' => "Stok {$product->name} hanya tersedia {$product->stock}",
        ], 422);
    }

    $item = $cart->items()->create([
        'product_id' => $request->product_id,
        'quantity' => $request->quantity,
    ]);
}
    $item->load('product');

    return response()->json([
        'message' => 'Produk berhasil ditambahkan ke cart',
        'item' => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'name' => $item->product->name,
            'price' => (float) $item->product->price,
            'image' => $item->product->image,
            'quantity' => $item->quantity,
            'subtotal' => (float) $item->product->price
                * $item->quantity,
        ],
    ], 201);
}

public function update(Request $request, $id)
{
    $request->validate([
        'quantity' => 'required|integer|min:1',
    ]);

    $user = $request->user();

    $cart = $user->cart()->first();

    if (!$cart) {
        return response()->json([
            'message' => 'Cart tidak ditemukan',
        ], 404);
    }

    $item = $cart->items()->where('id', $id)->first();

    if (!$item) {
        return response()->json([
            'message' => 'Item cart tidak ditemukan',
        ], 404);
    }

   $item->load('product');

if ($request->quantity > $item->product->stock) {
    return response()->json([
        'message' => "Stok {$item->product->name} hanya tersedia {$item->product->stock}",
    ], 422);
}

$item->quantity = $request->quantity;
$item->save();
    $item->load('product');

    return response()->json([
        'message' => 'Quantity berhasil diubah',
        'item' => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'name' => $item->product->name,
            'price' => (float) $item->product->price,
            'image' => $item->product->image,
            'quantity' => $item->quantity,
            'subtotal' => (float) $item->product->price
                * $item->quantity,
        ],
    ]);
}

public function destroy(Request $request, $id)
{
    $user = $request->user();

    $cart = $user->cart()->first();

    if (!$cart) {
        return response()->json([
            'message' => 'Cart tidak ditemukan',
        ], 404);
    }

    $item = $cart->items()->where('id', $id)->first();

    if (!$item) {
        return response()->json([
            'message' => 'Item cart tidak ditemukan',
        ], 404);
    }

    $item->delete();

    return response()->json([
        'message' => 'Item berhasil dihapus dari cart',
    ]);
}
}