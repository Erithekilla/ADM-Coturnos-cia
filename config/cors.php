<?php

/*
 * 6.2 — Isolamento de CORS.
 * Só as origens explícitas do painel administrativo são aceitas.
 * Configure em CORS_ALLOWED_ORIGINS (lista separada por vírgulas).
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173,http://localhost:8001,http://127.0.0.1:8001,http://localhost:8000,http://127.0.0.1:8000,http://localhost:8080,http://127.0.0.1:8080'
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'Cache-Control', 'Pragma'],

    'exposed_headers' => [],

    'max_age' => 600,

    // Autenticação é via Bearer token, não via cookie.
    'supports_credentials' => false,
];
