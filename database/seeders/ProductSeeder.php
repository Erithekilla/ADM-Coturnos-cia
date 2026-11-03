<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductImage;
use App\Models\ProductSize;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $produtosFrontend = [
            [
                'id' => 1,
                'category' => 'lancamentos',
                'tipo' => 'coturno',
                'marca' => 'tacforce',
                'cor' => 'preto',
                'material' => 'Couro Legítimo',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Tático Airstrike',
                'price' => 'R$ 649,90',
                'image' => '/coturno1.jpeg',
                'hoverImage' => '/coturno2.jpg',
            ],
            [
                'id' => 2,
                'category' => 'lancamentos',
                'tipo' => 'bota-tatica',
                'marca' => 'militar-pro',
                'cor' => 'verde',
                'material' => 'Nylon 1000D',
                'terreno' => 'Trilha/Lama',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Bota Anfíbia X',
                'price' => 'R$ 529,90',
                'image' => '/coturno1.jpeg',
                'hoverImage' => '/coturno2.jpg',
            ],
            [
                'id' => 3,
                'category' => 'lancamentos',
                'tipo' => 'coturno',
                'marca' => 'urbangear',
                'cor' => 'preto',
                'material' => 'Couro Legítimo',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Urbano Ghost',
                'price' => 'R$ 499,90',
                'image' => '/coturno3.jpeg',
                'hoverImage' => '/coturno4.jpeg',
            ],
            [
                'id' => 4,
                'category' => 'lancamentos',
                'tipo' => 'coturno',
                'marca' => 'urbangear',
                'cor' => 'marrom',
                'material' => 'Couro Legítimo Premium',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Tático Trooper',
                'price' => 'R$ 459,90',
                'image' => '/coturno5.jpeg',
                'hoverImage' => '/coturno3.jpeg',
            ],
            [
                'id' => 5,
                'category' => 'lancamentos',
                'tipo' => 'coturno',
                'marca' => 'coturno-br',
                'cor' => 'verde',
                'material' => 'Lona Reforçada',
                'terreno' => 'Selva/Militar',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Militar Padrão',
                'price' => 'R$ 299,90',
                'image' => '/coturno4.jpeg',
                'hoverImage' => '/coturno5.jpeg',
            ],
            [
                'id' => 6,
                'category' => 'classicos',
                'tipo' => 'bota-tatica',
                'marca' => 'borcegui',
                'cor' => 'preto',
                'material' => 'Couro Legítimo',
                'terreno' => 'Trilha/Lama',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Bota de Segurança Bruta',
                'price' => 'R$ 259,90',
                'image' => '/coturno1.jpeg',
                'hoverImage' => '/coturno2.jpg',
            ],
            [
                'id' => 7,
                'category' => 'classicos',
                'tipo' => 'coturno',
                'marca' => 'coturno-br',
                'cor' => 'preto',
                'material' => 'Couro Legítimo',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Clássico Policial',
                'price' => 'R$ 389,90',
                'image' => '/coturno2.jpg',
                'hoverImage' => '/coturno3.jpeg',
            ],
            [
                'id' => 8,
                'category' => 'classicos',
                'tipo' => 'coturno',
                'marca' => 'tacforce',
                'cor' => 'preto',
                'material' => 'Couro e Cordura',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Operacional Black',
                'price' => 'R$ 419,90',
                'image' => '/coturno4.jpeg',
                'hoverImage' => '/coturno5.jpeg',
            ],
            [
                'id' => 9,
                'category' => 'classicos',
                'tipo' => 'coturno',
                'marca' => 'urbangear',
                'cor' => 'areia',
                'material' => 'Camurça e Nylon',
                'terreno' => 'Deserto/Areia',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Desert Storm',
                'price' => 'R$ 479,90',
                'image' => '/coturno5.jpeg',
                'hoverImage' => '/coturno1.jpeg',
            ],
            [
                'id' => 10,
                'category' => 'destaques',
                'tipo' => 'coturno',
                'marca' => 'tacforce',
                'cor' => 'preto',
                'material' => 'Couro Hidrofugado',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Tático Invictus',
                'price' => 'R$ 689,90',
                'image' => '/coturno3.jpeg',
                'hoverImage' => '/coturno4.jpeg',
            ],
            [
                'id' => 11,
                'category' => 'destaques',
                'tipo' => 'bota-tatica',
                'marca' => 'militar-pro',
                'cor' => 'verde',
                'material' => 'Ripstop e Borracha',
                'terreno' => 'Selva/Militar',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Bota Tática Jungle Pro',
                'price' => 'R$ 559,90',
                'image' => '/coturno2.jpg',
                'hoverImage' => '/coturno1.jpeg',
            ],
            [
                'id' => 12,
                'category' => 'destaques',
                'tipo' => 'coturno',
                'marca' => 'coturno-br',
                'cor' => 'preto',
                'material' => 'Couro Legítimo Nobuk',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Resgate Rápido',
                'price' => 'R$ 399,90',
                'image' => '/coturno4.jpeg',
                'hoverImage' => '/coturno5.jpeg',
            ],
            [
                'id' => 13,
                'category' => 'mais-vendidos',
                'tipo' => 'coturno',
                'marca' => 'tacforce',
                'cor' => 'preto',
                'material' => 'Couro Legítimo',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Force One Black',
                'price' => 'R$ 599,90',
                'image' => '/coturno1.jpeg',
                'hoverImage' => '/coturno2.jpg',
            ],
            [
                'id' => 14,
                'category' => 'mais-vendidos',
                'tipo' => 'coturno',
                'marca' => 'urbangear',
                'cor' => 'marrom',
                'material' => 'Couro Bovino 100%',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Urbano Vintage',
                'price' => 'R$ 489,90',
                'image' => '/coturno5.jpeg',
                'hoverImage' => '/coturno3.jpeg',
            ],
            [
                'id' => 15,
                'category' => 'mais-vendidos',
                'tipo' => 'bota-tatica',
                'marca' => 'militar-pro',
                'cor' => 'preto',
                'material' => 'Nylon Balístico',
                'terreno' => 'Trilha/Lama',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Bota Tática Tork 8.0',
                'price' => 'R$ 519,90',
                'image' => '/coturno2.jpg',
                'hoverImage' => '/coturno4.jpeg',
            ],
        ];

        foreach ($produtosFrontend as $prodData) {
            $precoLimpo = (float) str_replace(['R$', ' ', '.', ','], ['', '', '', '.'], $prodData['price']);

            $produto = Product::updateOrCreate(
                ['id' => $prodData['id']],
                [
                    'product_type' => substr($prodData['tipo'], 0, 45),
                    'model' => substr($prodData['name'], 0, 45),
                    'brand' => substr($prodData['marca'], 0, 45),
                    'description' => substr("{$prodData['material']} | Terreno: {$prodData['terreno']} | Categoria: {$prodData['category']}", 0, 255),
                    'color' => substr($prodData['cor'], 0, 45),
                    'active' => 'ativo',
                    'status' => 'ativo',
                    'is_deleted' => 'N',
                ]
            );

            ProductPrice::updateOrCreate(
                ['product_id' => $produto->id],
                [
                    'price' => $precoLimpo,
                    'effective_date' => now(),
                ]
            );

            ProductImage::updateOrCreate(
                ['product_id' => $produto->id, 'image_url' => $prodData['image']],
                ['image_url' => $prodData['image']]
            );

            if (!empty($prodData['hoverImage'])) {
                ProductImage::updateOrCreate(
                    ['product_id' => $produto->id, 'image_url' => $prodData['hoverImage']],
                    ['image_url' => $prodData['hoverImage']]
                );
            }

            foreach ($prodData['tamanhos'] as $tam) {
                ProductSize::updateOrCreate(
                    [
                        'product_id' => $produto->id,
                        'size' => (int) $tam,
                    ],
                    [
                        'quantity' => 15,
                    ]
                );
            }
        }
    }
}

