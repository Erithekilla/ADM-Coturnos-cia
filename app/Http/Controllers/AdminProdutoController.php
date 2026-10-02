<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Preco;
use App\Models\TamanhoProduto;
use App\Models\ImagensProduto;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminProdutoController extends Controller
{
    protected CloudinaryService $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Endpoint de upload isolado (para testes e homologação).
     * Salva na pasta coturnos_produtos/ e retorna url e public_id.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|image|max:10240', // até 10MB
        ]);

        $file = $request->file('image');
        $result = $this->cloudinaryService->upload($file, 'coturnos_produtos');

        return response()->json([
            'status' => 'success',
            'message' => 'Upload realizado com sucesso na pasta coturnos_produtos!',
            'data' => $result
        ], 200);
    }

    /**
     * POST /api/v1/admin/produtos: Cadastra modelo e grade de estoque completa.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'modelo' => 'required|string|max:45',
            'tipo_do_produto' => 'nullable|string|max:45',
            'marca' => 'nullable|string|max:45',
            'preco' => 'required|numeric|min:0',
            'grade' => 'nullable|array', // Ex: [{"size": 39, "stock": 10}, {"size": 40, "stock": 15}]
            'tamanhos' => 'nullable',
            'image' => 'nullable',
        ]);

        try {
            DB::beginTransaction();

            $tipo = $request->input('tipo_do_produto') ?: $request->input('productType') ?: 'Coturno';
            $marca = $request->input('marca') ?: $request->input('brand') ?: 'Coturnos & Cia';
            $modelo = $request->input('modelo') ?: $request->input('name');
            $descricao = $request->input('descricao') ?: $request->input('description') ?: 'Calçado tático de alta performance';
            $cor = $request->input('cor') ?: $request->input('color') ?: 'Preto';
            $preco = (float) $request->input('preco', $request->input('price', 0));

            $produto = Produto::create([
                'tipo_do_produto' => substr($tipo, 0, 45),
                'modelo' => substr($modelo, 0, 45),
                'marca' => substr($marca, 0, 45),
                'descricao' => substr($descricao, 0, 255),
                'cor' => substr($cor, 0, 45),
                'ativo' => 'ativo',
                'status' => 'ativo',
                'excluido' => 'N',
            ]);

            // 1. Grava Preço
            Preco::create([
                'preco' => $preco,
                'alteracao' => now(),
                'produtos_id' => $produto->id,
            ]);

            // 2. Grava Grade de Tamanhos e Estoque
            $grade = $request->input('grade');
            if (is_array($grade) && count($grade) > 0) {
                foreach ($grade as $item) {
                    $tam = (int) ($item['size'] ?? $item['tamanho'] ?? 0);
                    $qtd = (int) ($item['stock'] ?? $item['quantidade'] ?? 0);
                    if ($tam > 0) {
                        TamanhoProduto::create([
                            'produtos_id' => $produto->id,
                            'tamanho' => $tam,
                            'quantidade' => max(0, $qtd),
                        ]);
                    }
                }
            } else {
                // Fallback padrão se enviou lista simples de tamanhos ou estoque geral
                $tamanhosLista = $request->input('tamanhos') ?: [37, 38, 39, 40, 41, 42, 43, 44];
                if (is_string($tamanhosLista)) {
                    $tamanhosLista = array_map('intval', explode(',', str_replace(['[', ']', ' '], '', $tamanhosLista)));
                }
                $estoqueTotal = (int) ($request->input('estoque') ?: $request->input('stock') ?: 80);
                $qtdPorTam = max(1, (int) round($estoqueTotal / max(1, count($tamanhosLista))));

                foreach ($tamanhosLista as $tam) {
                    if ((int)$tam > 0) {
                        TamanhoProduto::create([
                            'produtos_id' => $produto->id,
                            'tamanho' => (int) $tam,
                            'quantidade' => $qtdPorTam,
                        ]);
                    }
                }
            }

            // 3. Upload organizado no Cloudinary ou URL enviada
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

            ImagensProduto::create([
                'produtos_id' => $produto->id,
                'url_imagem' => $urlImagem,
                'public_id' => $publicId,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Produto e grade cadastrados com sucesso!',
                'data' => Produto::with(['imagens', 'precos', 'tamanhos'])->find($produto->id)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao cadastrar produto.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/v1/admin/produtos/{id}: Atualiza dados e grade.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $produto = Produto::find($id);
        if (!$produto) {
            return response()->json(['status' => 'error', 'message' => 'Produto não encontrado.'], 404);
        }

        try {
            DB::beginTransaction();

            $produto->update([
                'modelo' => substr($request->input('modelo', $produto->modelo), 0, 45),
                'tipo_do_produto' => substr($request->input('tipo_do_produto', $produto->tipo_do_produto), 0, 45),
                'marca' => substr($request->input('marca', $produto->marca), 0, 45),
                'descricao' => substr($request->input('descricao', $produto->descricao), 0, 255),
                'cor' => substr($request->input('cor', $produto->cor), 0, 45),
            ]);

            if ($request->filled('preco')) {
                Preco::create([
                    'preco' => (float) $request->input('preco'),
                    'alteracao' => now(),
                    'produtos_id' => $produto->id,
                ]);
            }

            if ($request->has('grade')) {
                TamanhoProduto::where('produtos_id', $produto->id)->delete();
                foreach ($request->input('grade', []) as $item) {
                    $tam = (int) ($item['size'] ?? $item['tamanho'] ?? 0);
                    $qtd = (int) ($item['stock'] ?? $item['quantidade'] ?? 0);
                    if ($tam > 0) {
                        TamanhoProduto::create([
                            'produtos_id' => $produto->id,
                            'tamanho' => $tam,
                            'quantidade' => max(0, $qtd),
                        ]);
                    }
                }
            }

            if ($request->hasFile('image')) {
                $uploadRes = $this->cloudinaryService->upload($request->file('image'), 'coturnos_produtos');
                ImagensProduto::where('produtos_id', $produto->id)->delete();
                ImagensProduto::create([
                    'produtos_id' => $produto->id,
                    'url_imagem' => $uploadRes['url'],
                    'public_id' => $uploadRes['public_id'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Produto atualizado com sucesso!',
                'data' => Produto::with(['imagens', 'precos', 'tamanhos'])->find($produto->id)
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao atualizar produto.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/admin/produtos/{id}: Aplica Soft Delete ($produto->delete() e excluido = 'S').
     */
    public function destroy($id): JsonResponse
    {
        $produto = Produto::find($id);
        if (!$produto) {
            return response()->json(['status' => 'error', 'message' => 'Produto não encontrado.'], 404);
        }

        try {
            DB::beginTransaction();

            $produto->update([
                'excluido' => 'S',
                'ativo' => 'inativo',
                'status' => 'inativo',
            ]);

            // Se o model tiver SoftDeletes nativo, chama delete()
            $produto->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Produto desativado com sucesso (Soft Delete aplicado).'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao remover produto.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
}
