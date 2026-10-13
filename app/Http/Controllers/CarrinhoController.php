<?php

namespace App\Http\Controllers;

use App\Models\Carrinho;
use App\Models\ItensCarrinho;
use App\Models\Produto;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CarrinhoController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Carrinho::with(['usuario', 'itens.produto'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'usuario_id' => 'required|integer|exists:usuario,id',
            'criado_em' => 'required|date',
            'atualizado_em' => 'required|date',
        ]);

        $record = Carrinho::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Carrinho criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = Carrinho::with(['usuario', 'itens.produto'])->find($id);

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
        $record = Carrinho::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Carrinho não encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'usuario_id' => 'required|integer|exists:usuario,id',
            'criado_em' => 'required|date',
            'atualizado_em' => 'required|date',
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
        $record = Carrinho::find($id);

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

        $request->validate([
            'produto_id' => 'required|integer|exists:produtos,id',
            'quantidade' => 'nullable|integer|min:1',
            'tamanho' => 'nullable|integer',
        ]);

        $carrinho = $this->obterOuCriarCarrinho($usuario->id);
        $produtoId = (int) $request->produto_id;
        $quantidade = (int) ($request->quantidade ?? 1);
        $tamanho = (int) ($request->tamanho ?? 40);

        $item = ItensCarrinho::where('carrinho_id_carrinho', $carrinho->id_carrinho)
            ->where('produtos_id', $produtoId)
            ->first();

        if ($item) {
            $item->quantidade += $quantidade;
            $item->tamanho = $tamanho;
            $item->save();
        } else {
            ItensCarrinho::create([
                'carrinho_id_carrinho' => $carrinho->id_carrinho,
                'produtos_id' => $produtoId,
                'quantidade' => $quantidade,
                'tamanho' => $tamanho,
            ]);
        }

        $carrinho->update(['atualizado_em' => now()]);

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

        $request->validate([
            'produto_id' => 'required|integer|exists:produtos,id',
            'delta' => 'nullable|integer',
            'quantidade' => 'nullable|integer',
        ]);

        $carrinho = $this->obterOuCriarCarrinho($usuario->id);
        $produtoId = (int) $request->produto_id;

        $item = ItensCarrinho::where('carrinho_id_carrinho', $carrinho->id_carrinho)
            ->where('produtos_id', $produtoId)
            ->first();

        if (!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Item não encontrado no carrinho.'
            ], 404);
        }

        if ($request->has('delta')) {
            $novaQtd = $item->quantidade + (int) $request->delta;
        } else {
            $novaQtd = (int) $request->quantidade;
        }

        if ($novaQtd <= 0) {
            $item->delete();
        } else {
            $item->quantidade = $novaQtd;
            $item->save();
        }

        $carrinho->update(['atualizado_em' => now()]);

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

        $request->validate([
            'produto_id' => 'required|integer',
        ]);

        $carrinho = $this->obterOuCriarCarrinho($usuario->id);

        ItensCarrinho::where('carrinho_id_carrinho', $carrinho->id_carrinho)
            ->where('produtos_id', $request->produto_id)
            ->delete();

        $carrinho->update(['atualizado_em' => now()]);

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

        ItensCarrinho::where('carrinho_id_carrinho', $carrinho->id_carrinho)->delete();

        $carrinho->update(['atualizado_em' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Carrinho esvaziado!',
            'data' => [
                'id_carrinho' => $carrinho->id_carrinho,
                'itens' => [],
                'subtotal' => 0,
                'quantidade_total' => 0
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
        $itens = $request->input('itens', []);

        if (is_array($itens)) {
            foreach ($itens as $it) {
                $pId = (int) ($it['id'] ?? 0);
                $qtd = (int) ($it['quantity'] ?? 1);
                $tam = (int) ($it['size'] ?? 40);

                if ($pId > 0 && Produto::where('id', $pId)->exists()) {
                    $existente = ItensCarrinho::where('carrinho_id_carrinho', $carrinho->id_carrinho)
                        ->where('produtos_id', $pId)
                        ->first();

                    if ($existente) {
                        $existente->quantidade = max($existente->quantidade, $qtd);
                        $existente->tamanho = $tam;
                        $existente->save();
                    } else {
                        ItensCarrinho::create([
                            'carrinho_id_carrinho' => $carrinho->id_carrinho,
                            'produtos_id' => $pId,
                            'quantidade' => $qtd,
                            'tamanho' => $tam,
                        ]);
                    }
                }
            }
        }

        $carrinho->update(['atualizado_em' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Carrinho sincronizado com sucesso!',
            'data' => $this->formatarCarrinho($carrinho)
        ], 200);
    }

    private function obterOuCriarCarrinho($usuarioId)
    {
        $carrinho = Carrinho::where('usuario_id', $usuarioId)->first();
        if (!$carrinho) {
            $carrinho = Carrinho::create([
                'usuario_id' => $usuarioId,
                'criado_em' => now(),
                'atualizado_em' => now()
            ]);
        }
        return $carrinho;
    }

    private function formatarCarrinho($carrinho)
    {
        $itensDB = ItensCarrinho::where('carrinho_id_carrinho', $carrinho->id_carrinho)
            ->with(['produto.precos', 'produto.imagens'])
            ->get();

        $itensFormatados = [];
        $subtotal = 0;

        foreach ($itensDB as $item) {
            $produto = $item->produto;
            if (!$produto) continue;

            $preco = $produto->precos->first()->preco ?? 0;
            $imagem = $produto->imagens->first()->url_imagem ?? '/coturno1.jpeg';
            $cor = 'preto';
            $tamanho = $item->tamanho ?: 40;

            $itemObj = [
                'id' => $produto->id,
                'name' => $produto->modelo,
                'price' => (float) $preco,
                'quantity' => (int) $item->quantidade,
                'size' => $tamanho,
                'color' => $cor,
                'image' => $imagem,
                'cartKey' => "{$produto->id}-{$tamanho}-{$cor}",
            ];

            $subtotal += ($itemObj['price'] * $itemObj['quantity']);
            $itensFormatados[] = $itemObj;
        }

        return [
            'id_carrinho' => $carrinho->id_carrinho,
            'itens' => $itensFormatados,
            'subtotal' => $subtotal,
            'quantidade_total' => array_sum(array_column($itensFormatados, 'quantity'))
        ];
    }

    private function getAuthenticatedUser(Request $request)
    {
        $token = $request->bearerToken() ?: $request->header('Authorization') ?: $request->input('token');

        if (!$token) {
            if ($request->has('usuario_id')) {
                return Usuario::find($request->usuario_id);
            }
            return null;
        }

        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        try {
            $payload = decrypt($token);
            if (!isset($payload['user_id'])) {
                return null;
            }
            return Usuario::find($payload['user_id']);
        } catch (\Exception $e) {
            return null;
        }
    }
}

