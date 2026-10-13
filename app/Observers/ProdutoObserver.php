<?php

namespace App\Observers;

use App\Models\Produto;
use App\Models\AuditLog;

class ProdutoObserver
{
    private function getAuthor(): string
    {
        $user = request()->user();
        if ($user) {
            return ($user->nome ?? 'Usuário') . ' (' . ($user->email ?? '') . ')';
        }
        return 'Administrador';
    }

    public function created(Produto $produto): void
    {
        AuditLog::create([
            'autor' => $this->getAuthor(),
            'acao' => 'PRODUCT_CREATE',
            'tabela' => 'produtos',
            'registro_id' => $produto->id,
            'ip' => request()->ip(),
            'detalhes' => json_encode([
                'modelo' => $produto->modelo,
                'tipo' => $produto->tipo_do_produto,
                'marca' => $produto->marca,
            ]),
        ]);
    }

    public function updated(Produto $produto): void
    {
        AuditLog::create([
            'autor' => $this->getAuthor(),
            'acao' => 'PRODUCT_UPDATE',
            'tabela' => 'produtos',
            'registro_id' => $produto->id,
            'ip' => request()->ip(),
            'detalhes' => json_encode($produto->getChanges()),
        ]);
    }

    public function deleted(Produto $produto): void
    {
        AuditLog::create([
            'autor' => $this->getAuthor(),
            'acao' => 'PRODUCT_DELETE',
            'tabela' => 'produtos',
            'registro_id' => $produto->id,
            'ip' => request()->ip(),
            'detalhes' => 'Produto removido/arquivado: ' . ($produto->modelo ?? $produto->id),
        ]);
    }
}

