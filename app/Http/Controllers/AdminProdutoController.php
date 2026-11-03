<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSize;
use App\Models\ProductImage;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminProdutoController extends Controller
{
    protected CloudinaryService $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|max:10240',
        ]);

        $file = $request->file('image');
        $result = $this->cloudinaryService->upload($file, 'coturnos_produtos');

        return response()->json([
            'status' => 'success',
            'message' => 'Upload realizado com sucesso na pasta coturnos_produtos!',
            'data' => $result
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'modelo' => 'nullable|string|max:45',
            'model' => 'nullable|string|max:45',
            'tipo_do_produto' => 'nullable|string|max:45',
            'product_type' => 'nullable|string|max:45',
            'marca' => 'nullable|string|max:45',
            'brand' => 'nullable|string|max:45',
            'preco' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'grade' => 'nullable|array',
            'tamanhos' => 'nullable',
            'image' => 'nullable',
        ]);

        try {
            DB::beginTransaction();

            $tipo = $request->input('product_type') ?: $request->input('productType') ?: $request->input('tipo_do_produto') ?: 'Coturno';
            $marca = $request->input('brand') ?: $request->input('marca') ?: 'Coturnos & Cia';
            $modelo = $request->input('model') ?: $request->input('modelo') ?: $request->input('name') ?: 'Novo Produto';
            $descricao = $request->input('description') ?: $request->input('descricao') ?: 'Calçado tático de alta performance';
            $cor = $request->input('color') ?: $request->input('cor') ?: 'Preto';
            $preco = (float) $request->input('price', $request->input('preco', 0));

            $produto = Product::create([
                'product_type' => substr($tipo, 0, 45),
                'model' => substr($modelo, 0, 45),
                'brand' => substr($marca, 0, 45),
                'description' => substr($descricao, 0, 255),
                'color' => substr($cor, 0, 45),
                'active' => 'ativo',
                'status' => 'ativo',
                'is_deleted' => 'N',
            ]);

            ProductPrice::create([
                'price' => $preco,
                'effective_date' => now(),
                'product_id' => $produto->id,
            ]);

            $grade = $request->input('grade');
            if (is_array($grade) && count($grade) > 0) {
                foreach ($grade as $item) {
                    $tam = (int) ($item['size'] ?? $item['tamanho'] ?? 0);
                    $qtd = (int) ($item['stock'] ?? $item['quantidade'] ?? 0);
                    if ($tam > 0) {
                        ProductSize::create([
                            'product_id' => $produto->id,
                            'size' => $tam,
                            'quantity' => max(0, $qtd),
                        ]);
                    }
                }
            } else {
                $tamanhosLista = $request->input('sizes') ?: $request->input('tamanhos') ?: [37, 38, 39, 40, 41, 42, 43, 44];
                if (is_string($tamanhosLista)) {
                    $tamanhosLista = array_map('intval', explode(',', str_replace(['[', ']', ' '], '', $tamanhosLista)));
                }
                $estoqueTotal = (int) ($request->input('stock') ?: $request->input('estoque') ?: 80);
                $qtdPorTam = max(1, (int) round($estoqueTotal / max(1, count($tamanhosLista))));

                foreach ($tamanhosLista as $tam) {
                    if ((int)$tam > 0) {
                        ProductSize::create([
                            'product_id' => $produto->id,
                            'size' => (int) $tam,
                            'quantity' => $qtdPorTam,
                        ]);
                    }
                }
            }

            $urlImagem = '/coturno1.jpeg';
            $publicId = null;

            if ($request->hasFile('image')) {
                $uploadRes = $this->cloudinaryService->upload($request->file('image'), 'coturnos_produtos');
                $urlImagem = $uploadRes['url'];
                $publicId = $uploadRes['public_id'] ?? null;
            } elseif ($request->filled('image')) {
                $urlImagem = $request->input('image');
                $publicId = $request->input('public_id');
            }

            ProductImage::create([
                'product_id' => $produto->id,
                'image_url' => $urlImagem,
                'public_id' => $publicId,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Produto e grade cadastrados com sucesso!',
                'data' => Product::with(['images', 'prices', 'sizes'])->find($produto->id)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Erro ao cadastrar produto.', $e);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $produto = Product::findByHashid($id);
        if (!$produto) {
            return response()->json(['status' => 'error', 'message' => 'Produto não encontrado.'], 404);
        }

        try {
            DB::beginTransaction();

            $produto->update([
                'model' => substr($request->input('model', $request->input('modelo', $produto->model)), 0, 45),
                'product_type' => substr($request->input('product_type', $request->input('tipo_do_produto', $produto->product_type)), 0, 45),
                'brand' => substr($request->input('brand', $request->input('marca', $produto->brand)), 0, 45),
                'description' => substr($request->input('description', $request->input('descricao', $produto->description)), 0, 255),
                'color' => substr($request->input('color', $request->input('cor', $produto->color)), 0, 45),
            ]);

            if ($request->filled('price') || $request->filled('preco')) {
                ProductPrice::create([
                    'price' => (float) ($request->input('price') ?: $request->input('preco')),
                    'effective_date' => now(),
                    'product_id' => $produto->id,
                ]);
            }

            if ($request->has('grade')) {
                ProductSize::where('product_id', $produto->id)->delete();
                foreach ($request->input('grade', []) as $item) {
                    $tam = (int) ($item['size'] ?? $item['tamanho'] ?? 0);
                    $qtd = (int) ($item['stock'] ?? $item['quantidade'] ?? 0);
                    if ($tam > 0) {
                        ProductSize::create([
                            'product_id' => $produto->id,
                            'size' => $tam,
                            'quantity' => max(0, $qtd),
                        ]);
                    }
                }
            }

            if ($request->hasFile('image')) {
                $uploadRes = $this->cloudinaryService->upload($request->file('image'), 'coturnos_produtos');
                ProductImage::where('product_id', $produto->id)->delete();
                ProductImage::create([
                    'product_id' => $produto->id,
                    'image_url' => $uploadRes['url'],
                    'public_id' => $uploadRes['public_id'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Produto atualizado com sucesso!',
                'data' => Product::with(['images', 'prices', 'sizes'])->find($produto->id)
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Erro ao atualizar produto.', $e);
        }
    }

    public function destroy($id): JsonResponse
    {
        $produto = Product::findByHashid($id);
        if (!$produto) {
            return response()->json(['status' => 'error', 'message' => 'Produto não encontrado.'], 404);
        }

        try {
            DB::beginTransaction();

            $produto->update([
                'is_deleted' => 'S',
                'active' => 'inativo',
                'status' => 'inativo',
            ]);

            $produto->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Produto desativado com sucesso (Soft Delete aplicado).'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Erro ao remover produto.', $e);
        }
    }
}
