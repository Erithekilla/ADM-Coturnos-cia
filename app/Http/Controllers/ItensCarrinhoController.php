<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ItensCarrinhoController extends Controller
{
    public function index(): JsonResponse
    {
        $data = CartItem::with(['cart', 'product'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->decodeHashids($request, [
            'produtos_id' => Product::class,
            'product_id' => Product::class,
        ]);

        $cartId = $request->input('cart_id') ?: $request->input('carrinho_id_carrinho');
        $productId = $request->input('product_id') ?: $request->input('produtos_id');
        $quantity = $request->input('quantity', $request->input('quantidade', 1));
        $size = $request->input('size') ?: $request->input('tamanho');

        $request->merge([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'size' => $size,
        ]);

        $validated = $request->validate([
            'cart_id' => 'required|integer|exists:carts,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'size' => 'required|integer',
        ]);

        $record = CartItem::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Item do carrinho criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = CartItem::with(['cart', 'product'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item do carrinho não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $this->decodeHashids($request, [
            'produtos_id' => Product::class,
            'product_id' => Product::class,
        ]);

        $record = CartItem::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item do carrinho não encontrado.'
            ], 404);
        }

        $cartId = $request->input('cart_id') ?: $request->input('carrinho_id_carrinho') ?: $record->cart_id;
        $productId = $request->input('product_id') ?: $request->input('produtos_id') ?: $record->product_id;
        $quantity = $request->input('quantity', $request->input('quantidade', $record->quantity));
        $size = $request->input('size') ?: $request->input('tamanho') ?: $record->size;

        $record->update([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'size' => $size,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Item do carrinho atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = CartItem::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item do carrinho não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Item do carrinho removido com sucesso!'
        ], 200);
    }
}
