<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSize;
use App\Models\ProductImage;
use App\Models\CartItem;
use App\Models\CollectionProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
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

        $products = $query->get();

        $formatted = $products->map(function ($p) {
            return $this->formatProduct($p);
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
            'total' => $formatted->count(),
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

        $model = $request->input('model') ?: $request->input('name') ?: $request->input('modelo');
        $type = $request->input('product_type') ?: $request->input('productType') ?: $request->input('tipo_do_produto') ?: 'Coturno';
        $brand = $request->input('brand') ?: $request->input('marca') ?: 'Coturnos & Cia';
        $description = $request->input('description') ?: $request->input('descricao') ?: '';
        $color = $request->input('color') ?: $request->input('cor') ?: 'Preto';
        $activeRaw = $request->input('active') ?: $request->input('ativo') ?: $request->input('status') ?: 'ativo';
        $active = strtolower((string)$activeRaw) === 'inativo' ? 'inativo' : 'ativo';

        if (empty($description)) {
            $description = "Calçado de alta resistência | Cor: {$color}";
        }

        $priceValue = (float) ($request->input('price') ?: $request->input('preco') ?: 0);
        $totalStock = (int) ($request->input('stock') ?: $request->input('estoque') ?: 20);
        $imageUrl = $request->input('image') ?: $request->input('imagem') ?: '/coturno1.jpeg';

        $sizesReceived = $request->input('tamanhos_array') ?: $request->input('sizes') ?: $request->input('tamanhos') ?: [38, 39, 40, 41, 42, 43, 44];
        if (is_string($sizesReceived)) {
            $sizesReceived = array_map('intval', explode(',', str_replace(['[', ']', ' '], '', $sizesReceived)));
        }

        try {
            DB::beginTransaction();

            $product = Product::create([
                'product_type' => substr($type, 0, 45),
                'model' => substr($model ?: 'Novo Produto', 0, 45),
                'brand' => substr($brand, 0, 45),
                'description' => substr($description, 0, 255),
                'color' => substr($color ?: 'Preto', 0, 45),
                'active' => $active,
                'status' => $active,
                'is_deleted' => 'N',
            ]);

            ProductPrice::create([
                'price' => $priceValue,
                'effective_date' => now(),
                'product_id' => $product->id,
            ]);

            if (is_array($sizesReceived) && count($sizesReceived) > 0) {
                $qtyPerSize = max(1, (int) round($totalStock / count($sizesReceived)));
                foreach ($sizesReceived as $size) {
                    $sizeNum = (int) $size;
                    if ($sizeNum > 0) {
                        ProductSize::create([
                            'product_id' => $product->id,
                            'size' => $sizeNum,
                            'quantity' => $qtyPerSize,
                        ]);
                    }
                }
            }

            if (!empty($imageUrl)) {
                ProductImage::create([
                    'image_url' => $imageUrl,
                    'product_id' => $product->id,
                ]);
            }

            DB::commit();

            $loadedProduct = Product::with(['images', 'prices', 'sizes'])->find($product->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Product and sizes created successfully!',
                'data' => $this->formatProduct($loadedProduct),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Failed to save product in database.', $e);
        }
    }

    public function show($id): JsonResponse
    {
        $product = Product::findByHashid($id, ['images', 'prices', 'sizes']);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatProduct($product),
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $product = Product::findByHashid($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $model = $request->input('model') ?: $request->input('name') ?: $request->input('modelo') ?: $product->model;
            $type = $request->input('product_type') ?: $request->input('productType') ?: $request->input('tipo_do_produto') ?: $product->product_type;
            $brand = $request->input('brand') ?: $request->input('marca') ?: $product->brand;
            $description = $request->input('description') ?: $request->input('descricao') ?: $product->description;
            $color = $request->input('color') ?: $request->input('cor') ?: $product->color ?: 'Preto';

            $active = $product->active ?: 'ativo';
            if ($request->has('active') || $request->has('ativo') || $request->has('status')) {
                $val = $request->input('active') ?: $request->input('ativo') ?: $request->input('status');
                $active = strtolower((string)$val) === 'inativo' ? 'inativo' : 'ativo';
            }

            $product->update([
                'product_type' => substr($type, 0, 45),
                'model' => substr($model, 0, 45),
                'brand' => substr($brand, 0, 45),
                'description' => substr($description, 0, 255),
                'color' => substr($color, 0, 45),
                'active' => $active,
                'status' => $active,
            ]);

            if ($request->has('price') || $request->has('preco')) {
                $priceValue = (float) ($request->input('price') ?: $request->input('preco'));
                ProductPrice::create([
                    'price' => $priceValue,
                    'effective_date' => now(),
                    'product_id' => $product->id,
                ]);
            }

            if ($request->has('sizes') || $request->has('tamanhos') || $request->has('tamanhos_array')) {
                $sizesReceived = $request->input('tamanhos_array') ?: $request->input('sizes') ?: $request->input('tamanhos');
                if (is_string($sizesReceived)) {
                    $sizesReceived = array_map('intval', explode(',', str_replace(['[', ']', ' '], '', $sizesReceived)));
                }

                if (is_array($sizesReceived)) {
                    ProductSize::where('product_id', $product->id)->delete();
                    $totalStock = (int) ($request->input('stock') ?: $request->input('estoque') ?: 20);
                    $qtyPerSize = max(1, (int) round($totalStock / max(1, count($sizesReceived))));

                    foreach ($sizesReceived as $size) {
                        $sizeNum = (int) $size;
                        if ($sizeNum > 0) {
                            ProductSize::create([
                                'product_id' => $product->id,
                                'size' => $sizeNum,
                                'quantity' => $qtyPerSize,
                            ]);
                        }
                    }
                }
            }

            if ($request->has('image') || $request->has('imagem')) {
                $imageUrl = $request->input('image') ?: $request->input('imagem');
                if (!empty($imageUrl)) {
                    ProductImage::where('product_id', $product->id)->delete();
                    ProductImage::create([
                        'image_url' => $imageUrl,
                        'product_id' => $product->id,
                    ]);
                }
            }

            DB::commit();

            $updatedProduct = Product::with(['images', 'prices', 'sizes'])->find($product->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully!',
                'data' => $this->formatProduct($updatedProduct),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Failed to update product.', $e);
        }
    }

    public function destroy($id): JsonResponse
    {
        $product = Product::findByHashid($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            CartItem::where('product_id', $product->id)->delete();
            CollectionProduct::where('product_id', $product->id)->delete();

            $product->forceFill([
                'is_deleted' => 'S',
                'active' => 'inativo',
                'status' => 'inativo',
            ])->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product removed successfully!',
                'data' => ['id' => $product->hashid]
            ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Error deleting product.', $e);
        }
    }

    private function formatProduct(Product $p): array
    {
        $recentPrice = $p->prices->sortByDesc('effective_date')->first();
        $price = $recentPrice ? (float) $recentPrice->price : 0;

        $sizesCollection = $p->sizes->sortBy('size');
        $sizesArray = $sizesCollection->pluck('size')->values()->toArray();
        $sizesText = count($sizesArray) > 0 ? implode(', ', $sizesArray) : '38-44';

        $stock = $sizesCollection->sum('quantity') ?: 20;
        $image = $p->images->first()->image_url ?? '/coturno1.jpeg';

        $color = $p->color ?: 'Preto';
        if ($color === 'Preto' && preg_match('/Cor:\s*([^|]+)/i', (string) $p->description, $matches)) {
            $color = trim($matches[1]);
        }

        $isAtivo = strtolower((string)$p->active) !== 'inativo' && strtolower((string)$p->status) !== 'inativo';

        return [
            'id' => $p->hashid,
            'name' => $p->model,
            'productType' => $p->product_type,
            'product_type' => $p->product_type,
            'model' => $p->model,
            'brand' => $p->brand,
            'description' => $p->description,
            'active' => $isAtivo ? 'Ativo' : 'Inativo',
            'ativo' => $isAtivo ? 'ativo' : 'inativo',
            'status' => $isAtivo ? 'ativo' : 'inativo',
            'price' => $price,
            'stock' => $stock,
            'sizes' => $sizesText,
            'tamanhos_array' => $sizesArray,
            'color' => $color,
            'image' => $image,
        ];
    }
}

