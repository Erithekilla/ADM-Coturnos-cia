# Migrations unificadas (cliente + admin)

Conjunto único de migrations que substitui `backend-api/database/migrations` e
`.MCP/migrations_client`. A estrutura veio do cliente; a proteção de dados, do admin;
e as falhas dos dois foram corrigidas.

> **Status:** sintaxe validada (`php -l`). **Não foi executado** contra um banco real:
> o ambiente onde foi escrito não tinha MySQL nem acesso ao Packagist. Siga o
> roteiro de teste abaixo numa **cópia** do banco antes de qualquer uso real.

Decisões aplicadas:
- conta ativa/excluída: `active` (boolean) + `softDeletes` (sem `status`/`is_deleted`);
- preço: o vigente fica em `products.base_price`/`promotional_price`, e o histórico em `product_prices`/`product_promotions`;
- banco atual preservado por uma migration de conversão (pasta `conversao/`).

---

## Arquivos

| Arquivo | Tabelas | Origem | Mudanças importantes |
|---|---|---|---|
| `0001_01_01_000000_create_users_table` | users, password_reset_tokens, sessions | cliente + admin | cpf/phone/birth_date em `string(512)` cifrados; `cpf_hash char(64) unique`; `role` enum(customer, admin); `active` + softDeletes |
| `0001_01_01_000001_create_cache_table` | cache, cache_locks | ambos | índice em `expiration` |
| `0001_01_01_000002_create_jobs_table` | jobs, job_batches, failed_jobs | ambos | — |
| `0001_01_01_000003_create_personal_access_tokens_table` | personal_access_tokens | ambos | uma só (o admin tinha duas) |
| `2026_09_01_000002_create_social_accounts_table` | social_accounts | cliente | substitui `google_id`/`provider`; unique por provedor |
| `2026_09_01_000003_create_addresses_table` | addresses | cliente | softDeletes; `state char(2)` |
| `2026_09_02_000001_create_products_table` | products | cliente + admin | + `model`, `color`; softDeletes no lugar de `is_deleted` |
| `2026_09_02_000002_create_product_sizes_table` | product_sizes | cliente | `stock` (o admin usava `quantity`) |
| `2026_09_02_000003_create_product_images_table` | product_images | cliente | — |
| `2026_09_02_000004_create_collections_table` | collections | cliente + admin | + `description`, `start_date`, `end_date` |
| `2026_09_02_000005_create_collection_product_table` | collection_product | cliente | PK composta (o admin chamava a tabela de `collection_products`) |
| `2026_09_02_000006_create_product_prices_table` | product_prices | admin | + `created_by` |
| `2026_09_02_000007_create_product_promotions_table` | product_promotions | admin | `active` boolean; + `created_by` |
| `2026_09_02_000008_create_collection_images_table` | collection_images | admin | `image_url` de 45 → 255 |
| `2026_09_02_000009_create_banners_table` | banners, banner_images | admin | `active` boolean; `image_url` 255 |
| `2026_09_02_000010_create_packed_products_table` | packed_products | admin | padrões do model |
| `2026_09_03_000001_create_carts_table` | carts | cliente | `session_id` unique |
| `2026_09_03_000002_create_cart_items_table` | cart_items | cliente | unique(cart, product, size) |
| `2026_09_04_000001_create_orders_table` | orders | cliente + admin | `user_id` **RESTRICT**; cópia do endereço (`shipping_*`); status só em inglês |
| `2026_09_04_000002_create_order_items_table` | order_items | cliente | `product_id` **RESTRICT**; `product_name` congelado |
| `2026_09_04_000003_create_payments_table` | payments | cliente + admin | `gateway_payment_id` **unique**; `response_payload` LONGTEXT cifrado; `paid_at`; últimos 4 dígitos/bandeira; `order_id` RESTRICT |
| `2026_09_04_000004_create_order_history_table` | order_history | admin | status em inglês; `changed_by` |
| `2026_09_05_000001_create_audit_logs_table` | audit_logs | cliente + admin | FK para users de volta; `autor` → `actor_label`, `detalhes` → `description`; colunas duplicadas removidas; + `user_agent` |
| `conversao/2026_11_04_000001_converter_banco_legado` | — | novo | converte o banco atual preservando os dados (ver abaixo) |
| `conversao/2026_11_04_000002_remover_tabelas_legadas` | — | novo | apaga as `legacy_*` (**irreversível**) |

Removidos de propósito (não têm equivalente): a migration `create_users` que não fazia nada, a duplicata de `personal_access_tokens`, `harden_security_columns` e `translate_database_schema_to_english`. O trabalho delas foi absorvido pelo schema novo e pela conversão.

---

## O que a conversão faz

`conversao/2026_11_04_000001_converter_banco_legado.php` (somente MySQL/MariaDB):

1. **Pré-checagem somente-leitura.** Se encontrar algo que impeça a conversão, para **antes de alterar qualquer coisa** e lista os problemas: pedido sem cliente, item ou pagamento sem pedido, e-mail duplicado, APP_KEY vazia, dados nas tabelas em português e em inglês ao mesmo tempo.
2. Guarda e remove as FKs antigas e renomeia as tabelas atuais para `legacy_<nome>`. Funciona tanto com o banco já traduzido (`users`) quanto com o original (`usuario`).
3. Cria o schema novo rodando as próprias migrations unificadas, sem nenhuma definição duplicada.
4. Copia os dados **preservando os IDs** e convertendo:
   - `status`/`is_deleted` viram `active`/`deleted_at`; `role` `cliente` vira `customer`;
   - CPF, telefone e nascimento são cifrados (se estavam em texto puro) e o `cpf_hash` é recalculado;
   - **senhas em texto puro** viram hash (com aviso);
   - `google_id` vira um registro em `social_accounts`;
   - status de pedidos, histórico e pagamentos passam do português para o inglês;
   - produtos e coleções ganham `slug`; pedidos ganham um `order_number` aleatório;
   - o pedido recebe uma cópia do endereço e o item recebe o nome do produto;
   - o preço vigente e a promoção ativa vêm do histórico antigo;
   - o `response_payload` dos pagamentos é cifrado;
   - colunas legadas da auditoria são levadas para as novas.
5. Ajusta a tabela `migrations` para o conjunto novo (com backup em `legacy_migrations`).

Se algo falhar a partir do passo 2, o banco é **restaurado automaticamente**: as tabelas novas saem, as `legacy_*` voltam ao nome original e as FKs são recriadas.
Os avisos (registros ajustados ou ignorados) saem no terminal e em `storage/logs/conversao_banco_legado.log`.

Não são copiados (são transitórios): sessões, tokens Sanctum, cache, filas e tokens de reset. **Todos precisarão fazer login de novo.**

---

## Ordem de implantação

1. **Adaptar o código** (seção seguinte). Com o schema novo e o código antigo, o painel quebra.
2. **Backup:** `mysqldump -u root -p Coturno_Cia > backup_antes_unificacao.sql`
3. **Cópia de teste:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE Coturno_Cia_teste"
   mysql -u root -p Coturno_Cia_teste < backup_antes_unificacao.sql
   ```
4. **Trocar as migrations** (numa branch):
   - mova o conteúdo atual de `database/migrations` para fora da pasta (ex.: `database/migrations_antigas_backup/`);
   - copie o conteúdo desta pasta (os `.php` **e** a subpasta `conversao/`) para `database/migrations/`. O `php artisan migrate` não lê subpastas, então `conversao/` só roda com `--path`.
5. **Converter a cópia** (no `.env`: `DB_DATABASE=Coturno_Cia_teste` e **a mesma `APP_KEY`**):
   ```bash
   php artisan migrate --path=database/migrations/conversao/2026_11_04_000001_converter_banco_legado.php
   php artisan migrate:status     # tudo como "Ran"
   php artisan migrate            # deve dizer "Nothing to migrate"
   ```
6. **Conferir:** compare a quantidade de registros (`SELECT COUNT(*)` em `users`/`legacy_users`, `orders`/`legacy_pedido` etc.), faça login no painel, abra um cliente (CPF decifrado), um pedido e um pagamento.
7. **Testar o desfazer** na cópia: `php artisan migrate:rollback --path=database/migrations/conversao/2026_11_04_000001_converter_banco_legado.php` deve restaurar o banco. Depois rode a conversão de novo.
8. **Instalação do zero:** num banco vazio, `php artisan migrate:fresh` deve criar tudo sem erro, tanto em MySQL quanto em SQLite (`.env.example`).
9. Com tudo validado, repita os passos 2, 4 e 5 no banco real.
10. Depois de alguns dias funcionando e com o backup guardado:
    `php artisan migrate --path=database/migrations/conversao/2026_11_04_000002_remover_tabelas_legadas.php`

> ⚠️ O rollback da conversão apaga as tabelas novas, então só faça logo depois de converter, antes de entrar dado novo.
> ⚠️ Nunca rode `key:generate` no meio do processo: CPF, telefone e `cpf_hash` dependem da APP_KEY.

---

## Ajustes de código necessários (próxima etapa)

**Segurança (obrigatórios):**
- `EnsureUserIsAdmin` (barreira 2): trocar `status ?? 'ativo'` / `is_deleted` por `! $user->active || $user->trashed()`. Sem valor padrão "ativo": se a informação faltar, o acesso é negado.
- `User`: trait `SoftDeletes`; `isActive()` passa a usar `active` + `deleted_at`; tirar `google_id`/`provider` do `$fillable`/`$hidden`; `active` e `role` continuam **fora** do `$fillable`.
- `UserPrivilegeService`: ativar/desativar passa a usar `active`; excluir vira `$user->delete()` (soft) + `active = false`; a checagem de "último admin ativo" passa a ser `role = admin AND active = 1 AND deleted_at IS NULL`.
- `AdminUserSeeder`: `'active' => true` no lugar de `status`/`is_deleted`.
- `Payment`: cast `'response_payload' => 'encrypted:array'`; `payment_date` vira `paid_at`, `payment_method` vira `method`.
- `AuditLogger` e tela `/admin/logs`: `autor` vira `actor_label`, `detalhes` vira `description`, sai `acao`/`tabela`/`registro_id`/`ip`, entra `user_agent`.

**Compatibilidade:**
- `Product`: `SoftDeletes`; `active` passa a ser boolean (o `ProductController` hoje grava `'ativo'`/`'inativo'`); a criação passa a exigir `name`, `slug` e `base_price`; ao salvar preço/promoção, atualizar `base_price`/`promotional_price`.
- `ProductSize`: `quantity` vira `stock`. `Collection`: pivô `collection_product` e `slug` obrigatório. `Address`: `SoftDeletes`.
- `Order`/`OrderItem`/`OrderHistory`: tirar os status em português dos controllers; `order_date` vira `created_at`; gerar `order_number` aleatório; preencher `shipping_*`, `product_name` e `subtotal` no checkout.
- Models com `public $timestamps = false` (Cart, CartItem, Banner etc.): as tabelas agora têm timestamps, então dá para ligar.
- `CartItem`: no schema novo, `cart_items` ganha um `id` próprio. Troque `primaryKey = 'cart_id'` por `id`, use o trait `HasHashid` e faça o `CartItemController` buscar por `CartItem::findByHashid($id)`. Hoje ele usa o ID público do carrinho, porque a tabela antiga não tem `id`.
- `OrderHistory`: o status passa de código numérico (0–5) para texto (`pending`, `paid`...). A conversão já traduz os códigos antigos; os controllers precisam gravar o texto.
- IDs cifrados (Hashid): o namespace é o nome da tabela. A conversão mantém IDs e nomes de tabela, então os IDs públicos continuam válidos. A exceção é o pivô `collection_products` → `collection_product`, que não expõe ID próprio.
- `App\Support\Hashid`: se a etiqueta usar o nome da tabela, os IDs públicos de tabelas renomeadas mudam. Conferir.
- Back-end do cliente: tirar `'cliente'` e os status em português; o model `User` dele precisa cifrar `birth_date` (agora é string cifrada, não `date`).
- `EncryptSensitiveData` e `tests/Feature/SecurityTest.php`: revisar as colunas antigas.
