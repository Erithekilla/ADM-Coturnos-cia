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

Para rodar o servidor localmente:

```bash
php artisan serve
```

A API estará acessível em: `http://127.0.0.1:8000` ou `http://localhost:8000`

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

## 🔒 Arquitetura de Segurança Implementada

1. **Tokens de API (Sanctum):**  
   Rotas protegidas devem receber o cabeçalho HTTP:
    ```http
    Authorization: Bearer <SEU_TOKEN_AQUI>
    Accept: application/json
    ```
2. **Criptografia Bidirecional (AES-256):**  
   Os campos `cpf` e `telefone` são criptografados de forma transparente via Eloquent Casts na model `User`:
    ```php
    protected function casts(): array {
        return [
            'password' => 'hashed',
            'cpf'      => 'encrypted',
            'telefone' => 'encrypted',
        ];
    }
    ```
3. **Anti-IDOR em Endereços:**  
   Nunca busque endereços diretamente por `Endereco::find($id)`. Sempre vincule ao usuário autenticado:
    ```php
    $endereco = $request->user()->enderecos()->findOrFail($id);
    ```
4. **Baixa Concorrente de Estoque:**  
   Ao decrementar estoque de pedidos aprovados, use transação atômica com bloqueio pessimista:
    ```php
    DB::transaction(function() use ($item) {
        $grade = ProductSize::where('product_id', $item->product_id)
            ->where('size', $item->size)
            ->lockForUpdate()
            ->first();
        $grade->decrement('stock', $item->quantity);
    });
    ```

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
