<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas Web - Painel Administrativo Front-end (React)
|--------------------------------------------------------------------------
|
| Todas as rotas legadas do React anterior (loja e views blade) foram
| removidas. O backend atua como API REST e serve a SPA do React
| localizada na pasta "front-end admin".
|
*/

// Redireciona a raiz para o Painel Administrativo (/admin)
Route::get('/', function () {
    return redirect('/admin');
});

// Serve a aplicação React do Front-end Admin (/admin e todas as suas sub-rotas)
Route::get('/admin/{any?}', function () {
    // 1. Tenta carregar o build compilado na pasta public/admin
    $adminPublicIndex = public_path('admin/index.html');
    if (file_exists($adminPublicIndex)) {
        return response()->file($adminPublicIndex);
    }

    // 2. Fallback: Tenta carregar diretamente da pasta front-end admin/Admin_Coturno-main/dist
    $adminDistIndex = base_path('../front-end admin/Admin_Coturno-main/dist/index.html');
    if (file_exists($adminDistIndex)) {
        return response()->file($adminDistIndex);
    }

    return response(
        '<!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Painel Administrativo - Coturno & Cia</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #0f172a; color: #f8fafc; }
                .card { background: #1e293b; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); max-width: 520px; text-align: center; border: 1px solid #334155; }
                h1 { font-size: 1.5rem; margin-bottom: 0.75rem; color: #38bdf8; }
                p { font-size: 0.95rem; line-height: 1.6; color: #94a3b8; margin-bottom: 1.5rem; }
                code { background: #0f172a; padding: 0.3rem 0.6rem; border-radius: 6px; font-family: monospace; color: #f43f5e; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>Painel Administrativo</h1>
                <p>O arquivo de build do React não foi encontrado em <code>public/admin/index.html</code>.</p>
                <p>Para compilar o front-end administrativo, execute na pasta <code>front-end admin/Admin_Coturno-main</code>:<br><br><code>npm run build</code></p>
            </div>
        </body>
        </html>',
        200
    )->header('Content-Type', 'text/html; charset=utf-8');
})->where('any', '.*');
