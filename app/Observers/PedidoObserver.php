<?php

namespace App\Observers;

use App\Models\Pedido;
use App\Models\AuditLog;

class PedidoObserver
{
    private function getAuthor(): string
    {
        $user = request()->user();
        if ($user) {
            return ($user->nome ?? 'Usuário') . ' (' . ($user->email ?? '') . ')';
        }
        return 'Administrador';
    }

    public function created(Pedido $pedido): void
    {
        AuditLog::create([
            'autor' => $this->getAuthor(),
            'acao' => 'ORDER_CREATE',
            'tabela' => 'pedido',
            'registro_id' => $pedido->id,
            'ip' => request()->ip(),
            'detalhes' => json_encode([
                'valor_total' => $pedido->valor_total,
                'status' => $pedido->status,
                'usuario_id' => $pedido->usuario_id,
            ]),
        ]);
    }

    public function updated(Pedido $pedido): void
    {
        $changes = $pedido->getChanges();
        $statusMsg = isset($changes['status']) ? "Status alterado para {$changes['status']}" : "Pedido atualizado";

        AuditLog::create([
            'autor' => $this->getAuthor(),
            'acao' => 'ORDER_STATUS',
            'tabela' => 'pedido',
            'registro_id' => $pedido->id,
            'ip' => request()->ip(),
            'detalhes' => $statusMsg . (isset($changes['codigo_rastreio']) ? " | Rastreio: {$changes['codigo_rastreio']}" : ""),
        ]);
    }

    public function deleted(Pedido $pedido): void
    {
        AuditLog::create([
            'autor' => $this->getAuthor(),
            'acao' => 'ORDER_DELETE',
            'tabela' => 'pedido',
            'registro_id' => $pedido->id,
            'ip' => request()->ip(),
            'detalhes' => 'Pedido #' . $pedido->id . ' cancelado/removido',
        ]);
    }
}
