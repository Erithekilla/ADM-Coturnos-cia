# 🥾 Guia de Execução e Manipulação — Back-end Coturnos & Cia

Este documento é o guia oficial para configurar, executar e manipular o back-end central da aplicação **Coturnos & Cia** (API REST em Laravel 11 com suporte a Sanctum e MySQL/SQLite).

---

## 📋 Pré-requisitos

Para rodar a aplicação localmente, certifique-se de ter instalado:

- **PHP >= 8.2** (com extensões `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `sqlite3`)
- **Composer** (gerenciador de dependências PHP)
- _(Opcional)_ **Docker & Docker Compose** (caso utilize o ambiente oficial via Laravel Sail)

---

## 🚀 Passo a Passo: Configuração Inicial

### 1. Clonar o Repositório e Instalar Dependências

```bash
# Clone o seu repositório
git clone https://github.com/luisesoliveira-dev/Coturnos-Cia_Back-end_Cliente.git
cd Coturnos_Back-end_Cliente

# Instale os pacotes PHP
composer install
```

### 2. Configurar o Arquivo de Ambiente (`.env`)

Copie o gabarito oficial documentado para criar seu `.env` local:

```bash
cp .env.example .env
```

### 3. Gerar a Chave Mestra da Aplicação (`APP_KEY`)

```bash
php artisan key:generate
```

> ⚠️ **Atenção sobre a Criptografia (LGPD):**  
> A `APP_KEY` gerada é a chave mestra do algoritmo **AES-256-CBC** que criptografa o `cpf` e o `telefone` dos clientes no banco de dados.  
> Se você e seus colegas forem trocar dumps de banco de dados com clientes já cadastrados, **todos devem usar a mesma `APP_KEY`** no `.env` para conseguir descriptografar esses dados.

---

## 🗄️ Executando o Banco de Dados & Migrations

O projeto conta com **14 migrations estruturais** + a tabela de tokens do **Sanctum**.

### Opção A: Desenvolvimento Rápido com SQLite (Padrão Atual)

O Laravel já vem pré-configurado no `.env` para usar o SQLite local:

1. Certifique-se de que o arquivo existe:
    ```bash
    touch database/database.sqlite
    ```
2. Execute as migrations para criar todas as tabelas:
    ```bash
    php artisan migrate:fresh
    ```

### Opção B: Ambiente Oficial Docker Sail (MySQL 8.0 + Redis)

Quando o ambiente Sail estiver ativo:

```bash
# Iniciar os containers em background
./vendor/bin/sail up -d

# Executar as migrations dentro do container
./vendor/bin/sail artisan migrate:fresh --seed
```

---

## 💻 Iniciando o Servidor de Desenvolvimento

Para rodar o servidor localmente (porta padrão configurada: 8001):

```bash
php artisan serve
```

> **Nota sobre a Porta:** O projeto está configurado via `SERVER_PORT=8001` no `.env`. Caso deseje utilizar outra porta, altere a variável `SERVER_PORT` no seu `.env` ou execute:
> ```bash
> php artisan serve --port=8001
> ```

A API estará acessível em: `http://127.0.0.1:8001` ou `http://localhost:8001`

---

## 🛠️ Comandos Essenciais para Manipulação do Back-end

### 1. Banco de Dados e Migrations

- **Recriar todo o banco do zero (limpeza total):**
    ```bash
    php artisan migrate:fresh
    ```
- **Recriar o banco e rodar os seeders de teste:**
    ```bash
    php artisan migrate:fresh --seed
    ```
- **Verificar o status de execução das migrations:**
    ```bash
    php artisan migrate:status
    ```

### 2. Rotas e API

- **Listar todas as rotas registradas na API:**
    ```bash
    php artisan route:list --path=api
    ```

### 3. Criação de Novos Recursos (Padrão Laravel)

Sempre utilize os comandos `make` do Artisan para manter os padrões da arquitetura:

- **Criar um Controller de API:**
    ```bash
    php artisan make:controller Api/V1/NomeController --api
    ```
- **Criar um Model com Migration associada:**
    ```bash
    php artisan make:model Nome -m
    ```
- **Criar uma Form Request (validação de dados de entrada):**
    ```bash
    php artisan make:request Auth/LoginRequest
    ```
- **Criar um Seeder:**
    ```bash
    php artisan make:seeder NomeSeeder
    ```

### 4. Limpeza de Caches e Otimização

Se você alterar rotas, configurações no `.env` ou bindings que não parecerem ter efeito, rode:

```bash
php artisan optimize:clear
```

_(Limpa cache de rotas, configurações, views e eventos de uma só vez)._

---

## 🔒 Arquitetura de Segurança e Banco de Dados em Inglês

1. **Tabelas e Colunas Padronizadas em Inglês:**  
   Todas as tabelas e colunas foram traduzidas e padronizadas no banco de dados e no backend:
   - `users` (`first_name`, `last_name`, `cpf`, `phone`, `birth_date`, `email`, `role`, `status`, `is_deleted`, `cpf_hash`, `password`)
   - `addresses` (`user_id`, `zip_code`, `street`, `neighborhood`, `city`, `state`, `number`, `complement`, `is_default`)
   - `products` (`product_type`, `model`, `brand`, `description`, `active`, `color`, `status`, `is_deleted`)
   - `product_sizes` (`product_id`, `size`, `quantity`)
   - `product_images` (`image_url`, `public_id`, `product_id`)
   - `product_prices` (`price`, `effective_date`, `product_id`)
   - `product_promotions` (`promotional_price`, `start_date`, `end_date`, `active`, `price_id`)
   - `collections` (`name`, `description`, `start_date`, `end_date`)
   - `collection_products` (`collection_id`, `product_id`)
   - `collection_images` (`image_url`, `collection_id`)
   - `banners` (`active`)
   - `banner_images` (`image_url`, `banner_id`)
   - `carts` (`user_id`, `created_at`, `updated_at`)
   - `cart_items` (`cart_id`, `product_id`, `quantity`, `size`)
   - `orders` (`total_amount`, `status`, `tracking_code`, `order_date`, `user_id`, `address_id`)
   - `order_items` (`quantity`, `size`, `unit_price`, `product_id`, `order_id`)
   - `payments` (`payment_date`, `order_id`, `payment_method`, `status`, `amount`)
   - `order_history` (`status`, `order_id`, `status_date`, `notes`, `extra_info`)
   - `audit_logs` (`admin_user_id`, `action`, `table_affected`, `record_id`, `old_values`, `new_values`, `ip_address`)

2. **Compatibilidade Total com o Front-end:**  
   As classes em português (`Usuario`, `Produto`, `Pedido`, etc.) e os nomes de rotas/atributos legados continuam operacionais como aliases transparentes.

3. **Tokens de API (Sanctum):**  
   Rotas protegidas exigem o cabeçalho:
    ```http
    Authorization: Bearer <SEU_TOKEN_AQUI>
    Accept: application/json
    ```

4. **Criptografia Bidirecional (AES-256) e Blind Index:**  
   `cpf`, `phone` e `birth_date` utilizam o cast seguro `SafeEncrypted`. A busca por CPF é feita com o índice cego `cpf_hash`.

5. **Ofuscação de IDs (Hashids):**  
   Chaves primárias e estrangeiras expostas via JSON são convertidas em IDs ofuscados (ex: strings de 22 caracteres), prevenindo ataques de enumeração/IDOR.

---

## 📂 Estrutura de Arquivos Principais

- `app/Models/`: Modelos Eloquent (`User`, `Product`, etc.).
- `app/Http/Controllers/`: Controladores da API.
- `app/Http/Requests/`: Validações de formulários e requisições HTTP.
- `app/Services/`: Classes de regras de negócio desacopladas (ex: `SuperFreteService.php`).
- `database/migrations/`: As 14 migrations oficiais do banco de dados unificado.
- `routes/api.php`: Declaração dos endpoints e rotas REST da API.
- `config/sanctum.php`: Configurações de autenticação SPA e API tokens.
- `CHANGELOG.md`: Histórico detalhado de versões e alterações realizadas.
