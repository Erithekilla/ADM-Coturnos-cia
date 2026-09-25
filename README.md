# 🥾 Coturnos & Cia — Back-end Central (API RESTful)

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Sanctum](https://img.shields.io/badge/Auth-Sanctum-red?style=for-the-badge)](https://laravel.com/docs/sanctum)
[![Mercado Pago](https://img.shields.io/badge/Payments-Mercado%20Pago-009EE3?style=for-the-badge)](https://www.mercadopago.com.br/developers)
[![Docker](https://img.shields.io/badge/Environment-Docker%20Sail-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://laravel.com/docs/sail)

Repositório central de back-end da plataforma de e-commerce **Coturnos & Cia**, construído para atender tanto a loja virtual de clientes quanto o painel administrativo através de uma arquitetura unificada de API RESTful.

---

## 📌 Principais Recursos Implementados

- **Esquema de Banco de Dados Oficial (14 Migrations):**
    - Gestão de Usuários e Vínculo Multi-Redes Sociais (1:N para Google, Facebook, Apple).
    - Catálogo de Coturnos com precificação, `slug` amigável e exclusão lógica (_Soft Deletes_).
    - Grade de Estoque por Tamanho (37 ao 44) com chave composta única `[product_id, size]`.
    - Galeria de Imagens integrada ao Cloudinary.
    - Carrinhos de Compras dinâmicos (com suporte a _Guest Checkout_ via `session_id`).
    - Pedidos e Pagamentos integrados ao Checkout Transparente do Mercado Pago (Pix e Cartão).
    - Auditoria administrativa completa por IP e data.
- **Autenticação Segura com Laravel Sanctum:**
    - Emissão e controle de tokens de API SPA / Bearer Tokens.
- **Segurança e Conformidade LGPD:**
    - Criptografia bidirecional **AES-256-CBC** nativa para dados sensíveis (`cpf`, `telefone`) via Eloquent Casts derivada da `APP_KEY`.
    - Hashing irreversível de senhas com **Argon2id / Bcrypt**.
    - Regras estritas de proteção Anti-IDOR para manipulação de endereços e pedidos.

---

## 🚀 Como Executar o Projeto

Para o guia detalhado com todas as opções de ambiente, comandos de manutenção e boas práticas, consulte:
👉 **[Guia Completo de Execução e Manipulação (COMO_RODAR.md)](./COMO_RODAR.md)**

### Inicialização Rápida (Quickstart)

```bash
# 1. Instalar dependências PHP
composer install

# 2. Configurar variáveis de ambiente
cp .env.example .env

# 3. Gerar chave da aplicação (AES-256)
php artisan key:generate

# 4. Criar o banco e rodar as migrations oficiais
php artisan migrate:fresh

# 5. Iniciar o servidor local
php artisan serve
```

A API estará acessível em: `http://localhost:8000`

---

## 📖 Documentação Adicional

- 📋 **[COMO_RODAR.md](./COMO_RODAR.md):** Manual completo de comandos Artisan, rotas, segurança e desenvolvimento.
- 📝 **[CHANGELOG.md](./CHANGELOG.md):** Histórico de versões e alterações implementadas na base de código.
- ⚙️ **[.env.example](./.env.example):** Gabarito de variáveis de ambiente comentado e documentado.
