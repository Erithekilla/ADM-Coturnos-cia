<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        // 1. Drop old foreign keys if present
        $legacyFks = [
            ['carrinho', 'fk_carrinho_usuario1'],
            ['colecao_has_produtos', 'fk_colecao_has_produtos_colecao1'],
            ['colecao_has_produtos', 'fk_colecao_has_produtos_produtos1'],
            ['endereco', 'fk_endereco_usuario1'],
            ['historico_pedido', 'fk_historico_pedido_pedido1'],
            ['imagens_banner', 'fk_imagens_banner_banner1'],
            ['imagens_colecao', 'fk_imagens_colecao_colecao1'],
            ['imagens_produto', 'fk_imagens_produto_produtos1'],
            ['itens_carrinho', 'fk_carrinho_has_produtos_carrinho1'],
            ['itens_carrinho', 'fk_carrinho_has_produtos_produtos1'],
            ['itens_pedidos', 'fk_itens_pedidos_pedido1'],
            ['itens_pedidos', 'fk_itens_pedidos_produtos1'],
            ['pagamento', 'fk_compra_pedido1'],
            ['pedido', 'fk_pedido_endereco1'],
            ['pedido', 'fk_pedido_usuario1'],
            ['precos', 'fk_precos_produtos1'],
            ['promocao_produtos', 'fk_promocao_produtos_precos1'],
            ['tamanho_produtos', 'fk_tamanho_produtos_produtos1'],
        ];

        foreach ($legacyFks as [$tbl, $fk]) {
            if ($isMysql && Schema::hasTable($tbl)) {
                try {
                    DB::statement("ALTER TABLE `{$tbl}` DROP FOREIGN KEY `{$fk}`");
                } catch (\Throwable $e) {
                }
            }
        }

        // Helper functions
        $renameCol = function (string $table, string $old, string $new, string $definition) use ($isMysql) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $old) && !Schema::hasColumn($table, $new)) {
                if ($isMysql) {
                    DB::statement("ALTER TABLE `{$table}` CHANGE COLUMN `{$old}` `{$new}` {$definition}");
                } else {
                    Schema::table($table, function (Blueprint $t) use ($old, $new) {
                        $t->renameColumn($old, $new);
                    });
                }
            }
        };

        $renameTbl = function (string $old, string $new) {
            if (Schema::hasTable($old) && !Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        };

        // --- Table 1: usuario -> users ---
        if (Schema::hasTable('usuario')) {
            $renameCol('usuario', 'nome', 'first_name', 'VARCHAR(45) NOT NULL');
            $renameCol('usuario', 'sobrenome', 'last_name', 'VARCHAR(45) NOT NULL');
            $renameCol('usuario', 'data_nascimento', 'birth_date', 'VARCHAR(512) NULL');
            $renameCol('usuario', 'data_criacao', 'created_at', 'DATETIME NOT NULL');
            $renameCol('usuario', 'senha', 'password', 'VARCHAR(255) NULL');
            $renameCol('usuario', 'telefone', 'phone', 'VARCHAR(512) NULL');
            $renameCol('usuario', 'excluido', 'is_deleted', "VARCHAR(25) NOT NULL DEFAULT 'N'");
            if (!Schema::hasColumn('usuario', 'updated_at')) {
                if ($isMysql) {
                    DB::statement("ALTER TABLE `usuario` ADD COLUMN `updated_at` TIMESTAMP NULL AFTER `created_at`");
                } else {
                    Schema::table('usuario', function (Blueprint $t) {
                        $t->timestamp('updated_at')->nullable();
                    });
                }
            }
            $renameTbl('usuario', 'users');
        } elseif (Schema::hasTable('users')) {
            // Also ensure columns on users are named in English
            $renameCol('users', 'nome', 'first_name', 'VARCHAR(45) NOT NULL');
            $renameCol('users', 'sobrenome', 'last_name', 'VARCHAR(45) NOT NULL');
            $renameCol('users', 'data_nascimento', 'birth_date', 'VARCHAR(512) NULL');
            $renameCol('users', 'data_criacao', 'created_at', 'DATETIME NOT NULL');
            $renameCol('users', 'senha', 'password', 'VARCHAR(255) NULL');
            $renameCol('users', 'telefone', 'phone', 'VARCHAR(512) NULL');
            $renameCol('users', 'excluido', 'is_deleted', "VARCHAR(25) NOT NULL DEFAULT 'N'");
            if (!Schema::hasColumn('users', 'updated_at')) {
                Schema::table('users', function (Blueprint $t) {
                    $t->timestamp('updated_at')->nullable();
                });
            }
        }

        // --- Table 2: endereco -> addresses ---
        if (Schema::hasTable('endereco')) {
            $renameCol('endereco', 'usuario_id', 'user_id', 'INT NOT NULL');
            $renameCol('endereco', 'cep', 'zip_code', 'VARCHAR(8) NOT NULL');
            $renameCol('endereco', 'rua', 'street', 'VARCHAR(45) NOT NULL');
            $renameCol('endereco', 'bairro', 'neighborhood', 'VARCHAR(45) NOT NULL');
            $renameCol('endereco', 'cidade', 'city', 'VARCHAR(45) NOT NULL');
            $renameCol('endereco', 'estado', 'state', 'VARCHAR(45) NOT NULL');
            $renameCol('endereco', 'numero', 'number', 'VARCHAR(45) NOT NULL');
            $renameCol('endereco', 'complemento', 'complement', 'VARCHAR(45) NULL');
            $renameCol('endereco', 'padrao', 'is_default', 'TINYINT NOT NULL DEFAULT 0');
            $renameTbl('endereco', 'addresses');
        }

        // --- Table 3: produtos -> products ---
        if (Schema::hasTable('produtos')) {
            $renameCol('produtos', 'tipo_do_produto', 'product_type', 'VARCHAR(45) NOT NULL');
            $renameCol('produtos', 'modelo', 'model', 'VARCHAR(45) NOT NULL');
            $renameCol('produtos', 'marca', 'brand', 'VARCHAR(45) NOT NULL');
            $renameCol('produtos', 'descricao', 'description', 'VARCHAR(255) NOT NULL');
            $renameCol('produtos', 'ativo', 'active', "VARCHAR(15) NOT NULL DEFAULT 'ativo'");
            $renameCol('produtos', 'cor', 'color', 'VARCHAR(45) NOT NULL');
            $renameCol('produtos', 'excluido', 'is_deleted', "VARCHAR(25) NOT NULL DEFAULT 'N'");
            $renameTbl('produtos', 'products');
        }

        // --- Table 4: tamanho_produtos -> product_sizes ---
        if (Schema::hasTable('tamanho_produtos')) {
            $renameCol('tamanho_produtos', 'produtos_id', 'product_id', 'INT NOT NULL');
            $renameCol('tamanho_produtos', 'tamanho', 'size', 'INT NOT NULL');
            $renameCol('tamanho_produtos', 'quantidade', 'quantity', 'INT NOT NULL');
            $renameTbl('tamanho_produtos', 'product_sizes');
        }

        // --- Table 5: imagens_produto -> product_images ---
        if (Schema::hasTable('imagens_produto')) {
            $renameCol('imagens_produto', 'id_imagens_produto', 'id', 'INT NOT NULL AUTO_INCREMENT');
            $renameCol('imagens_produto', 'url_imagem', 'image_url', 'VARCHAR(255) NOT NULL');
            $renameCol('imagens_produto', 'produtos_id', 'product_id', 'INT NOT NULL');
            $renameTbl('imagens_produto', 'product_images');
        }

        // --- Table 6: precos -> product_prices ---
        if (Schema::hasTable('precos')) {
            $renameCol('precos', 'id_precos', 'id', 'INT NOT NULL AUTO_INCREMENT');
            $renameCol('precos', 'preco', 'price', 'DECIMAL(10,2) NOT NULL');
            $renameCol('precos', 'alteracao', 'effective_date', 'DATETIME NOT NULL');
            $renameCol('precos', 'produtos_id', 'product_id', 'INT NOT NULL');
            $renameTbl('precos', 'product_prices');
        }

        // --- Table 7: promocao_produtos -> product_promotions ---
        if (Schema::hasTable('promocao_produtos')) {
            $renameCol('promocao_produtos', 'valor_promo', 'promotional_price', 'DECIMAL(10,2) NOT NULL');
            $renameCol('promocao_produtos', 'data_inicio', 'start_date', 'DATE NOT NULL');
            $renameCol('promocao_produtos', 'data_fim', 'end_date', 'DATE NOT NULL');
            $renameCol('promocao_produtos', 'ativo', 'active', "VARCHAR(15) NOT NULL DEFAULT 'ativo'");
            $renameCol('promocao_produtos', 'precos_id_precos', 'price_id', 'INT NOT NULL');
            $renameTbl('promocao_produtos', 'product_promotions');
        }

        // --- Table 8: colecao -> collections ---
        if (Schema::hasTable('colecao')) {
            $renameCol('colecao', 'id_colecao', 'id', 'INT NOT NULL AUTO_INCREMENT');
            $renameCol('colecao', 'nome_colecao', 'name', 'VARCHAR(45) NOT NULL');
            $renameCol('colecao', 'descricao_colecao', 'description', 'VARCHAR(45) NOT NULL');
            $renameCol('colecao', 'data_inicio', 'start_date', 'DATETIME NOT NULL');
            $renameCol('colecao', 'data_fim', 'end_date', 'DATETIME NOT NULL');
            $renameTbl('colecao', 'collections');
        }

        // --- Table 9: colecao_has_produtos -> collection_products ---
        if (Schema::hasTable('colecao_has_produtos')) {
            $renameCol('colecao_has_produtos', 'id_colecao', 'collection_id', 'INT NOT NULL');
            $renameCol('colecao_has_produtos', 'produtos_id', 'product_id', 'INT NOT NULL');
            $renameTbl('colecao_has_produtos', 'collection_products');
        }

        // --- Table 10: imagens_colecao -> collection_images ---
        if (Schema::hasTable('imagens_colecao')) {
            $renameCol('imagens_colecao', 'id_imagens_colecao', 'id', 'INT NOT NULL AUTO_INCREMENT');
            $renameCol('imagens_colecao', 'url_imagem', 'image_url', 'VARCHAR(45) NOT NULL');
            $renameCol('imagens_colecao', 'id_colecao', 'collection_id', 'INT NOT NULL');
            $renameTbl('imagens_colecao', 'collection_images');
        }

        // --- Table 11: banner -> banners ---
        if (Schema::hasTable('banner')) {
            $renameCol('banner', 'idbanner', 'id', 'INT NOT NULL AUTO_INCREMENT');
            $renameCol('banner', 'ativo', 'active', "VARCHAR(15) NOT NULL DEFAULT 'ativo'");
            $renameTbl('banner', 'banners');
        }

        // --- Table 12: imagens_banner -> banner_images ---
        if (Schema::hasTable('imagens_banner')) {
            $renameCol('imagens_banner', 'id_imagens_banner', 'id', 'INT NOT NULL AUTO_INCREMENT');
            $renameCol('imagens_banner', 'url_imagem', 'image_url', 'VARCHAR(45) NOT NULL');
            $renameCol('imagens_banner', 'banner_idbanner', 'banner_id', 'INT NOT NULL');
            $renameTbl('imagens_banner', 'banner_images');
        }

        // --- Table 13: carrinho -> carts ---
        if (Schema::hasTable('carrinho')) {
            $renameCol('carrinho', 'id_carrinho', 'id', 'INT NOT NULL AUTO_INCREMENT');
            $renameCol('carrinho', 'usuario_id', 'user_id', 'INT NOT NULL');
            $renameCol('carrinho', 'criado_em', 'created_at', 'DATE NOT NULL');
            $renameCol('carrinho', 'atualizado_em', 'updated_at', 'DATE NOT NULL');
            $renameTbl('carrinho', 'carts');
        }

        // --- Table 14: itens_carrinho -> cart_items ---
        if (Schema::hasTable('itens_carrinho')) {
            $renameCol('itens_carrinho', 'carrinho_id_carrinho', 'cart_id', 'INT NOT NULL');
            $renameCol('itens_carrinho', 'produtos_id', 'product_id', 'INT NOT NULL');
            $renameCol('itens_carrinho', 'quantidade', 'quantity', 'INT NOT NULL');
            $renameCol('itens_carrinho', 'tamanho', 'size', 'INT NOT NULL');
            $renameTbl('itens_carrinho', 'cart_items');
        }

        // --- Table 15: pedido -> orders ---
        if (Schema::hasTable('pedido')) {
            $renameCol('pedido', 'valor_total', 'total_amount', 'DECIMAL(10,2) NOT NULL');
            $renameCol('pedido', 'codigo_rastreio', 'tracking_code', 'VARCHAR(100) NULL');
            $renameCol('pedido', 'data_pedido', 'order_date', 'DATETIME NOT NULL');
            $renameCol('pedido', 'usuario_id', 'user_id', 'INT NOT NULL');
            $renameCol('pedido', 'endereco_id', 'address_id', 'INT NOT NULL');
            $renameTbl('pedido', 'orders');
        }

        // --- Table 16: itens_pedidos -> order_items ---
        if (Schema::hasTable('itens_pedidos')) {
            $renameCol('itens_pedidos', 'quantidade', 'quantity', 'INT NOT NULL');
            $renameCol('itens_pedidos', 'tamanho', 'size', 'INT NOT NULL');
            $renameCol('itens_pedidos', 'preco_unitario', 'unit_price', 'DECIMAL(10,2) NOT NULL');
            $renameCol('itens_pedidos', 'produtos_id', 'product_id', 'INT NOT NULL');
            $renameCol('itens_pedidos', 'pedido_id', 'order_id', 'INT NOT NULL');
            $renameTbl('itens_pedidos', 'order_items');
        }

        // --- Table 17: pagamento -> payments ---
        if (Schema::hasTable('pagamento')) {
            $renameCol('pagamento', 'id_compra', 'id', 'INT NOT NULL AUTO_INCREMENT');
            $renameCol('pagamento', 'data_pagamento', 'payment_date', 'DATETIME NOT NULL');
            $renameCol('pagamento', 'pedido_id', 'order_id', 'INT NOT NULL');
            $renameCol('pagamento', 'metodo_pagamento', 'payment_method', 'VARCHAR(45) NOT NULL');
            $renameCol('pagamento', 'valor', 'amount', 'DECIMAL(10,2) NOT NULL');
            $renameTbl('pagamento', 'payments');
        }

        // --- Table 18: historico_pedido -> order_history ---
        if (Schema::hasTable('historico_pedido')) {
            $renameCol('historico_pedido', 'pedido_id', 'order_id', 'INT NOT NULL');
            $renameCol('historico_pedido', 'data_status', 'status_date', 'DATETIME NOT NULL');
            $renameCol('historico_pedido', 'observacao', 'notes', 'VARCHAR(255) NOT NULL');
            $renameCol('historico_pedido', 'historico_pedidocol', 'extra_info', 'VARCHAR(45) NULL');
            $renameTbl('historico_pedido', 'order_history');
        }

        // Update personal_access_tokens
        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', 'App\\Models\\Usuario')
                ->update(['tokenable_type' => 'App\\Models\\User']);
        }

        // Re-add foreign keys with clean English names
        if ($isMysql) {
            $fksToAdd = [
                ['addresses', 'user_id', 'users', 'id', 'fk_addresses_user_id'],
                ['product_sizes', 'product_id', 'products', 'id', 'fk_product_sizes_product_id'],
                ['product_images', 'product_id', 'products', 'id', 'fk_product_images_product_id'],
                ['product_prices', 'product_id', 'products', 'id', 'fk_product_prices_product_id'],
                ['product_promotions', 'price_id', 'product_prices', 'id', 'fk_product_promotions_price_id'],
                ['collection_products', 'collection_id', 'collections', 'id', 'fk_collection_products_collection_id'],
                ['collection_products', 'product_id', 'products', 'id', 'fk_collection_products_product_id'],
                ['collection_images', 'collection_id', 'collections', 'id', 'fk_collection_images_collection_id'],
                ['banner_images', 'banner_id', 'banners', 'id', 'fk_banner_images_banner_id'],
                ['carts', 'user_id', 'users', 'id', 'fk_carts_user_id'],
                ['cart_items', 'cart_id', 'carts', 'id', 'fk_cart_items_cart_id'],
                ['cart_items', 'product_id', 'products', 'id', 'fk_cart_items_product_id'],
                ['orders', 'user_id', 'users', 'id', 'fk_orders_user_id'],
                ['orders', 'address_id', 'addresses', 'id', 'fk_orders_address_id'],
                ['order_items', 'order_id', 'orders', 'id', 'fk_order_items_order_id'],
                ['order_items', 'product_id', 'products', 'id', 'fk_order_items_product_id'],
                ['payments', 'order_id', 'orders', 'id', 'fk_payments_order_id'],
                ['order_history', 'order_id', 'orders', 'id', 'fk_order_history_order_id'],
            ];

            foreach ($fksToAdd as [$fromTbl, $fromCol, $toTbl, $toCol, $fkName]) {
                if (Schema::hasTable($fromTbl) && Schema::hasTable($toTbl)) {
                    try {
                        DB::statement("ALTER TABLE `{$fromTbl}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$fromCol}`) REFERENCES `{$toTbl}` (`{$toCol}`) ON DELETE CASCADE");
                    } catch (\Throwable $e) {
                    }
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
    }
};

