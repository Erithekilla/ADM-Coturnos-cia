<?php

test('rotas administrativas exigem autenticação', function () {
    $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);
    $this->postJson('/api/upload-store-images')->assertStatus(401);
    $this->postJson('/api/v1/upload-store-images')->assertStatus(401);
    $this->getJson('/api/v1/usuarios')->assertStatus(401);
});

test('respostas da API trazem headers de segurança', function () {
    $this->getJson('/api/v1/admin/logs')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});
