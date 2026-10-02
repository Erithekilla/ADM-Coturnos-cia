<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Preco;
use App\Models\TamanhoProduto;
use App\Models\ImagensProduto;
use App\Models\ItensCarrinho;
use App\Models\PromocaoProduto;
use App\Models\ColecaoHasProduto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProdutoController extends Controller
{
    /**
     * Lista todos os produtos com seus relacionamentos (preço, imagem, tamanhos).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Produto::with(['imagens', 'precos', 'tamanhos'])
            ->where(function ($q) {
                $q->whereNull('excluido')->orWhereRaw("TRIM(UPPER(COALESCE(excluido, 'N'))) != 'S'");
            })
            ->orderBy('id', 'desc');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('modelo', 'like', "%{$search}%")
                  ->orWhere('tipo_do_produto', 'like', "%{$search}%")
                  ->orWhere('marca', 'like', "%{$search}%");
            });
        }

        $produtos = $query->get();

        $formatados = $produtos->map(function ($p) {
            return $this->formatarProduto($p);
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatados,
            'total' => $formatados->count(),
        ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Cadastra um novo produto com preço, imagem e múltiplos tamanhos.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:45',
            'modelo' => 'nullable|string|max:45',
            'productType' => 'nullable|string|max:45',
            'tipo_do_produto' => 'nullable|string|max:45',
            'brand' => 'nullable|string|max:45',
            'marca' => 'nullable|string|max:45',
            'price' => 'nullable|numeric|min:0',
            'preco' => 'nullable|numeric|min:0',
            'sizes' => 'nullable',
            'tamanhos' => 'nullable',
        ]);

        $modelo = $request->input('name') ?: $request->input('modelo');
        $tipo = $request->input('productType') ?: $request->input('tipo_do_produto') ?: 'Coturno';
        $marca = $request->input('brand') ?: $request->input('marca') ?: 'Coturnos & Cia';
        $descricao = $request->input('description') ?: $request->input('descricao') ?: '';
        $cor = $request->input('color') ?: $request->input('cor') ?: 'Preto';
        $ativoRaw = $request->input('active') ?: $request->input('ativo') ?: $request->input('status') ?: 'ativo';
        $ativo = strtolower((string)$ativoRaw) === 'inativo' ? 'inativo' : 'ativo';

        if (empty($descricao)) {
            $descricao = "Calçado de alta resistência | Cor: {$cor}";
        }

        $precoValor = (float) ($request->input('price') ?: $request->input('preco') ?: 0);
        $estoqueTotal = (int) ($request->input('stock') ?: $request->input('estoque') ?: 20);
        $imageUrl = $request->input('image') ?: $request->input('imagem') ?: '/coturno1.jpeg';

        // Trata os tamanhos (pode vir como array [38, 39, 40] ou string)
        $tamanhosRecebidos = $request->input('tamanhos_array') ?: $request->input('sizes') ?: $request->input('tamanhos') ?: [38, 39, 40, 41, 42, 43, 44];
        if (is_string($tamanhosRecebidos)) {
            $tamanhosRecebidos = array_map('intval', explode(',', str_replace(['[', ']', ' '], '', $tamanhosRecebidos)));
        }

        try {
            DB::beginTransaction();

            $produto = Produto::create([
                'tipo_do_produto' => substr($tipo, 0, 45),
                'modelo' => substr($modelo ?: 'Novo Produto', 0, 45),
                'marca' => substr($marca, 0, 45),
                'descricao' => substr($descricao, 0, 255),
                'cor' => substr($cor ?: 'Preto', 0, 45),
                'ativo' => $ativo,
                'status' => $ativo,
                'excluido' => 'N',
            ]);

            // 1. Grava preço
            Preco::create([
                'preco' => $precoValor,
                'alteracao' => now(),
                'produtos_id' => $produto->id,
            ]);

            // 2. Grava múltiplos tamanhos na tabela tamanho_produtos
            if (is_array($tamanhosRecebidos) && count($tamanhosRecebidos) > 0) {
                $qtdPorTamanho = max(1, (int) round($estoqueTotal / count($tamanhosRecebidos)));
                foreach ($tamanhosRecebidos as $tam) {
                    $tamNum = (int) $tam;
                    if ($tamNum > 0) {
                        TamanhoProduto::create([
                            'produtos_id' => $produto->id,
                            'tamanho' => $tamNum,
                            'quantidade' => $qtdPorTamanho,
                        ]);
                    }
                }
            }

            // 3. Grava imagem do produto
            if (!empty($imageUrl)) {
                ImagensProduto::create([
                    'url_imagem' => $imageUrl,
                    'produtos_id' => $produto->id,
                ]);
            }

            DB::commit();

            $produtoCarregado = Produto::with(['imagens', 'precos', 'tamanhos'])->find($produto->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Produto e grade de tamanhos cadastrados com sucesso!',
                'data' => $this->formatarProduto($produtoCarregado),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Falha ao salvar produto no banco de dados.',
                'error_detail' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exibe os detalhes de um produto específico.
     */
    public function show($id): JsonResponse
    {
        $produto = Produto::with(['imagens', 'precos', 'tamanhos'])->find($id);

        if (!$produto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produto não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatarProduto($produto),
        ], 200);
    }

    /**
     * Atualiza um produto existente e sincroniza sua grade de tamanhos.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $produto = Produto::find($id);

        if (!$produto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produto não encontrado.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $modelo = $request->input('name') ?: $request->input('modelo') ?: $produto->modelo;
            $tipo = $request->input('productType') ?: $request->input('tipo_do_produto') ?: $produto->tipo_do_produto;
            $marca = $request->input('brand') ?: $request->input('marca') ?: $produto->marca;
            $descricao = $request->input('description') ?: $request->input('descricao') ?: $produto->descricao;
            $cor = $request->input('color') ?: $request->input('cor') ?: $produto->cor ?: 'Preto';
            
            $ativo = $produto->ativo ?: 'ativo';
            if ($request->has('active') || $request->has('ativo') || $request->has('status')) {
                $val = $request->input('active') ?: $request->input('ativo') ?: $request->input('status');
                $ativo = strtolower((string)$val) === 'inativo' ? 'inativo' : 'ativo';
            }

            $produto->update([
                'tipo_do_produto' => substr($tipo, 0, 45),
                'modelo' => substr($modelo, 0, 45),
                'marca' => substr($marca, 0, 45),
                'descricao' => substr($descricao, 0, 255),
                'cor' => substr($cor, 0, 45),
                'ativo' => $ativo,
                'status' => $ativo,
            ]);

            // Atualiza Preço se enviado
            if ($request->has('price') || $request->has('preco')) {
                $precoValor = (float) ($request->input('price') ?: $request->input('preco'));
                Preco::create([
                    'preco' => $precoValor,
                    'alteracao' => now(),
                    'produtos_id' => $produto->id,
                ]);
            }

            // Atualiza Tamanhos se enviados
            if ($request->has('sizes') || $request->has('tamanhos') || $request->has('tamanhos_array')) {
                $tamanhosRecebidos = $request->input('tamanhos_array') ?: $request->input('sizes') ?: $request->input('tamanhos');
                if (is_string($tamanhosRecebidos)) {
                    $tamanhosRecebidos = array_map('intval', explode(',', str_replace(['[', ']', ' '], '', $tamanhosRecebidos)));
                }

                if (is_array($tamanhosRecebidos)) {
                    // Remove tamanhos antigos e insere os novos
                    TamanhoProduto::where('produtos_id', $produto->id)->delete();
                    $estoqueTotal = (int) ($request->input('stock') ?: $request->input('estoque') ?: 20);
                    $qtdPorTamanho = max(1, (int) round($estoqueTotal / max(1, count($tamanhosRecebidos))));

                    foreach ($tamanhosRecebidos as $tam) {
                        $tamNum = (int) $tam;
                        if ($tamNum > 0) {
                            TamanhoProduto::create([
                                'produtos_id' => $produto->id,
                                'tamanho' => $tamNum,
                                'quantidade' => $qtdPorTamanho,
                            ]);
                        }
                    }
                }
            }

            // Atualiza Imagem se enviada
            if ($request->has('image') || $request->has('imagem')) {
                $imageUrl = $request->input('image') ?: $request->input('imagem');
                if (!empty($imageUrl)) {
                    ImagensProduto::where('produtos_id', $produto->id)->delete();
                    ImagensProduto::create([
                        'url_imagem' => $imageUrl,
                        'produtos_id' => $produto->id,
                    ]);
                }
            }

            DB::commit();

            $produtoAtualizado = Produto::with(['imagens', 'precos', 'tamanhos'])->find($produto->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Produto atualizado com sucesso!',
                'data' => $this->formatarProduto($produtoAtualizado),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Falha ao atualizar produto.',
                'error_detail' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove o produto e todas as suas dependências em cascata.
     */
    public function destroy($id): JsonResponse
    {
        $produto = Produto::find($id);

        if (!$produto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produto não encontrado.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // 1. Remove itens de carrinho associados a este produto no banco
            ItensCarrinho::where('produtos_id', $id)->delete();

            // 2. Remove vínculos com coleções
            DB::table('colecao_has_produtos')->where('produtos_id', $id)->delete();

            // 3. Atualiza a coluna 'excluido' diretamente na tabela 'produtos' do banco MySQL
            DB::table('produtos')->where('id', $id)->update([
                'excluido' => 'S',
                'ativo' => 'inativo',
                'status' => 'inativo',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Produto excluído com sucesso do banco de dados!',
                'data' => ['id' => (int) $id]
            ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao excluir produto no banco de dados.',
                'error_detail' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Formata os dados de um produto para retorno uniforme ao Front-end.
     */
    private function formatarProduto(Produto $p): array
    {
        $precoRecente = $p->precos->sortByDesc('alteracao')->first();
        $preco = $precoRecente ? (float) $precoRecente->preco : 0;

        $tamanhosColecao = $p->tamanhos->sortBy('tamanho');
        $tamanhosArray = $tamanhosColecao->pluck('tamanho')->values()->toArray();
        $tamanhosTexto = count($tamanhosArray) > 0 ? implode(', ', $tamanhosArray) : '38-44';

        $estoque = $tamanhosColecao->sum('quantidade') ?: 20;
        $imagem = $p->imagens->first()->url_imagem ?? '/coturno1.jpeg';

        $cor = $p->cor ?: 'Preto';
        if ($cor === 'Preto' && preg_match('/Cor:\s*([^|]+)/i', $p->descricao, $matches)) {
            $cor = trim($matches[1]);
        }

        $isAtivo = strtolower((string)$p->ativo) !== 'inativo' && strtolower((string)$p->status) !== 'inativo';

        return [
            'id' => $p->id,
            'name' => $p->modelo,
            'productType' => $p->tipo_do_produto,
            'model' => $p->modelo,
            'brand' => $p->marca,
            'description' => $p->descricao,
            'active' => $isAtivo ? 'Ativo' : 'Inativo',
            'ativo' => $isAtivo ? 'ativo' : 'inativo',
            'status' => $isAtivo ? 'ativo' : 'inativo',
            'price' => $preco,
            'stock' => $estoque,
            'sizes' => $tamanhosTexto,
            'tamanhos_array' => $tamanhosArray,
            'color' => $cor,
            'image' => $imagem,
        ];
    }
}
