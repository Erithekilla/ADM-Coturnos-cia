<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\TamanhoProduto;
use App\Models\HistoricoPedido;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminPedidoController extends Controller
{
    /**
     * Matriz de transições permitidas de status de pedidos.
     */
    private const ALLOWED_TRANSITIONS = [
        'pendente' => ['pago', 'cancelado'],
        'pago' => ['em_separacao', 'cancelado'],
        'em_separacao' => ['enviado', 'cancelado'],
        'enviado' => ['entregue'],
        'entregue' => [],
        'cancelado' => [],
    ];

    /**
     * PATCH /api/v1/admin/pedidos/{id}/status
     * Atualiza o status do pedido validando a máquina de estados,
     * exigindo código de rastreio para 'enviado' e aplicando
     * ESTORNO AUTOMÁTICO DE ESTOQUE no cancelamento via DB::transaction.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $pedido = Pedido::with('itens')->find($id);

        if (!$pedido) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pedido não encontrado.'
            ], 404);
        }

        $request->validate([
            'status' => 'required|string',
            'codigo_rastreio' => 'nullable|string|max:100',
        ]);

        $statusAtual = strtolower(trim((string)$pedido->status));
        $novoStatus = strtolower(trim((string)$request->input('status')));
        $codigoRastreio = $request->input('codigo_rastreio');

        // Se o status já for o mesmo, apenas atualiza rastreio se enviado
        if ($statusAtual === $novoStatus) {
            if ($codigoRastreio && \Illuminate\Support\Facades\Schema::hasColumn('pedido', 'codigo_rastreio')) {
                $pedido->codigo_rastreio = $codigoRastreio;
                $pedido->save();
            }
            return response()->json([
                'status' => 'success',
                'message' => 'O pedido já se encontra no status ' . $novoStatus,
                'data' => $pedido
            ], 200);
        }

        // Validação da máquina de estados (se statusAtual for conhecido)
        if (isset(self::ALLOWED_TRANSITIONS[$statusAtual])) {
            $permitidos = self::ALLOWED_TRANSITIONS[$statusAtual];
            if (!in_array($novoStatus, $permitidos)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Transição de status inválida: de '{$statusAtual}' para '{$novoStatus}'. Transições permitidas: " . implode(', ', $permitidos)
                ], 422);
            }
        }

        // Regra obrigatória: exigir código de rastreio para o status 'enviado'
        if ($novoStatus === 'enviado') {
            if (empty($codigoRastreio) && empty($pedido->codigo_rastreio)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "O campo 'codigo_rastreio' é obrigatório para transitar o pedido para o status 'enviado'."
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $pedido->status = $novoStatus;
            if (!empty($codigoRastreio)) {
                $pedido->codigo_rastreio = $codigoRastreio;
            }
            $pedido->save();

            // =========================================================================
            // REGRA CRÍTICA: ESTORNO AUTOMÁTICO DE ESTOQUE NO CANCELAMENTO
            // =========================================================================
            if ($novoStatus === 'cancelado') {
                foreach ($pedido->itens as $item) {
                    $query = TamanhoProduto::where('produtos_id', $item->produtos_id);
                    if (!empty($item->tamanho)) {
                        $query->where('tamanho', $item->tamanho);
                    }
                    $query->increment('quantidade', (int) ($item->quantidade ?: 1));
                }
            }

            $statusInt = match ($novoStatus) {
                'cancelado' => 0,
                'pendente' => 1,
                'pago' => 2,
                'em_separacao' => 3,
                'enviado' => 4,
                'entregue' => 5,
                default => 1,
            };

            // Registra histórico do pedido
            HistoricoPedido::create([
                'pedido_id' => $pedido->id,
                'status' => $statusInt,
                'data_status' => now(),
                'observacao' => "Status atualizado para '{$novoStatus}'" . ($codigoRastreio ? " (Rastreio: {$codigoRastreio})" : ""),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Status do pedido #{$pedido->id} atualizado para '{$novoStatus}' com sucesso!" . ($novoStatus === 'cancelado' ? " (Estoque estornado automaticamente)." : ""),
                'data' => $pedido->fresh(['itens', 'usuario', 'endereco'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao atualizar status do pedido.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
}
