<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Cart::with(['user', 'items.product'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->decodeHashids($request, [
            'usuario_id' => User::class,
            'user_id' => User::class,
        ]);

        $userId = $request->input('user_id') ?: $request->input('usuario_id');
        $request->merge(['user_id' => $userId]);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'created_at' => 'nullable|date',
            'updated_at' => 'nullable|date',
        ]);

        $record = Cart::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Cart created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = Cart::with(['user', 'items.product'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cart not found.'
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
            'usuario_id' => User::class,
            'user_id' => User::class,
        ]);

        $record = Cart::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cart not found.'
            ], 404);
        }

        $userId = $request->input('user_id') ?: $request->input('usuario_id');
        $request->merge(['user_id' => $userId]);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'created_at' => 'nullable|date',
            'updated_at' => 'nullable|date',
        ]);

        $record->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Cart updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = Cart::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cart not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Cart removed successfully!'
        ], 200);
    }

    public function getMeCarrinho(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to access your cart.'
            ], 401);
        }

        $cart = $this->getOrCreateCart($user->id);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatCart($cart)
        ], 200);
    }

    public function adicionarItem(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to add items to your cart.'
            ], 401);
        }

        $this->decodeHashids($request, [
            'produto_id' => Product::class,
            'product_id' => Product::class,
        ]);

        $productId = (int) ($request->input('product_id') ?: $request->input('produto_id'));
        $request->merge(['product_id' => $productId]);

        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantidade' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1',
            'tamanho' => 'nullable|integer',
            'size' => 'nullable|integer',
        ]);

        $cart = $this->getOrCreateCart($user->id);
        $quantity = (int) ($request->input('quantity') ?: $request->input('quantidade') ?: 1);
        $size = (int) ($request->input('size') ?: $request->input('tamanho') ?: 40);

        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->first();

        if ($item) {
            $item->quantity += $quantity;
            $item->size = $size;
            $item->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'size' => $size,
            ]);
        }

        $cart->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Item added to cart!',
            'data' => $this->formatCart($cart)
        ], 200);
    }

    public function atualizarItem(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $this->decodeHashids($request, [
            'produto_id' => Product::class,
            'product_id' => Product::class,
        ]);

        $productId = (int) ($request->input('product_id') ?: $request->input('produto_id'));
        $request->merge(['product_id' => $productId]);

        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'delta' => 'nullable|integer',
            'quantidade' => 'nullable|integer',
            'quantity' => 'nullable|integer',
        ]);

        $cart = $this->getOrCreateCart($user->id);

        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->first();

        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item not found in cart.'
            ], 404);
        }

        if ($request->has('delta')) {
            $newQty = $item->quantity + (int) $request->delta;
        } else {
            $newQty = (int) ($request->input('quantity') ?: $request->input('quantidade'));
        }

        if ($newQty <= 0) {
            $item->delete();
        } else {
            $item->quantity = $newQty;
            $item->save();
        }

        $cart->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Cart updated!',
            'data' => $this->formatCart($cart)
        ], 200);
    }

    public function removerItem(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $this->decodeHashids($request, [
            'produto_id' => Product::class,
            'product_id' => Product::class,
        ]);

        $productId = (int) ($request->input('product_id') ?: $request->input('produto_id'));

        $cart = $this->getOrCreateCart($user->id);

        CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->delete();

        $cart->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Item removed from cart!',
            'data' => $this->formatCart($cart)
        ], 200);
    }

    public function limparCarrinho(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $cart = $this->getOrCreateCart($user->id);

        CartItem::where('cart_id', $cart->id)->delete();

        $cart->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Cart cleared!',
            'data' => [
                'id_carrinho' => $cart->id,
                'cart_id' => $cart->id,
                'itens' => [],
                'items' => [],
                'subtotal' => 0,
                'quantidade_total' => 0,
                'total_quantity' => 0,
            ]
        ], 200);
    }

    public function sincronizarCarrinho(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $cart = $this->getOrCreateCart($user->id);
        $items = $request->input('items', $request->input('itens', []));

        if (is_array($items)) {
            foreach ($items as $it) {
                $pId = (int) Product::decodeHashid($it['id'] ?? null);
                $qty = (int) ($it['quantity'] ?? $it['quantidade'] ?? 1);
                $size = (int) ($it['size'] ?? $it['tamanho'] ?? 40);

                if ($pId > 0 && Product::where('id', $pId)->exists()) {
                    $existing = CartItem::where('cart_id', $cart->id)
                        ->where('product_id', $pId)
                        ->first();

                    if ($existing) {
                        $existing->quantity = max($existing->quantity, $qty);
                        $existing->size = $size;
                        $existing->save();
                    } else {
                        CartItem::create([
                            'cart_id' => $cart->id,
                            'product_id' => $pId,
                            'quantity' => $qty,
                            'size' => $size,
                        ]);
                    }
                }
            }
        }

        $cart->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Cart synchronized successfully!',
            'data' => $this->formatCart($cart)
        ], 200);
    }

    private function getOrCreateCart($userId)
    {
        $cart = Cart::where('user_id', $userId)->first();
        if (!$cart) {
            $cart = Cart::create([
                'user_id' => $userId,
            ]);
        }
        return $cart;
    }

    private function formatCart($cart)
    {
        $itemsDb = CartItem::where('cart_id', $cart->id)
            ->with(['product.prices', 'product.images'])
            ->get();

        $formatted = [];
        $subtotal = 0;

        foreach ($itemsDb as $item) {
            $product = $item->product;
            if (!$product) continue;

            $price = $product->prices->first()->price ?? 0;
            $image = $product->images->first()->image_url ?? '/coturno1.jpeg';
            $color = $product->color ?: 'preto';
            $size = $item->size ?: 40;

            $itemObj = [
                'id' => $product->hashid,
                'name' => $product->model,
                'price' => (float) $price,
                'quantity' => (int) $item->quantity,
                'size' => $size,
                'color' => $color,
                'image' => $image,
                'cartKey' => "{$product->hashid}-{$size}-{$color}",
            ];

            $subtotal += ($itemObj['price'] * $itemObj['quantity']);
            $formatted[] = $itemObj;
        }

        return [
            'id_carrinho' => $cart->id,
            'cart_id' => $cart->id,
            'itens' => $formatted,
            'items' => $formatted,
            'subtotal' => $subtotal,
            'quantidade_total' => array_sum(array_column($formatted, 'quantity')),
            'total_quantity' => array_sum(array_column($formatted, 'quantity'))
        ];
    }

    private function getAuthenticatedUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User && $user->isActive() ? $user : null;
    }
}
