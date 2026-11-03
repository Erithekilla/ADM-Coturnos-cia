<?php

/**
 * CONVERSÃO ÚNICA: banco atual do admin (Coturno_Cia) → schema unificado.
 *
 * Rode SOMENTE com --path, primeiro numa CÓPIA do banco (veja o README):
 *   php artisan migrate --path=database/migrations/conversao/2026_11_04_000001_converter_banco_legado.php
 *
 * Como funciona (copiar-e-trocar, sem perder nada):
 *   1. Pré-checagem somente-leitura. Se achar problema que impediria a conversão
 *      (pedido sem cliente, e-mail duplicado...), PARA sem alterar o banco e lista tudo.
 *   2. Remove as FKs das tabelas antigas e as renomeia para legacy_<nome> (os dados
 *      antigos continuam intactos no mesmo banco).
 *   3. Cria as tabelas novas rodando as próprias migrations unificadas (mesmo schema
 *      de um migrate:fresh — nada de definição duplicada).
 *   4. Copia os dados convertendo: status/is_deleted → active/deleted_at, enums em
 *      português → inglês, CPF/telefone/nascimento cifrados + cpf_hash, senhas em
 *      texto puro → hash, slug/order_number gerados, cópia do endereço no pedido,
 *      payload do gateway cifrado etc. Os IDs são preservados.
 *   5. Ajusta a tabela `migrations` para o conjunto novo (backup em legacy_migrations).
 *
 * Se qualquer passo depois do 1 falhar, o banco é restaurado automaticamente
 * (tabelas novas removidas, legacy_* renomeadas de volta). Depois de concluída,
 * `php artisan migrate:rollback --path=...` (este arquivo) também desfaz tudo.
 *
 * Não são copiados (dados transitórios): sessões, tokens Sanctum, cache, filas,
 * tokens de reset de senha. Todos precisarão fazer login de novo.
 *
 * Requer: MySQL 8+ (ou MariaDB 10.5+) e a MESMA APP_KEY do banco atual.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public $withinTransaction = false;

    private const PREFIX = 'legacy_';

    private const MAP_TABLE = 'legacy_conversion_map';

    private const FK_TABLE = 'legacy_conversion_fks';

    /** Migrations do conjunto unificado, na ordem de execução. */
    private const NEW_MIGRATIONS = [
        '0001_01_01_000000_create_users_table',
        '0001_01_01_000001_create_cache_table',
        '0001_01_01_000002_create_jobs_table',
        '0001_01_01_000003_create_personal_access_tokens_table',
        '2026_09_01_000002_create_social_accounts_table',
        '2026_09_01_000003_create_addresses_table',
        '2026_09_02_000001_create_products_table',
        '2026_09_02_000002_create_product_sizes_table',
        '2026_09_02_000003_create_product_images_table',
        '2026_09_02_000004_create_collections_table',
        '2026_09_02_000005_create_collection_product_table',
        '2026_09_02_000006_create_product_prices_table',
        '2026_09_02_000007_create_product_promotions_table',
        '2026_09_02_000008_create_collection_images_table',
        '2026_09_02_000009_create_banners_table',
        '2026_09_02_000010_create_packed_products_table',
        '2026_09_03_000001_create_carts_table',
        '2026_09_03_000002_create_cart_items_table',
        '2026_09_04_000001_create_orders_table',
        '2026_09_04_000002_create_order_items_table',
        '2026_09_04_000003_create_payments_table',
        '2026_09_04_000004_create_order_history_table',
        '2026_09_05_000001_create_audit_logs_table',
    ];

    /**
     * Tabela unificada => nomes possíveis no banco atual (já traduzido ou ainda em português).
     * Todas as que existirem são movidas para legacy_<nome original>.
     */
    private const SOURCES = [
        'users'                  => ['users', 'usuario'],
        'password_reset_tokens'  => ['password_reset_tokens'],
        'sessions'               => ['sessions'],
        'cache'                  => ['cache'],
        'cache_locks'            => ['cache_locks'],
        'jobs'                   => ['jobs'],
        'job_batches'            => ['job_batches'],
        'failed_jobs'            => ['failed_jobs'],
        'personal_access_tokens' => ['personal_access_tokens'],
        'social_accounts'        => ['social_accounts'],
        'addresses'              => ['addresses', 'endereco'],
        'products'               => ['products', 'produtos'],
        'product_sizes'          => ['product_sizes', 'tamanho_produtos', 'tamanho_produto'],
        'product_images'         => ['product_images', 'imagens_produto'],
        'collections'            => ['collections', 'colecao'],
        'collection_product'     => ['collection_product', 'collection_products', 'colecao_has_produtos'],
        'product_prices'         => ['product_prices', 'precos', 'preco'],
        'product_promotions'     => ['product_promotions', 'promocao_produtos'],
        'collection_images'      => ['collection_images', 'imagens_colecao'],
        'banners'                => ['banners', 'banner'],
        'banner_images'          => ['banner_images', 'imagens_banner'],
        'packed_products'        => ['packed_products'],
        'carts'                  => ['carts', 'carrinho'],
        'cart_items'             => ['cart_items', 'itens_carrinho'],
        'orders'                 => ['orders', 'pedido'],
        'order_items'            => ['order_items', 'itens_pedidos'],
        'payments'               => ['payments', 'pagamento'],
        'order_history'          => ['order_history', 'historico_pedido'],
        'audit_logs'             => ['audit_logs'],
    ];

    private const ORDER_STATUS = [
        'pending'   => ['pending', 'pendente', 'aguardando', 'aguardando_pagamento', 'novo', 'aberto'],
        'paid'      => ['paid', 'pago', 'aprovado', 'approved', 'confirmado'],
        'preparing' => ['preparing', 'em_separacao', 'separando', 'processando', 'em_preparo', 'preparando'],
        'shipped'   => ['shipped', 'enviado', 'em_transito', 'despachado', 'postado'],
        'delivered' => ['delivered', 'entregue', 'concluido', 'finalizado'],
        'cancelled' => ['cancelled', 'canceled', 'cancelado', 'recusado', 'estornado'],
    ];

    /** Códigos numéricos usados pelo painel antigo em historico_pedido.status. */
    private const HISTORY_CODES = [
        0 => 'cancelled', 1 => 'pending', 2 => 'paid', 3 => 'preparing', 4 => 'shipped', 5 => 'delivered',
    ];

    private const PAYMENT_STATUS = [
        'pending'    => ['pending', 'pendente', 'aguardando', 'aguardando_pagamento'],
        'approved'   => ['approved', 'aprovado', 'pago', 'paid', 'concluido', 'confirmado'],
        'in_process' => ['in_process', 'em_processamento', 'processando', 'em_analise'],
        'rejected'   => ['rejected', 'recusado', 'negado', 'reprovado'],
        'cancelled'  => ['cancelled', 'canceled', 'cancelado'],
        'refunded'   => ['refunded', 'estornado', 'reembolsado', 'devolvido'],
    ];

    private const UF = [
        'acre' => 'AC', 'alagoas' => 'AL', 'amapa' => 'AP', 'amazonas' => 'AM', 'bahia' => 'BA',
        'ceara' => 'CE', 'distrito federal' => 'DF', 'espirito santo' => 'ES', 'goias' => 'GO',
        'maranhao' => 'MA', 'mato grosso' => 'MT', 'mato grosso do sul' => 'MS', 'minas gerais' => 'MG',
        'para' => 'PA', 'paraiba' => 'PB', 'parana' => 'PR', 'pernambuco' => 'PE', 'piaui' => 'PI',
        'rio de janeiro' => 'RJ', 'rio grande do norte' => 'RN', 'rio grande do sul' => 'RS',
        'rondonia' => 'RO', 'roraima' => 'RR', 'santa catarina' => 'SC', 'sao paulo' => 'SP',
        'sergipe' => 'SE', 'tocantins' => 'TO',
    ];

    /** unificada => tabela de origem já renomeada (legacy_...) com os dados */
    private array $src = [];

    /** Avisos não bloqueantes (registros ajustados ou ignorados). */
    private array $warnings = [];

    /** IDs já copiados, para checar referências: ['users' => [id => true], ...] */
    private array $ids = [];

    /** Dados auxiliares já convertidos. */
    private array $addresses = [];

    private array $productNames = [];

    private array $productPrices = [];

    // =====================================================================
    // up / down
    // =====================================================================

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('A conversão foi escrita para MySQL/MariaDB. Driver atual: ' . DB::getDriverName());
        }

        if (Schema::hasTable(self::MAP_TABLE)) {
            throw new RuntimeException('Este banco já foi convertido (a tabela ' . self::MAP_TABLE . ' existe).');
        }

        $found = $this->preflight();

        try {
            $this->moveLegacyTables($found);
            $this->createUnifiedSchema();
            $this->copyData();
            $this->rewriteMigrationsTable();
        } catch (Throwable $e) {
            $this->say('ERRO: ' . $e->getMessage());
            $this->say('Restaurando o banco ao estado anterior...');
            $this->restore();
            throw $e;
        }

        $this->report();
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::MAP_TABLE)) {
            return;
        }

        $this->restore();
    }

    // =====================================================================
    // 1. Pré-checagem (não altera nada)
    // =====================================================================

    /** @return array<string, list<string>> unificada => tabelas existentes */
    private function preflight(): array
    {
        $errors = [];

        if (empty(config('app.key'))) {
            $errors[] = 'APP_KEY vazia. Use a MESMA chave do banco atual (CPF/telefone e cpf_hash dependem dela).';
        }

        foreach (self::NEW_MIGRATIONS as $name) {
            if (!is_file($this->migrationPath($name))) {
                $errors[] = "Migration unificada não encontrada: {$name}.php (esperada em " . dirname(__DIR__) . ')';
            }
        }

        foreach (array_column(Schema::getTables(), 'name') as $existing) {
            if (str_starts_with($existing, self::PREFIX)) {
                $errors[] = "Já existe uma tabela '{$existing}'. Remova-a ou renomeie antes de converter.";
            }
        }

        $found = [];
        foreach (self::SOURCES as $unified => $candidates) {
            $present = array_values(array_filter($candidates, fn ($t) => Schema::hasTable($t)));
            if (!$present) {
                continue;
            }
            $found[$unified] = $present;

            $withData = array_values(array_filter($present, fn ($t) => DB::table($t)->exists()));
            if (count($withData) > 1) {
                $errors[] = "Dados em mais de uma tabela para '{$unified}': " . implode(', ', $withData) . '. Unifique manualmente antes.';
            }
            // a origem dos dados é a que tem registros (ou a primeira, se todas vazias)
            $this->src[$unified] = $withData[0] ?? $present[0];
        }

        if (!isset($this->src['users'])) {
            $errors[] = 'Tabela de usuários (users/usuario) não encontrada.';
        }

        if (!$errors) {
            $errors = array_merge($errors, $this->integrityChecks());
        }

        if ($errors) {
            $this->src = [];
            throw new RuntimeException(
                "Conversão abortada ANTES de qualquer alteração. Corrija os itens abaixo e rode de novo:\n - "
                . implode("\n - ", $errors)
            );
        }

        $this->say('Pré-checagem OK. Origens: ' . json_encode($this->src));

        return $found;
    }

    /** Problemas que fariam registros financeiros se perderem ou violarem constraints. */
    private function integrityChecks(): array
    {
        $errors = [];

        $users = $this->src['users'];
        $emailCol = $this->col($users, ['email']);
        if ($emailCol) {
            $dups = DB::table($users)
                ->selectRaw("LOWER(TRIM(`{$emailCol}`)) as e, COUNT(*) as c")
                ->groupByRaw("LOWER(TRIM(`{$emailCol}`))")
                ->havingRaw('COUNT(*) > 1')
                ->pluck('c', 'e');
            foreach ($dups as $email => $count) {
                $errors[] = "E-mail duplicado em usuários ({$count}x): {$email}";
            }
            $empty = DB::table($users)->whereNull($emailCol)->orWhere($emailCol, '')->count();
            if ($empty) {
                $errors[] = "{$empty} usuário(s) sem e-mail.";
            }
        }

        // pedido → usuário
        $this->orphanCheck($errors, 'orders', ['user_id', 'usuario_id'], 'users', ['id'], 'pedido(s) sem cliente existente');
        // item → pedido / produto
        $this->orphanCheck($errors, 'order_items', ['order_id', 'pedido_id'], 'orders', ['id'], 'item(ns) de pedido sem pedido existente');
        $this->orphanCheck($errors, 'order_items', ['product_id', 'produtos_id'], 'products', ['id'], 'item(ns) de pedido apontando para produto inexistente');
        // pagamento → pedido
        $this->orphanCheck($errors, 'payments', ['order_id', 'pedido_id'], 'orders', ['id'], 'pagamento(s) sem pedido existente');

        return $errors;
    }

    private function orphanCheck(array &$errors, string $child, array $fkCands, string $parent, array $pkCands, string $label): void
    {
        if (!isset($this->src[$child], $this->src[$parent])) {
            return;
        }
        $c = $this->src[$child];
        $p = $this->src[$parent];
        $fk = $this->col($c, $fkCands);
        $pk = $this->col($p, $pkCands);
        if (!$fk || !$pk) {
            return;
        }

        $orphans = DB::table($c)
            ->whereNotNull($fk)
            ->whereNotIn($fk, DB::table($p)->select($pk))
            ->pluck($fk)
            ->unique()
            ->take(20)
            ->all();

        if ($orphans) {
            $errors[] = count($orphans) . " {$label} ({$c}.{$fk} = " . implode(', ', $orphans) . ')';
        }
    }

    // =====================================================================
    // 2. Mover tabelas antigas para legacy_*
    // =====================================================================

    private function moveLegacyTables(array $found): void
    {
        DB::statement('CREATE TABLE `' . self::MAP_TABLE . '` (
            `original` VARCHAR(64) NOT NULL PRIMARY KEY,
            `legacy` VARCHAR(80) NOT NULL,
            `unified` VARCHAR(64) NOT NULL,
            `is_source` TINYINT(1) NOT NULL DEFAULT 0
        )');

        DB::statement('CREATE TABLE `' . self::FK_TABLE . '` (
            `table_name` VARCHAR(64) NOT NULL,
            `constraint_name` VARCHAR(64) NOT NULL,
            `columns` VARCHAR(500) NOT NULL,
            `referenced_table` VARCHAR(64) NOT NULL,
            `referenced_columns` VARCHAR(500) NOT NULL,
            `update_rule` VARCHAR(20) NOT NULL,
            `delete_rule` VARCHAR(20) NOT NULL
        )');

        $all = array_merge(...array_values($found));

        // FKs precisam sair antes: os nomes de constraint são únicos por banco e
        // colidiriam com os das tabelas novas; e os tipos INT antigos são incompatíveis.
        // As definições ficam guardadas para a restauração poder recriá-las.
        $in = $this->placeholders($all);
        $fks = DB::select(
            "SELECT rc.TABLE_NAME AS t, rc.CONSTRAINT_NAME AS c, rc.REFERENCED_TABLE_NAME AS rt,
                    rc.UPDATE_RULE AS ur, rc.DELETE_RULE AS dr,
                    GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION) AS cols,
                    GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION) AS rcols
               FROM information_schema.REFERENTIAL_CONSTRAINTS rc
               JOIN information_schema.KEY_COLUMN_USAGE k
                 ON k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
                AND k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND k.TABLE_NAME = rc.TABLE_NAME
              WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
                AND (rc.TABLE_NAME IN ({$in}) OR rc.REFERENCED_TABLE_NAME IN ({$in}))
              GROUP BY rc.TABLE_NAME, rc.CONSTRAINT_NAME, rc.REFERENCED_TABLE_NAME, rc.UPDATE_RULE, rc.DELETE_RULE",
            array_merge($all, $all)
        );
        foreach ($fks as $fk) {
            if (!in_array($fk->t, $all, true)) {
                $this->warn("A tabela externa '{$fk->t}' tinha a FK '{$fk->c}' para uma tabela convertida; ela foi removida.");
            }
            DB::table(self::FK_TABLE)->insert([
                'table_name'         => $fk->t,
                'constraint_name'    => $fk->c,
                'columns'            => $fk->cols,
                'referenced_table'   => $fk->rt,
                'referenced_columns' => $fk->rcols,
                'update_rule'        => $fk->ur,
                'delete_rule'        => $fk->dr,
            ]);
            DB::statement("ALTER TABLE `{$fk->t}` DROP FOREIGN KEY `{$fk->c}`");
        }

        foreach ($found as $unified => $tables) {
            foreach ($tables as $original) {
                $legacy = self::PREFIX . $original;
                $isSource = ($this->src[$unified] ?? null) === $original;
                // registra ANTES de renomear: se cair no meio, a restauração sabe o que mover de volta
                DB::table(self::MAP_TABLE)->insert([
                    'original'  => $original,
                    'legacy'    => $legacy,
                    'unified'   => $unified,
                    'is_source' => $isSource,
                ]);
                Schema::rename($original, $legacy);
                if ($isSource) {
                    $this->src[$unified] = $legacy;
                }
            }
        }

        $this->say('Tabelas antigas movidas para ' . self::PREFIX . '*: ' . count($all));
    }

    // =====================================================================
    // 3. Criar schema unificado
    // =====================================================================

    private function createUnifiedSchema(): void
    {
        foreach (self::NEW_MIGRATIONS as $name) {
            $migration = require $this->migrationPath($name);
            $migration->up();
        }

        $this->say('Schema unificado criado (' . count(self::NEW_MIGRATIONS) . ' migrations).');
    }

    // =====================================================================
    // 4. Copiar dados
    // =====================================================================

    private function copyData(): void
    {
        $this->copyUsers();
        $this->copySocialAccounts();
        $this->copyAddresses();
        $this->loadLegacyPrices();
        $this->copyProducts();
        $this->copyProductSizes();
        $this->copyProductImages();
        $this->copyProductPrices();
        $this->copyProductPromotions();
        $this->copyCollections();
        $this->copyCollectionProduct();
        $this->copySimpleImages('collection_images', 'collections', ['collection_id', 'id_colecao'], ['id', 'id_imagens_colecao']);
        $this->copyBanners();
        $this->copySimpleImages('banner_images', 'banners', ['banner_id', 'banner_idbanner'], ['id', 'id_imagens_banner']);
        $this->copyPackedProducts();
        $this->copyCarts();
        $this->copyCartItems();
        $this->copyOrders();
        $this->copyOrderItems();
        $this->copyPayments();
        $this->copyOrderHistory();
        $this->copyAuditLogs();
    }

    private function copyUsers(): void
    {
        $rows = [];
        $hashes = [];
        $social = [];

        foreach ($this->rows('users') as $r) {
            $id = $this->pick($r, ['id', 'id_usuario']);

            $cpfPlain = $this->cleanDigits($this->plain($this->pick($r, ['cpf']), "users#{$id}.cpf"));
            $cpfHash = $cpfPlain ? hash_hmac('sha256', $cpfPlain, (string) config('app.key')) : null;
            if ($cpfHash && isset($hashes[$cpfHash])) {
                $this->warn("users#{$id}: CPF igual ao do users#{$hashes[$cpfHash]}; cpf_hash deixado nulo (revisar).");
                $cpfHash = null;
            } elseif ($cpfHash) {
                $hashes[$cpfHash] = $id;
            }

            $password = $this->pick($r, ['password', 'senha']);
            if ($password !== null && $password !== '' && !$this->looksHashed((string) $password)) {
                $this->warn("users#{$id}: senha estava em TEXTO PURO no banco; foi convertida para hash.");
                $password = Hash::make((string) $password);
            }

            $deleted = strtoupper(trim((string) ($this->pick($r, ['is_deleted', 'excluido']) ?? 'N'))) === 'S'
                || $this->pick($r, ['deleted_at']) !== null;

            $activeRaw = $this->pick($r, ['active', 'status']);
            $active = !$deleted && $this->truthy($activeRaw, true);

            $createdAt = $this->pick($r, ['created_at', 'data_criacao']) ?? now();
            $updatedAt = $this->pick($r, ['updated_at']) ?? $createdAt;

            $rows[] = [
                'id'                => $id,
                'first_name'        => $this->cut($this->pick($r, ['first_name', 'nome']) ?: 'Cliente', 60),
                'last_name'         => $this->cut($this->pick($r, ['last_name', 'sobrenome']) ?? '', 60),
                'cpf'               => $cpfPlain ? Crypt::encryptString($cpfPlain) : null,
                'cpf_hash'          => $cpfHash,
                'email'             => strtolower(trim((string) $this->pick($r, ['email']))),
                'email_verified_at' => $this->pick($r, ['email_verified_at']),
                'phone'             => $this->encryptOrNull($this->plain($this->pick($r, ['phone', 'telefone']), "users#{$id}.phone")),
                'birth_date'        => $this->encryptOrNull($this->plain($this->pick($r, ['birth_date', 'data_nascimento']), "users#{$id}.birth_date")),
                'password'          => $password ?: null,
                'role'              => strtolower(trim((string) $this->pick($r, ['role']))) === 'admin' ? 'admin' : 'customer',
                'active'            => $active,
                'remember_token'    => $this->pick($r, ['remember_token']),
                'created_at'        => $createdAt,
                'updated_at'        => $updatedAt,
                'deleted_at'        => $deleted ? ($this->pick($r, ['deleted_at']) ?? $updatedAt) : null,
            ];
            $this->ids['users'][$id] = true;

            $googleId = $this->pick($r, ['google_id']);
            if ($googleId) {
                $social[] = [
                    'user_id'       => $id,
                    'provider_name' => 'google',
                    'provider_id'   => $this->cut((string) $googleId, 191),
                    'created_at'    => $createdAt,
                    'updated_at'    => $updatedAt,
                ];
            }
        }

        $this->insert('users', $rows);
        $this->insert('social_accounts', $social);

        $admins = count(array_filter($rows, fn ($u) => $u['role'] === 'admin' && $u['active']));
        if ($admins === 0) {
            $this->warn('Nenhum administrador ativo após a conversão. Rode o AdminUserSeeder (adaptado) antes de usar o painel.');
        }
    }

    private function copySocialAccounts(): void
    {
        $existing = DB::table('social_accounts')->get(['provider_name', 'provider_id', 'user_id'])
            ->map(fn ($s) => "{$s->provider_name}|{$s->provider_id}")->flip()->all();

        $rows = [];
        foreach ($this->rows('social_accounts') as $r) {
            $userId = $this->pick($r, ['user_id']);
            $key = $this->pick($r, ['provider_name']) . '|' . $this->pick($r, ['provider_id']);
            if (!isset($this->ids['users'][$userId]) || isset($existing[$key])) {
                continue;
            }
            $existing[$key] = true;
            $rows[] = [
                'user_id'       => $userId,
                'provider_name' => $this->cut((string) $this->pick($r, ['provider_name']), 30),
                'provider_id'   => $this->cut((string) $this->pick($r, ['provider_id']), 191),
                'created_at'    => $this->pick($r, ['created_at']),
                'updated_at'    => $this->pick($r, ['updated_at']),
            ];
        }
        $this->insert('social_accounts', $rows);
    }

    private function copyAddresses(): void
    {
        $rows = [];
        foreach ($this->rows('addresses') as $r) {
            $id = $this->pick($r, ['id', 'id_endereco']);
            $userId = $this->pick($r, ['user_id', 'usuario_id']);
            if (!isset($this->ids['users'][$userId])) {
                $this->warn("addresses#{$id}: usuário {$userId} inexistente; endereço ignorado.");
                continue;
            }

            $row = [
                'id'           => $id,
                'user_id'      => $userId,
                'zip_code'     => $this->formatCep($this->pick($r, ['zip_code', 'cep'])),
                'street'       => $this->cut($this->pick($r, ['street', 'rua']) ?? '', 120),
                'number'       => $this->cut($this->pick($r, ['number', 'numero']) ?: 'S/N', 20),
                'complement'   => $this->cut($this->pick($r, ['complement', 'complemento']), 100),
                'neighborhood' => $this->cut($this->pick($r, ['neighborhood', 'bairro']) ?? '', 60),
                'city'         => $this->cut($this->pick($r, ['city', 'cidade']) ?? '', 60),
                'state'        => $this->uf($this->pick($r, ['state', 'estado']), "addresses#{$id}"),
                'is_default'   => $this->truthy($this->pick($r, ['is_default', 'padrao']), false),
                'created_at'   => $this->pick($r, ['created_at']),
                'updated_at'   => $this->pick($r, ['updated_at']),
                'deleted_at'   => $this->pick($r, ['deleted_at']),
            ];
            $rows[] = $row;
            $this->addresses[$id] = $row;
            $this->ids['addresses'][$id] = true;
        }
        $this->insert('addresses', $rows);
    }

    /** Preço vigente e promoção vigente, lidos do histórico antigo antes de copiar produtos. */
    private function loadLegacyPrices(): void
    {
        $priceProduct = [];
        $latest = [];
        foreach ($this->rows('product_prices') as $r) {
            $priceId = $this->pick($r, ['id', 'id_precos']);
            $productId = $this->pick($r, ['product_id', 'produtos_id']);
            $date = (string) ($this->pick($r, ['effective_date', 'alteracao']) ?? '');
            $priceProduct[$priceId] = $productId;
            if (!isset($latest[$productId]) || [$date, $priceId] > [$latest[$productId]['d'], $latest[$productId]['id']]) {
                $latest[$productId] = ['d' => $date, 'id' => $priceId, 'p' => $this->pick($r, ['price', 'preco'])];
            }
        }

        $today = now()->toDateString();
        $promo = [];
        foreach ($this->rows('product_promotions') as $r) {
            $productId = $priceProduct[$this->pick($r, ['price_id', 'precos_id_precos'])] ?? null;
            $start = substr((string) $this->pick($r, ['start_date', 'data_inicio']), 0, 10);
            $end = substr((string) ($this->pick($r, ['end_date', 'data_fim']) ?? $start), 0, 10);
            if ($productId === null || !$this->truthy($this->pick($r, ['active', 'ativo']), true) || $start > $today || $end < $today) {
                continue;
            }
            $value = (float) $this->pick($r, ['promotional_price', 'valor_promo']);
            $promo[$productId] = isset($promo[$productId]) ? min($promo[$productId], $value) : $value;
        }

        foreach ($latest as $productId => $info) {
            $this->productPrices[$productId]['base'] = $info['p'];
        }
        foreach ($promo as $productId => $value) {
            $this->productPrices[$productId]['promo'] = $value;
        }
    }

    private function copyProducts(): void
    {
        $rows = [];
        foreach ($this->rows('products') as $r) {
            $id = $this->pick($r, ['id', 'id_produtos']);
            $brand = $this->pick($r, ['brand', 'marca']) ?? '';
            $model = $this->pick($r, ['model', 'modelo']);
            $color = $this->pick($r, ['color', 'cor']);
            $name = $this->pick($r, ['name', 'nome']) ?: (trim("{$brand} {$model} {$color}") ?: "Produto {$id}");

            $base = $this->productPrices[$id]['base'] ?? $this->pick($r, ['base_price', 'price', 'preco']);
            if ($base === null) {
                $this->warn("products#{$id}: sem preço no histórico; base_price = 0 (revisar no painel).");
            }
            $promo = $this->productPrices[$id]['promo'] ?? $this->pick($r, ['promotional_price']);

            $deleted = strtoupper(trim((string) ($this->pick($r, ['is_deleted', 'excluido']) ?? 'N'))) === 'S'
                || $this->pick($r, ['deleted_at']) !== null;

            $rows[] = [
                'id'                => $id,
                'name'              => $this->cut($name, 120),
                'slug'              => $this->cut(Str::slug($name) ?: 'produto', 128) . '-' . $id,
                'product_type'      => $this->cut($this->pick($r, ['product_type', 'tipo_do_produto']) ?: 'Coturno', 45),
                'brand'             => $this->cut($brand, 45),
                'model'             => $this->cut($model, 45),
                'color'             => $this->cut($color, 45),
                'description'       => (string) ($this->pick($r, ['description', 'descricao']) ?? ''),
                'base_price'        => $base ?? 0,
                'promotional_price' => $promo,
                'weight'            => $this->pick($r, ['weight', 'peso']),
                'height'            => $this->pick($r, ['height', 'altura']),
                'width'             => $this->pick($r, ['width', 'largura']),
                'length'            => $this->pick($r, ['length', 'comprimento']),
                'active'            => !$deleted && $this->truthy($this->pick($r, ['active', 'ativo', 'status']), true),
                'created_at'        => $this->pick($r, ['created_at']),
                'updated_at'        => $this->pick($r, ['updated_at']),
                'deleted_at'        => $deleted ? ($this->pick($r, ['deleted_at', 'updated_at']) ?? now()) : null,
            ];
            $this->ids['products'][$id] = true;
            $this->productNames[$id] = $this->cut($name, 120);
            $this->productPrices[$id]['current'] = $promo ?? $base ?? 0;
        }
        $this->insert('products', $rows);
    }

    private function copyProductSizes(): void
    {
        $byKey = [];
        foreach ($this->rows('product_sizes') as $r) {
            $productId = $this->pick($r, ['product_id', 'produtos_id']);
            $size = $this->pick($r, ['size', 'tamanho']);
            if (!isset($this->ids['products'][$productId]) || $size === null) {
                $this->warn('product_sizes: registro ignorado (produto inexistente ou sem tamanho): ' . json_encode($r));
                continue;
            }
            $stock = max(0, (int) $this->pick($r, ['stock', 'quantity', 'quantidade']));
            $key = "{$productId}|{$size}";
            if (isset($byKey[$key])) {
                $this->warn("product_sizes: produto {$productId} tamanho {$size} duplicado; estoques somados.");
                $byKey[$key]['stock'] += $stock;
                continue;
            }
            $byKey[$key] = [
                'id'         => $this->pick($r, ['id', 'id_tamanho_produtos']),
                'product_id' => $productId,
                'size'       => (int) $size,
                'stock'      => $stock,
                'created_at' => $this->pick($r, ['created_at']),
                'updated_at' => $this->pick($r, ['updated_at']),
            ];
        }
        $this->insert('product_sizes', $this->dropNullIds(array_values($byKey)));
    }

    private function copyProductImages(): void
    {
        $rows = [];
        $order = [];
        foreach ($this->rows('product_images') as $r) {
            $productId = $this->pick($r, ['product_id', 'produtos_id']);
            $url = $this->pick($r, ['image_url', 'url_imagem']);
            if (!isset($this->ids['products'][$productId]) || !$url) {
                continue;
            }
            $position = $order[$productId] = ($order[$productId] ?? -1) + 1;
            $rows[] = [
                'id'                   => $this->pick($r, ['id', 'id_imagens_produto']),
                'product_id'           => $productId,
                'image_url'            => $this->cut($url, 255),
                'cloudinary_public_id' => $this->cut($this->pick($r, ['cloudinary_public_id']), 150),
                'is_primary'           => $this->pick($r, ['is_primary']) !== null ? $this->truthy($this->pick($r, ['is_primary']), false) : $position === 0,
                'display_order'        => $this->pick($r, ['display_order']) ?? $position,
                'created_at'           => $this->pick($r, ['created_at']),
                'updated_at'           => $this->pick($r, ['updated_at']),
            ];
        }
        $this->insert('product_images', $this->dropNullIds($rows));
    }

    private function copyProductPrices(): void
    {
        $rows = [];
        foreach ($this->rows('product_prices') as $r) {
            $id = $this->pick($r, ['id', 'id_precos']);
            $productId = $this->pick($r, ['product_id', 'produtos_id']);
            if (!isset($this->ids['products'][$productId])) {
                continue;
            }
            $date = $this->pick($r, ['effective_date', 'alteracao']) ?? now();
            $rows[] = [
                'id'             => $id,
                'product_id'     => $productId,
                'price'          => $this->pick($r, ['price', 'preco']) ?? 0,
                'effective_date' => $date,
                'created_at'     => $date,
                'updated_at'     => $date,
            ];
            $this->ids['product_prices'][$id] = true;
        }
        $this->insert('product_prices', $rows);
    }

    private function copyProductPromotions(): void
    {
        $rows = [];
        foreach ($this->rows('product_promotions') as $r) {
            $priceId = $this->pick($r, ['price_id', 'precos_id_precos']);
            if (!isset($this->ids['product_prices'][$priceId])) {
                continue;
            }
            $start = $this->pick($r, ['start_date', 'data_inicio']) ?? now();
            $rows[] = [
                'id'                => $this->pick($r, ['id', 'id_promocao_produtos']),
                'price_id'          => $priceId,
                'promotional_price' => $this->pick($r, ['promotional_price', 'valor_promo']) ?? 0,
                'start_date'        => substr((string) $start, 0, 10),
                'end_date'          => substr((string) ($this->pick($r, ['end_date', 'data_fim']) ?? $start), 0, 10),
                'active'            => $this->truthy($this->pick($r, ['active', 'ativo']), true),
                'created_at'        => $this->pick($r, ['created_at']),
                'updated_at'        => $this->pick($r, ['updated_at']),
            ];
        }
        $this->insert('product_promotions', $this->dropNullIds($rows));
    }

    private function copyCollections(): void
    {
        $rows = [];
        foreach ($this->rows('collections') as $r) {
            $id = $this->pick($r, ['id', 'id_colecao']);
            $name = $this->pick($r, ['name', 'nome_colecao', 'nome']) ?: "Coleção {$id}";
            $rows[] = [
                'id'          => $id,
                'name'        => $this->cut($name, 100),
                'slug'        => $this->cut(Str::slug($name) ?: 'colecao', 108) . '-' . $id,
                'description' => $this->cut($this->pick($r, ['description', 'descricao_colecao']), 255),
                'banner_url'  => $this->cut($this->pick($r, ['banner_url']), 255),
                'start_date'  => $this->pick($r, ['start_date', 'data_inicio']),
                'end_date'    => $this->pick($r, ['end_date', 'data_fim']),
                'active'      => $this->truthy($this->pick($r, ['active', 'ativo']), true),
                'created_at'  => $this->pick($r, ['created_at']),
                'updated_at'  => $this->pick($r, ['updated_at']),
            ];
            $this->ids['collections'][$id] = true;
        }
        $this->insert('collections', $rows);
    }

    private function copyCollectionProduct(): void
    {
        $rows = [];
        foreach ($this->rows('collection_product') as $r) {
            $c = $this->pick($r, ['collection_id', 'id_colecao']);
            $p = $this->pick($r, ['product_id', 'produtos_id']);
            if (!isset($this->ids['collections'][$c], $this->ids['products'][$p])) {
                continue;
            }
            $rows["{$c}|{$p}"] = [
                'collection_id' => $c,
                'product_id'    => $p,
                'created_at'    => $this->pick($r, ['created_at']),
                'updated_at'    => $this->pick($r, ['updated_at']),
            ];
        }
        $this->insert('collection_product', array_values($rows));
    }

    private function copyBanners(): void
    {
        $rows = [];
        foreach ($this->rows('banners') as $r) {
            $id = $this->pick($r, ['id', 'idbanner']);
            $rows[] = [
                'id'         => $id,
                'title'      => $this->cut($this->pick($r, ['title', 'titulo']), 100),
                'link_url'   => $this->cut($this->pick($r, ['link_url', 'link']), 255),
                'active'     => $this->truthy($this->pick($r, ['active', 'ativo']), true),
                'created_at' => $this->pick($r, ['created_at']),
                'updated_at' => $this->pick($r, ['updated_at']),
            ];
            $this->ids['banners'][$id] = true;
        }
        $this->insert('banners', $rows);
    }

    private function copySimpleImages(string $unified, string $parent, array $fkCands, array $idCands): void
    {
        $rows = [];
        $fkName = $fkCands[0];
        foreach ($this->rows($unified) as $r) {
            $parentId = $this->pick($r, $fkCands);
            $url = $this->pick($r, ['image_url', 'url_imagem']);
            if (!isset($this->ids[$parent][$parentId]) || !$url) {
                continue;
            }
            if (mb_strlen((string) $url) === 45) {
                $this->warn("{$unified}: URL com exatamente 45 caracteres, possivelmente truncada pela coluna antiga: {$url}");
            }
            $rows[] = [
                'id'         => $this->pick($r, $idCands),
                $fkName      => $parentId,
                'image_url'  => $this->cut($url, 255),
                'created_at' => $this->pick($r, ['created_at']),
                'updated_at' => $this->pick($r, ['updated_at']),
            ];
        }
        $this->insert($unified, $this->dropNullIds($rows));
    }

    private function copyPackedProducts(): void
    {
        $rows = [];
        foreach ($this->rows('packed_products') as $r) {
            $rows[] = [
                'id'         => $this->pick($r, ['id']),
                'weight'     => $this->pick($r, ['weight', 'peso']) ?? 1.50,
                'height'     => $this->pick($r, ['height', 'altura']) ?? 14.00,
                'width'      => $this->pick($r, ['width', 'largura']) ?? 22.00,
                'length'     => $this->pick($r, ['length', 'comprimento']) ?? 35.00,
                'created_at' => $this->pick($r, ['created_at']),
                'updated_at' => $this->pick($r, ['updated_at']),
            ];
        }
        $this->insert('packed_products', $this->dropNullIds($rows));
    }

    private function copyCarts(): void
    {
        $rows = [];
        foreach ($this->rows('carts') as $r) {
            $id = $this->pick($r, ['id', 'id_carrinho']);
            $userId = $this->pick($r, ['user_id', 'usuario_id']);
            $session = $this->pick($r, ['session_id']);
            if (!isset($this->ids['users'][$userId]) && !$session) {
                continue; // carrinho órfão e sem sessão: sem dono possível
            }
            $rows[] = [
                'id'         => $id,
                'user_id'    => isset($this->ids['users'][$userId]) ? $userId : null,
                'session_id' => $session,
                'created_at' => $this->pick($r, ['created_at', 'criado_em']),
                'updated_at' => $this->pick($r, ['updated_at', 'atualizado_em']),
            ];
            $this->ids['carts'][$id] = true;
        }
        $this->insert('carts', $rows);
    }

    private function copyCartItems(): void
    {
        $byKey = [];
        foreach ($this->rows('cart_items') as $r) {
            $cartId = $this->pick($r, ['cart_id', 'carrinho_id_carrinho']);
            $productId = $this->pick($r, ['product_id', 'produtos_id']);
            $size = $this->pick($r, ['size', 'tamanho']);
            if (!isset($this->ids['carts'][$cartId], $this->ids['products'][$productId]) || $size === null) {
                continue;
            }
            $qty = max(1, (int) ($this->pick($r, ['quantity', 'quantidade']) ?? 1));
            $key = "{$cartId}|{$productId}|{$size}";
            if (isset($byKey[$key])) {
                $byKey[$key]['quantity'] += $qty;
                continue;
            }
            $byKey[$key] = [
                'id'         => $this->pick($r, ['id']),
                'cart_id'    => $cartId,
                'product_id' => $productId,
                'size'       => (int) $size,
                'quantity'   => $qty,
                'unit_price' => $this->pick($r, ['unit_price']) ?? $this->productPrices[$productId]['current'] ?? 0,
                'created_at' => $this->pick($r, ['created_at']),
                'updated_at' => $this->pick($r, ['updated_at']),
            ];
        }
        $this->insert('cart_items', $this->dropNullIds(array_values($byKey)));
    }

    private function copyOrders(): void
    {
        // subtotal real de cada pedido, a partir dos itens antigos
        $itemsTotal = [];
        foreach ($this->rows('order_items') as $r) {
            $orderId = $this->pick($r, ['order_id', 'pedido_id']);
            $qty = (int) ($this->pick($r, ['quantity', 'quantidade']) ?? 1);
            $price = (float) ($this->pick($r, ['unit_price', 'preco_unitario']) ?? 0);
            $itemsTotal[$orderId] = ($itemsTotal[$orderId] ?? 0) + (float) ($this->pick($r, ['subtotal']) ?? $qty * $price);
        }

        $rows = [];
        $numbers = [];
        foreach ($this->rows('orders') as $r) {
            $id = $this->pick($r, ['id', 'id_pedido']);
            $addressId = $this->pick($r, ['address_id', 'endereco_id']);
            $address = $this->addresses[$addressId] ?? null;
            if ($addressId && !$address) {
                $this->warn("orders#{$id}: endereço {$addressId} inexistente; pedido mantido sem endereço.");
            }

            $total = round((float) ($this->pick($r, ['total_amount', 'valor_total']) ?? 0), 2);
            $subtotal = round((float) ($this->pick($r, ['subtotal']) ?? $itemsTotal[$id] ?? $total), 2);
            $shipping = $this->pick($r, ['shipping_fee']) ?? max(0, round($total - $subtotal, 2));
            $discount = $this->pick($r, ['discount_fee']) ?? max(0, round($subtotal - $total, 2));

            $number = $this->cut($this->pick($r, ['order_number']), 20);
            while (!$number || isset($numbers[$number])) {
                $number = 'CC-' . strtoupper(Str::random(10));
            }
            $numbers[$number] = true;

            $rows[] = [
                'id'                    => $id,
                'order_number'          => $number,
                'user_id'               => $this->pick($r, ['user_id', 'usuario_id']),
                'address_id'            => $address ? $addressId : null,
                'shipping_zip_code'     => $address['zip_code'] ?? null,
                'shipping_street'       => $address['street'] ?? null,
                'shipping_number'       => $address['number'] ?? null,
                'shipping_complement'   => $address['complement'] ?? null,
                'shipping_neighborhood' => $address['neighborhood'] ?? null,
                'shipping_city'         => $address['city'] ?? null,
                'shipping_state'        => $address['state'] ?? null,
                'status'                => $this->mapStatus(self::ORDER_STATUS, $this->pick($r, ['status']), 'pending', "orders#{$id}"),
                'subtotal'              => $subtotal,
                'shipping_fee'          => $shipping,
                'discount_fee'          => $discount,
                'total_amount'          => $total,
                'shipping_method'       => $this->cut($this->pick($r, ['shipping_method', 'metodo_envio']), 50),
                'tracking_code'         => $this->cut($this->pick($r, ['tracking_code', 'codigo_rastreio']), 100),
                'created_at'            => $this->pick($r, ['created_at', 'order_date', 'data_pedido']) ?? now(),
                'updated_at'            => $this->pick($r, ['updated_at', 'order_date', 'data_pedido']) ?? now(),
            ];
            $this->ids['orders'][$id] = true;
        }
        $this->insert('orders', $rows);
    }

    private function copyOrderItems(): void
    {
        $rows = [];
        foreach ($this->rows('order_items') as $r) {
            $orderId = $this->pick($r, ['order_id', 'pedido_id']);
            $productId = $this->pick($r, ['product_id', 'produtos_id']);
            $id = $this->pick($r, ['id', 'id_itens_pedidos']);
            $size = $this->pick($r, ['size', 'tamanho']);
            if ($size === null) {
                $this->warn("order_items#{$id}: sem tamanho; gravado como 0 (revisar).");
            }
            $qty = (int) ($this->pick($r, ['quantity', 'quantidade']) ?? 1);
            $price = $this->pick($r, ['unit_price', 'preco_unitario']) ?? $this->productPrices[$productId]['current'] ?? 0;
            $rows[] = [
                'id'           => $id,
                'order_id'     => $orderId,
                'product_id'   => $productId,
                'product_name' => $this->productNames[$productId] ?? null,
                'size'         => (int) ($size ?? 0),
                'quantity'     => $qty,
                'unit_price'   => $price,
                'subtotal'     => $this->pick($r, ['subtotal']) ?? round($qty * (float) $price, 2),
                'created_at'   => $this->pick($r, ['created_at']),
                'updated_at'   => $this->pick($r, ['updated_at']),
            ];
        }
        $this->insert('order_items', $this->dropNullIds($rows));
    }

    private function copyPayments(): void
    {
        $rows = [];
        $gatewayIds = [];
        foreach ($this->rows('payments') as $r) {
            $id = $this->pick($r, ['id', 'id_compra']);
            $paidAt = $this->pick($r, ['paid_at', 'payment_date', 'data_pagamento']);

            $gatewayId = $this->pick($r, ['gateway_payment_id']);
            if ($gatewayId && isset($gatewayIds[$gatewayId])) {
                $this->warn("payments#{$id}: gateway_payment_id {$gatewayId} repetido (webhook duplicado?); deixado nulo.");
                $gatewayId = null;
            }
            if ($gatewayId) {
                $gatewayIds[$gatewayId] = true;
            }

            $payload = $this->pick($r, ['response_payload']);
            if ($payload !== null && !$this->looksEncrypted((string) $payload)) {
                $payload = Crypt::encryptString(is_string($payload) ? $payload : json_encode($payload));
            }

            $rawMethod = $this->pick($r, ['method', 'payment_method', 'metodo_pagamento']);

            $rows[] = [
                'id'                 => $id,
                'order_id'           => $this->pick($r, ['order_id', 'pedido_id']),
                'gateway'            => $this->cut($this->pick($r, ['gateway']) ?: 'legado', 30),
                'preference_id'      => $this->cut($this->pick($r, ['preference_id']), 100),
                'gateway_payment_id' => $this->cut($gatewayId, 100),
                'method'             => $rawMethod ? $this->cut(Str::snake(Str::ascii(strtolower((string) $rawMethod))), 50) : null,
                'status'             => $this->mapStatus(self::PAYMENT_STATUS, $this->pick($r, ['status']), $paidAt ? 'approved' : 'pending', "payments#{$id}"),
                'amount'             => $this->pick($r, ['amount', 'valor']) ?? 0,
                'installments'       => max(1, min(255, (int) ($this->pick($r, ['installments', 'parcelas']) ?? 1))),
                'init_point'         => $this->pick($r, ['init_point']),
                'qr_code_pix'        => $this->pick($r, ['qr_code_pix']),
                'qr_code_base64'     => $this->pick($r, ['qr_code_base64']),
                'response_payload'   => $payload,
                'paid_at'            => $paidAt,
                'created_at'         => $this->pick($r, ['created_at']) ?? $paidAt,
                'updated_at'         => $this->pick($r, ['updated_at']) ?? $paidAt,
            ];
        }
        $this->insert('payments', $rows);
    }

    private function copyOrderHistory(): void
    {
        $rows = [];
        foreach ($this->rows('order_history') as $r) {
            $id = $this->pick($r, ['id', 'id_historico_pedido']);
            $orderId = $this->pick($r, ['order_id', 'pedido_id']);
            if (!isset($this->ids['orders'][$orderId])) {
                continue;
            }
            $raw = $this->pick($r, ['status']);
            // o painel antigo gravava o histórico com códigos numéricos (AdminOrderController)
            $status = is_numeric($raw)
                ? (self::HISTORY_CODES[(int) $raw] ?? null)
                : $this->mapStatus(self::ORDER_STATUS, $raw, null, null);
            $notes = $this->pick($r, ['notes', 'observacao']);
            if ($status === null) {
                $status = 'pending';
                $notes = trim("[status legado: {$raw}] " . ($notes ?? ''));
                $this->warn("order_history#{$id}: status '{$raw}' desconhecido; gravado como pending (original nas notas).");
            }
            $date = $this->pick($r, ['status_date', 'data_status']) ?? now();
            $rows[] = [
                'id'          => $id,
                'order_id'    => $orderId,
                'status'      => $status,
                'status_date' => $date,
                'notes'       => $this->cut($notes, 255),
                'extra_info'  => $this->cut($this->pick($r, ['extra_info', 'historico_pedidocol']), 255),
                'created_at'  => $date,
                'updated_at'  => $date,
            ];
        }
        $this->insert('order_history', $this->dropNullIds($rows));
    }

    private function copyAuditLogs(): void
    {
        $rows = [];
        foreach ($this->rows('audit_logs') as $r) {
            $adminId = $this->pick($r, ['admin_user_id']);
            $rows[] = [
                'id'             => $this->pick($r, ['id']),
                'admin_user_id'  => isset($this->ids['users'][$adminId]) ? $adminId : null,
                'actor_label'    => $this->cut($this->pick($r, ['actor_label', 'autor']), 191),
                'action'         => $this->cut($this->pick($r, ['action', 'acao']) ?: 'LEGACY', 100),
                'table_affected' => $this->cut($this->pick($r, ['table_affected', 'tabela']) ?? '', 60),
                'record_id'      => $this->pick($r, ['record_id', 'registro_id']),
                'old_values'     => $this->validJson($this->pick($r, ['old_values'])),
                'new_values'     => $this->validJson($this->pick($r, ['new_values'])),
                'description'    => $this->pick($r, ['description', 'detalhes']),
                'ip_address'     => $this->cut($this->pick($r, ['ip_address', 'ip']), 45),
                'user_agent'     => $this->cut($this->pick($r, ['user_agent']), 255),
                'created_at'     => $this->pick($r, ['created_at']) ?? now(),
            ];
        }
        $this->insert('audit_logs', $this->dropNullIds($rows));
    }

    // =====================================================================
    // 5. Tabela migrations
    // =====================================================================

    private function rewriteMigrationsTable(): void
    {
        // um único comando: ou a cópia existe completa, ou não existe
        DB::statement('CREATE TABLE `' . self::PREFIX . 'migrations` AS SELECT * FROM `migrations`');

        $self = pathinfo(__FILE__, PATHINFO_FILENAME);
        DB::table('migrations')->whereNotIn('migration', array_merge(self::NEW_MIGRATIONS, [$self]))->delete();

        $batch = (int) DB::table('migrations')->max('batch') + 1;
        $ran = DB::table('migrations')->pluck('migration')->all();
        foreach (self::NEW_MIGRATIONS as $name) {
            if (!in_array($name, $ran, true)) {
                DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
            }
        }
    }

    // =====================================================================
    // Restauração
    // =====================================================================

    private function restore(): void
    {
        if (!Schema::hasTable(self::MAP_TABLE)) {
            return; // nada foi alterado ainda
        }

        Schema::disableForeignKeyConstraints();

        $map = DB::table(self::MAP_TABLE)->get()->keyBy('original');

        // Remove só o que a conversão criou: um nome unificado é "novo" se não existia
        // antes (sem registro no mapa) ou se a original correspondente já foi movida.
        foreach (array_reverse(array_keys(self::SOURCES)) as $unified) {
            if (!Schema::hasTable($unified)) {
                continue;
            }
            $entry = $map->get($unified);
            if ($entry === null || Schema::hasTable($entry->legacy)) {
                Schema::drop($unified);
            }
        }

        foreach ($map as $m) {
            if (Schema::hasTable($m->legacy) && !Schema::hasTable($m->original)) {
                Schema::rename($m->legacy, $m->original);
            }
        }

        if (Schema::hasTable(self::PREFIX . 'migrations')) {
            DB::table('migrations')->delete();
            DB::statement('INSERT INTO `migrations` SELECT * FROM `' . self::PREFIX . 'migrations`');
            Schema::drop(self::PREFIX . 'migrations');
        }

        Schema::enableForeignKeyConstraints();

        // Recria as FKs antigas exatamente como estavam
        if (Schema::hasTable(self::FK_TABLE)) {
            foreach (DB::table(self::FK_TABLE)->get() as $fk) {
                $cols = '`' . str_replace(',', '`,`', $fk->columns) . '`';
                $refs = '`' . str_replace(',', '`,`', $fk->referenced_columns) . '`';
                try {
                    DB::statement("ALTER TABLE `{$fk->table_name}` ADD CONSTRAINT `{$fk->constraint_name}`
                        FOREIGN KEY ({$cols}) REFERENCES `{$fk->referenced_table}` ({$refs})
                        ON DELETE {$fk->delete_rule} ON UPDATE {$fk->update_rule}");
                } catch (Throwable $e) {
                    $this->say("AVISO: não foi possível recriar a FK {$fk->constraint_name} em {$fk->table_name}: " . $e->getMessage());
                }
            }
            Schema::drop(self::FK_TABLE);
        }

        Schema::drop(self::MAP_TABLE);

        $this->say('Banco restaurado ao estado anterior à conversão.');
    }

    // =====================================================================
    // Utilitários
    // =====================================================================

    /** Linhas da tabela de origem como arrays (vazio se a origem não existir). */
    private function rows(string $unified): iterable
    {
        if (!isset($this->src[$unified])) {
            return [];
        }

        return DB::table($this->src[$unified])->cursor()->map(fn ($r) => (array) $r);
    }

    /** Primeiro valor não nulo entre as colunas candidatas. */
    private function pick(array $row, array $names, mixed $default = null): mixed
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $row) && $row[$name] !== null) {
                return is_string($row[$name]) ? trim($row[$name]) : $row[$name];
            }
        }

        return $default;
    }

    private function col(string $table, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (Schema::hasColumn($table, $c)) {
                return $c;
            }
        }

        return null;
    }

    private function insert(string $table, array $rows): void
    {
        // insert em lote exige as mesmas colunas em todas as linhas (com e sem id)
        $groups = [];
        foreach ($rows as $row) {
            $groups[implode(',', array_keys($row))][] = $row;
        }
        foreach ($groups as $group) {
            foreach (array_chunk($group, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
        $this->say(str_pad($table, 24) . count($rows) . ' registro(s)');
    }

    /** Remove a chave id das linhas sem id (o AUTO_INCREMENT gera). */
    private function dropNullIds(array $rows): array
    {
        return array_map(function ($r) {
            if (array_key_exists('id', $r) && $r['id'] === null) {
                unset($r['id']);
            }

            return $r;
        }, $rows);
    }

    private function truthy(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $v = Str::ascii(strtolower(trim((string) $value)));

        return in_array($v, ['1', 'true', 'ativo', 'active', 's', 'sim', 'y', 'yes'], true);
    }

    private function mapStatus(array $map, mixed $raw, ?string $fallback, ?string $where): ?string
    {
        if ($raw === null || $raw === '') {
            return $fallback;
        }
        $norm = str_replace([' ', '-'], '_', Str::ascii(strtolower(trim((string) $raw))));
        foreach ($map as $target => $aliases) {
            if (in_array($norm, $aliases, true)) {
                return $target;
            }
        }
        if ($where) {
            $this->warn("{$where}: status '{$raw}' desconhecido; gravado como '{$fallback}'.");
        }

        return $fallback;
    }

    private function uf(mixed $raw, string $where): string
    {
        $v = trim((string) $raw);
        if (strlen($v) === 2) {
            return strtoupper($v);
        }
        $key = Str::ascii(strtolower($v));
        if (isset(self::UF[$key])) {
            return self::UF[$key];
        }
        if ($v !== '') {
            $this->warn("{$where}: estado '{$v}' não reconhecido; UF deixada em branco (revisar).");
        }

        return '';
    }

    private function formatCep(mixed $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        return strlen($digits) === 8 ? substr($digits, 0, 5) . '-' . substr($digits, 5) : substr($digits, 0, 9);
    }

    private function cut(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr((string) $value, 0, $max);
    }

    /** Valor em claro: decifra se estiver cifrado; mantém se for texto puro legado. */
    private function plain(mixed $value, string $where): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        if (!$this->looksEncrypted($value)) {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            throw new RuntimeException("{$where}: dado cifrado com outra APP_KEY (não foi possível decifrar). Configure a APP_KEY correta (ou APP_PREVIOUS_KEYS).");
        }
    }

    private function encryptOrNull(?string $plain): ?string
    {
        return ($plain === null || $plain === '') ? null : Crypt::encryptString($plain);
    }

    private function looksEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        $payload = json_decode($decoded, true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    private function looksHashed(string $value): bool
    {
        return (bool) preg_match('/^\$(2[abxy]|argon2i|argon2id)\$/', $value);
    }

    private function validJson(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            return json_encode($value);
        }
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE ? $value : json_encode(['legado' => $value]);
    }

    private function migrationPath(string $name): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . $name . '.php';
    }

    private function placeholders(array $items): string
    {
        return implode(',', array_fill(0, count($items), '?'));
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    private function say(string $message): void
    {
        echo '  [conversão] ' . $message . PHP_EOL;
    }

    private function report(): void
    {
        $log = storage_path('logs/conversao_banco_legado.log');
        $body = '[' . now()->toDateTimeString() . "] Conversão concluída.\n"
            . 'Avisos (' . count($this->warnings) . "):\n - " . implode("\n - ", $this->warnings ?: ['nenhum']) . "\n";
        file_put_contents($log, $body, FILE_APPEND);

        $this->say('Concluída. ' . count($this->warnings) . ' aviso(s) gravado(s) em ' . $log);
        foreach (array_slice($this->warnings, 0, 15) as $w) {
            $this->say('  ! ' . $w);
        }
        $this->say('As tabelas antigas continuam no banco como ' . self::PREFIX . '* até você rodar a migration de limpeza.');
    }

    /** Mantém só dígitos (mesma regra do User::cleanCpf). */
    private function cleanDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? null : $digits;
    }
};
