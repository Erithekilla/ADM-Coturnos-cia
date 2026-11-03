<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSize;
use App\Models\ProductImage;
use App\Models\CartItem;
use App\Models\ProductPromotion;
use App\Models\CollectionProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProdutoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['images', 'prices', 'sizes'])
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
            })
            ->orderBy('id', 'desc');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('model', 'like', "%{$search}%")
                  ->orWhere('product_type', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
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

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:45',
            'modelo' => 'nullable|string|max:45',
            'model' => 'nullable|string|max:45',
            'productType' => 'nullable|string|max:45',
            'tipo_do_produto' => 'nullable|string|max:45',
            'product_type' => 'nullable|string|max:45',
            'brand' => 'nullable|string|max:45',
            'marca' => 'nullable|string|max:45',
            'price' => 'nullable|numeric|min:0',
            'preco' => 'nullable|numeric|min:0',
            'sizes' => 'nullable',
            'tamanhos' => 'nullable',
        ]);

        $modelo = $request->input('model') ?: $request->input('name') ?: $request->input('modelo');
        $tipo = $request->input('product_type') ?: $request->input('productType') ?: $request->input('tipo_do_produto') ?: 'Coturno';
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

        $tamanhosRecebidos = $request->input('tamanhos_array') ?: $request->input('sizes') ?: $request->input('tamanhos') ?: [38, 39, 40, 41, 42, 43, 44];
        if (is_string($tamanhosRecebidos)) {
            $tamanhosRecebidos = array_map('intval', explode(',', str_replace(['[', ']', ' '], '', $tamanhosRecebidos)));
        }

        try {
            DB::beginTransaction();

            $produto = Product::create([
                'product_type' => substr($tipo, 0, 45),
                'model' => substr($modelo ?: 'Novo Produto', 0, 45),
                'brand' => substr($marca, 0, 45),
                'description' => substr($descricao, 0, 255),
                'color' => substr($cor ?: 'Preto', 0, 45),
                'active' => $ativo,
                'status' => $ativo,
                'is_deleted' => 'N',
            ]);

            ProductPrice::create([
                'price' => $precoValor,
                'effective_date' => now(),
                'product_id' => $produto->id,
            ]);

            if (is_array($tamanhosRecebidos) && count($tamanhosRecebidos) > 0) {
                $qtdPorTamanho = max(1, (int) round($estoqueTotal / count($tamanhosRecebidos)));
                foreach ($tamanhosRecebidos as $tam) {
                    $tamNum = (int) $tam;
                    if ($tamNum > 0) {
                        ProductSize::create([
                            'product_id' => $produto->id,
                            'size' => $tamNum,
                            'quantity' => $qtdPorTamanho,
                        ]);
                    }
                }
            }

            if (!empty($imageUrl)) {
                ProductImage::create([
                    'image_url' => $imageUrl,
                    'product_id' => $produto->id,
                ]);
            }

            DB::commit();

            $produtoCarregado = Product::with(['images', 'prices', 'sizes'])->find($produto->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Produto e grade de tamanhos cadastrados com sucesso!',
                'data' => $this->formatarProduto($produtoCarregado),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Falha ao salvar produto no banco de dados.', $e);
        }
    }

    public function show($id): JsonResponse
    {
        $produto = Product::findByHashid($id, ['images', 'prices', 'sizes']);

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

    public function update(Request $request, $id): JsonResponse
    {
        $produto = Product::findByHashid($id);

        if (!$produto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produto não encontrado.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $modelo = $request->input('model') ?: $request->input('name') ?: $request->input('modelo') ?: $produto->model;
            $tipo = $request->input('product_type') ?: $request->input('productType') ?: $request->input('tipo_do_produto') ?: $produto->product_type;
            $marca = $request->input('brand') ?: $request->input('marca') ?: $produto->brand;
            $descricao = $request->input('description') ?: $request->input('descricao') ?: $produto->description;
            $cor = $request->input('color') ?: $request->input('cor') ?: $produto->color ?: 'Preto';

            $ativo = $produto->active ?: 'ativo';
            if ($request->has('active') || $request->has('ativo') || $request->has('status')) {
                $val = $request->input('active') ?: $request->input('ativo') ?: $request->input('status');
                $ativo = strtolower((string)$val) === 'inativo' ? 'inativo' : 'ativo';
            }

            $produto->update([
                'product_type' => substr($tipo, 0, 45),
                'model' => substr($modelo, 0, 45),
                'brand' => substr($marca, 0, 45),
                'description' => substr($descricao, 0, 255),
                'color' => substr($cor, 0, 45),
                'active' => $ativo,
                'status' => $ativo,
            ]);

            if ($request->has('price') || $request->has('preco')) {
                $precoValor = (float) ($request->input('price') ?: $request->input('preco'));
                ProductPrice::create([
                    'price' => $precoValor,
                    'effective_date' => now(),
                    'product_id' => $produto->id,
                ]);
            }

            if ($request->has('sizes') || $request->has('tamanhos') || $request->has('tamanhos_array')) {
                $tamanhosRecebidos = $request->input('tamanhos_array') ?: $request->input('sizes') ?: $request->input('tamanhos');
                if (is_string($tamanhosRecebidos)) {
                    $tamanhosRecebidos = array_map('intval', explode(',', str_replace(['[', ']', ' '], '', $tamanhosRecebidos)));
                }

                if (is_array($tamanhosRecebidos)) {
                    ProductSize::where('product_id', $produto->id)->delete();
                    $estoqueTotal = (int) ($request->input('stock') ?: $request->input('estoque') ?: 20);
                    $qtdPorTamanho = max(1, (int) round($estoqueTotal / max(1, count($tamanhosRecebidos))));

                    foreach ($tamanhosRecebidos as $tam) {
                        $tamNum = (int) $tam;
                        if ($tamNum > 0) {
                            ProductSize::create([
                                'product_id' => $produto->id,
                                'size' => $tamNum,
                                'quantity' => $qtdPorTamanho,
                            ]);
                        }
                    }
                }
            }

            if ($request->has('image') || $request->has('imagem')) {
                $imageUrl = $request->input('image') ?: $request->input('imagem');
                if (!empty($imageUrl)) {
                    ProductImage::where('product_id', $produto->id)->delete();
                    ProductImage::create([
                        'image_url' => $imageUrl,
                        'product_id' => $produto->id,
                    ]);
                }
            }

            DB::commit();

            $produtoAtualizado = Product::with(['images', 'prices', 'sizes'])->find($produto->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Produto atualizado com sucesso!',
                'data' => $this->formatarProduto($produtoAtualizado),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Falha ao atualizar produto.', $e);
        }
    }

    public function destroy($id): JsonResponse
    {
        $produto = Product::findByHashid($id);

        if (!$produto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produto não encontrado.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            CartItem::where('product_id', $produto->id)->delete();
            CollectionProduct::where('product_id', $produto->id)->delete();

            // Via Eloquent (e não DB::table) para que o ProductObserver registre a auditoria.
            $produto->forceFill([
                'is_deleted' => 'S',
                'active' => 'inativo',
                'status' => 'inativo',
            ])->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Produto excluído com sucesso do banco de dados!',
                'data' => ['id' => $produto->hashid]
            ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Erro ao excluir produto no banco de dados.', $e);
        }
    }

    private function formatarProduto(Product $p): array
    {
        $precoRecente = $p->prices->sortByDesc('effective_date')->first();
        $preco = $precoRecente ? (float) $precoRecente->price : 0;

        $tamanhosColecao = $p->sizes->sortBy('size');
        $tamanhosArray = $tamanhosColecao->pluck('size')->values()->toArray();
        $tamanhosTexto = count($tamanhosArray) > 0 ? implode(', ', $tamanhosArray) : '38-44';

        $estoque = $tamanhosColecao->sum('quantity') ?: 20;
        $imagem = $p->images->first()->image_url ?? '/coturno1.jpeg';

        $cor = $p->color ?: 'Preto';
        if ($cor === 'Preto' && preg_match('/Cor:\s*([^|]+)/i', (string) $p->description, $matches)) {
            $cor = trim($matches[1]);
        }

        $isAtivo = strtolower((string)$p->active) !== 'inativo' && strtolower((string)$p->status) !== 'inativo';

        return [
            'id' => $p->hashid,
            'name' => $p->model,
            'productType' => $p->product_type,
            'model' => $p->model,
            'brand' => $p->brand,
            'description' => $p->description,
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
