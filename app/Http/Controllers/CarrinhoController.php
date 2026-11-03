<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CarrinhoController extends Controller
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
            'message' => 'Carrinho criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = Cart::with(['user', 'items.product'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Carrinho não encontrado.'
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
                'message' => 'Carrinho não encontrado.'
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
            'message' => 'Carrinho atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = Cart::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Carrinho não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Carrinho removido com sucesso!'
        ], 200);
    }

    public function getMeCarrinho(Request $request): JsonResponse
    {
        $usuario = $this->getAuthenticatedUser($request);
        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'message' => 'Faça login para acessar o carrinho no banco de dados.'
            ], 401);
        }

        $carrinho = $this->obterOuCriarCarrinho($usuario->id);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatarCarrinho($carrinho)
        ], 200);
    }

    public function adicionarItem(Request $request): JsonResponse
    {
        $usuario = $this->getAuthenticatedUser($request);
        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'message' => 'Faça login para salvar itens no carrinho.'
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

        $carrinho = $this->obterOuCriarCarrinho($usuario->id);
        $quantidade = (int) ($request->input('quantity') ?: $request->input('quantidade') ?: 1);
        $tamanho = (int) ($request->input('size') ?: $request->input('tamanho') ?: 40);

        $item = CartItem::where('cart_id', $carrinho->id)
            ->where('product_id', $productId)
            ->first();

        if ($item) {
            $item->quantity += $quantidade;
            $item->size = $tamanho;
            $item->save();
        } else {
            CartItem::create([
                'cart_id' => $carrinho->id,
                'product_id' => $productId,
                'quantity' => $quantidade,
                'size' => $tamanho,
            ]);
        }

        $carrinho->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Item adicionado ao carrinho!',
            'data' => $this->formatarCarrinho($carrinho)
        ], 200);
    }

    public function atualizarItem(Request $request): JsonResponse
    {
        $usuario = $this->getAuthenticatedUser($request);
        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não autenticado.'
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

        $carrinho = $this->obterOuCriarCarrinho($usuario->id);

        $item = CartItem::where('cart_id', $carrinho->id)
            ->where('product_id', $productId)
            ->first();

        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item não encontrado no carrinho.'
            ], 404);
        }

        if ($request->has('delta')) {
            $novaQtd = $item->quantity + (int) $request->delta;
        } else {
            $novaQtd = (int) ($request->input('quantity') ?: $request->input('quantidade'));
        }

        if ($novaQtd <= 0) {
            $item->delete();
        } else {
            $item->quantity = $novaQtd;
            $item->save();
        }

        $carrinho->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Carrinho atualizado!',
            'data' => $this->formatarCarrinho($carrinho)
        ], 200);
    }

    public function removerItem(Request $request): JsonResponse
    {
        $usuario = $this->getAuthenticatedUser($request);
        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não autenticado.'
            ], 401);
        }

        $this->decodeHashids($request, [
            'produto_id' => Product::class,
            'product_id' => Product::class,
        ]);

        $productId = (int) ($request->input('product_id') ?: $request->input('produto_id'));

        $carrinho = $this->obterOuCriarCarrinho($usuario->id);

        CartItem::where('cart_id', $carrinho->id)
            ->where('product_id', $productId)
            ->delete();

        $carrinho->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Item removido do carrinho!',
            'data' => $this->formatarCarrinho($carrinho)
        ], 200);
    }

    public function limparCarrinho(Request $request): JsonResponse
    {
        $usuario = $this->getAuthenticatedUser($request);
        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não autenticado.'
            ], 401);
        }

        $carrinho = $this->obterOuCriarCarrinho($usuario->id);

        CartItem::where('cart_id', $carrinho->id)->delete();

        $carrinho->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Carrinho esvaziado!',
            'data' => [
                'id_carrinho' => $carrinho->id,
                'cart_id' => $carrinho->id,
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
        $usuario = $this->getAuthenticatedUser($request);
        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não autenticado.'
            ], 401);
        }

        $carrinho = $this->obterOuCriarCarrinho($usuario->id);
        $itens = $request->input('items', $request->input('itens', []));

        if (is_array($itens)) {
            foreach ($itens as $it) {
                $pId = (int) Product::decodeHashid($it['id'] ?? null);
                $qtd = (int) ($it['quantity'] ?? $it['quantidade'] ?? 1);
                $tam = (int) ($it['size'] ?? $it['tamanho'] ?? 40);

                if ($pId > 0 && Product::where('id', $pId)->exists()) {
                    $existente = CartItem::where('cart_id', $carrinho->id)
                        ->where('product_id', $pId)
                        ->first();

                    if ($existente) {
                        $existente->quantity = max($existente->quantity, $qtd);
                        $existente->size = $tam;
                        $existente->save();
                    } else {
                        CartItem::create([
                            'cart_id' => $carrinho->id,
                            'product_id' => $pId,
                            'quantity' => $qtd,
                            'size' => $tam,
                        ]);
                    }
                }
            }
        }

        $carrinho->touch();

        return response()->json([
            'status' => 'success',
            'message' => 'Carrinho sincronizado com sucesso!',
            'data' => $this->formatarCarrinho($carrinho)
        ], 200);
    }

    private function obterOuCriarCarrinho($usuarioId)
    {
        $carrinho = Cart::where('user_id', $usuarioId)->first();
        if (!$carrinho) {
            $carrinho = Cart::create([
                'user_id' => $usuarioId,
            ]);
        }
        return $carrinho;
    }

    private function formatarCarrinho($carrinho)
    {
        $itensDB = CartItem::where('cart_id', $carrinho->id)
            ->with(['product.prices', 'product.images'])
            ->get();

        $itensFormatados = [];
        $subtotal = 0;

        foreach ($itensDB as $item) {
            $produto = $item->product;
            if (!$produto) continue;

            $preco = $produto->prices->first()->price ?? 0;
            $imagem = $produto->images->first()->image_url ?? '/coturno1.jpeg';
            $cor = $produto->color ?: 'preto';
            $tamanho = $item->size ?: 40;

            $itemObj = [
                'id' => $produto->hashid,
                'name' => $produto->model,
                'price' => (float) $preco,
                'quantity' => (int) $item->quantity,
                'size' => $tamanho,
                'color' => $cor,
                'image' => $imagem,
                'cartKey' => "{$produto->hashid}-{$tamanho}-{$cor}",
            ];

            $subtotal += ($itemObj['price'] * $itemObj['quantity']);
            $itensFormatados[] = $itemObj;
        }

        return [
            'id_carrinho' => $carrinho->id,
            'cart_id' => $carrinho->id,
            'itens' => $itensFormatados,
            'items' => $itensFormatados,
            'subtotal' => $subtotal,
            'quantidade_total' => array_sum(array_column($itensFormatados, 'quantity')),
            'total_quantity' => array_sum(array_column($itensFormatados, 'quantity'))
        ];
    }

    /**
     * Usuário dono do token Sanctum (rotas sob auth:sanctum).
     * Escopo obrigatório: nunca aceita usuario_id vindo do cliente (anti-IDOR).
     */
    private function getAuthenticatedUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User && $user->isActive() ? $user : null;
    }
}
