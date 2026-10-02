<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Produto;
use App\Models\Preco;
use App\Models\ImagensProduto;
use App\Models\TamanhoProduto;
use Illuminate\Support\Facades\DB;

class ProdutoSeeder extends Seeder
{
    /**
     * Seed produtos baseados no front-end.
     */
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
                'marca' => 'borcegui',
                'cor' => 'marrom',
                'material' => 'Couro Legítimo',
                'terreno' => 'Trilha/Lama',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Trilha PRO',
                'price' => 'R$ 349,90',
                'image' => '/coturno3.jpeg',
                'hoverImage' => '/coturno4.jpeg',
            ],
            [
                'id' => 8,
                'category' => 'classicos',
                'tipo' => 'coturno',
                'marca' => 'coturno-br',
                'cor' => 'preto',
                'material' => 'Lona Reforçada',
                'terreno' => 'Selva/Militar',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno BM Clássico',
                'price' => 'R$ 289,90',
                'image' => '/coturno5.jpeg',
                'hoverImage' => '/coturno3.jpeg',
            ],
            [
                'id' => 9,
                'category' => 'classicos',
                'tipo' => 'coturno',
                'marca' => 'militar-pro',
                'cor' => 'bege',
                'material' => 'Nobuck',
                'terreno' => 'Deserto/Areia',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Coturno Desert',
                'price' => 'R$ 199,90',
                'image' => '/coturno1.jpeg',
                'hoverImage' => '/coturno2.jpg',
            ],
            [
                'id' => 10,
                'category' => 'classicos',
                'tipo' => 'bota-tatica',
                'marca' => 'tacforce',
                'cor' => 'cinza',
                'material' => 'Nylon 1000D',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [37, 38, 39, 40, 41, 42, 43, 44],
                'name' => 'Bota Tática Leve',
                'price' => 'R$ 179,90',
                'image' => '/coturno4.jpeg',
                'hoverImage' => '/coturno5.jpeg',
            ],
            [
                'id' => 11,
                'category' => 'promocao',
                'tipo' => 'coturno',
                'marca' => 'borcegui',
                'cor' => 'verde',
                'material' => 'Lona Reforçada',
                'terreno' => 'Selva/Militar',
                'tamanhos' => [40, 41, 42, 43, 44, 45],
                'name' => 'Coturno Jungle',
                'price' => 'R$ 219,90',
                'image' => '/coturno3.jpeg',
                'hoverImage' => '/coturno4.jpeg',
            ],
            [
                'id' => 12,
                'category' => 'promocao',
                'tipo' => 'bota-tatica',
                'marca' => 'militar-pro',
                'cor' => 'preto',
                'material' => 'Sintético Premium',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [38, 39, 40, 41, 42],
                'name' => 'Bota Operacional',
                'price' => 'R$ 259,90',
                'image' => '/coturno4.jpeg',
                'hoverImage' => '/coturno5.jpeg',
            ],
            [
                'id' => 13,
                'category' => 'promocao',
                'tipo' => 'sapato-social',
                'marca' => 'urbangear',
                'cor' => 'marrom',
                'material' => 'Couro Legítimo',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [39, 40, 41, 42, 43],
                'name' => 'Tênis Tático Fast',
                'price' => 'R$ 389,90',
                'image' => '/coturno5.jpeg',
                'hoverImage' => '/coturno3.jpeg',
            ],
            [
                'id' => 14,
                'category' => 'promocao',
                'tipo' => 'sapato-social',
                'marca' => 'coturno-br',
                'cor' => 'cinza',
                'material' => 'Nylon 1000D',
                'terreno' => 'Asfalto/Urbano',
                'tamanhos' => [40, 41, 42],
                'name' => 'Tênis Tático Fast II',
                'price' => 'R$ 389,90',
                'image' => '/coturno5.jpeg',
                'hoverImage' => '/coturno1.jpeg',
            ],
            [
                'id' => 15,
                'category' => 'promocao',
                'tipo' => 'coturno',
                'marca' => 'tacforce',
                'cor' => 'preto',
                'material' => 'Couro Legítimo',
                'terreno' => 'Selva/Militar',
                'tamanhos' => [41, 42, 43, 44, 45],
                'name' => 'Coturno BM Clássico Pro',
                'price' => 'R$ 289,90',
                'image' => '/coturno1.jpeg',
                'hoverImage' => '/coturno2.jpg',
            ],
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        TamanhoProduto::truncate();
        ImagensProduto::truncate();
        Preco::truncate();
        Produto::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        foreach ($produtosFrontend as $item) {
            $clean = str_replace(['R$', ' '], '', $item['price']);
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
            $precoNum = (float) $clean;

            $produto = Produto::create([
                'id' => $item['id'],
                'tipo_do_produto' => substr($item['tipo'] ?? 'coturno', 0, 45),
                'modelo' => substr($item['name'], 0, 45),
                'marca' => substr($item['marca'] ?? 'Coturno & Cia', 0, 45),
                'descricao' => substr(($item['material'] ?? '') . ' | ' . ($item['terreno'] ?? '') . ' | Cor: ' . ($item['cor'] ?? ''), 0, 255),
                'ativo' => 1,
            ]);

            Preco::create([
                'preco' => $precoNum,
                'alteracao' => now(),
                'produtos_id' => $produto->id,
            ]);

            if (!empty($item['image'])) {
                ImagensProduto::create([
                    'url_imagem' => $item['image'],
                    'produtos_id' => $produto->id,
                ]);
            }
            if (!empty($item['hoverImage'])) {
                ImagensProduto::create([
                    'url_imagem' => $item['hoverImage'],
                    'produtos_id' => $produto->id,
                ]);
            }

            if (!empty($item['tamanhos']) && is_array($item['tamanhos'])) {
                foreach ($item['tamanhos'] as $tamanho) {
                    TamanhoProduto::create([
                        'produtos_id' => $produto->id,
                        'tamanho' => (int) $tamanho,
                        'quantidade' => 20,
                    ]);
                }
            }
        }
    }
}

