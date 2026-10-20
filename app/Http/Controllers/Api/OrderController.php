<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // Membuat order dari cart
    public function store(Request $request)
{
    $user = $request->user();

    $cart = $user->cart()->with('items.product')->first();

    if (!$cart || $cart->items->isEmpty()) {
        return response()->json([
            'message' => 'Cart kosong',
        ], 400);
    }

    try {
        $order = DB::transaction(function () use ($cart, $user) {

    $total = 0;
    $products = [];

    foreach ($cart->items as $item) {

        $product = $item->product()
            ->lockForUpdate()
            ->first();

        if (!$product) {
            throw new \Exception(
                "Produk {$item->product_id} tidak ditemukan"
            );
        }

        if ($product->stock < $item->quantity) {
            throw new \Exception(
                "Stok {$product->name} tidak mencukupi. " .
                "Stok tersedia: {$product->stock}"
            );
        }

        $products[$item->product_id] = $product;

        $total += $product->price * $item->quantity;
    }

    $order = $user->orders()->create([
        'total' => $total,
        'status' => 'pending',
    ]);

    foreach ($cart->items as $item) {

        $product = $products[$item->product_id];

        $price = $product->price;
        $subtotal = $price * $item->quantity;

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $price,
            'quantity' => $item->quantity,
            'subtotal' => $subtotal,
        ]);

        $product->stock -= $item->quantity;
        $product->save();
    }

    $cart->items()->delete();

    return $order;
});
        $order->load('items');

        return response()->json([
            'message' => 'Order berhasil dibuat',
            'order' => $order,
        ], 201);

    } catch (\Exception $e) {

        return response()->json([
            'message' => $e->getMessage(),
        ], 400);
    }
}
    // Menampilkan semua order milik user
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = $user->orders()
            ->with('items')
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    // Menampilkan detail satu order
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $order = $user->orders()
            ->with('items')
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'order' => $order,
        ]);
    }
}