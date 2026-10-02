<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PedidoController extends Controller
{
    /**
     * Lista todos os pedidos com seus relacionamentos reais do banco de dados MySQL.
     */
    public function index(): JsonResponse
    {
        $pedidos = Pedido::with(['usuario', 'endereco', 'itens.produto', 'pagamentos', 'historicos'])->orderBy('id', 'desc')->get();

        $data = $pedidos->map(function ($p) {
            return [
                'id' => $p->id,
                'valorTotal' => (float) $p->valor_total,
                'status' => $p->status ?: 'Pendente',
                'statusEnvio' => $p->status ?: 'Pendente',
                'statusCompra' => 'Aprovado',
                'dataPedido' => $p->data_pedido ? date('Y-m-d\TH:i:s', strtotime($p->data_pedido)) : date('Y-m-d\TH:i:s'),
                'usuarioId' => $p->usuario_id,
                'cliente' => $p->usuario ? ($p->usuario->nome . ' ' . $p->usuario->sobrenome) : 'Cliente #' . $p->usuario_id,
                'enderecoId' => $p->endereco_id,
                'produtos_do_pedido' => $p->itens->map(function ($item) {
                    return [
                        'produto' => [
                            'id' => $item->produto?->id,
                            'nome' => $item->produto?->modelo ?: 'Calçado #' . $item->produtos_id,
                            'categoria' => $item->produto?->tipo_do_produto ?: 'Coturno',
                            'preco' => (float) ($item->preco_unitario ?? 0),
                            'status' => 'Em Estoque',
                        ],
                        'quantidade' => $item->quantidade ?? 1,
                        'precoUnitario' => (float) ($item->preco_unitario ?? 0),
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * Grava um novo pedido no banco de dados MySQL.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'valor_total' => 'required|numeric',
            'status' => 'required|string|max:45',
            'data_pedido' => 'required|date',
            'usuario_id' => 'required|integer|exists:usuario,id',
            'endereco_id' => 'required|integer|exists:endereco,id',
        ]);

        $record = Pedido::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pedido criado com sucesso!',
            'data' => $record
        ], 201);
    }

    /**
     * Exibe os detalhes de um pedido específico.
     */
    public function show($id): JsonResponse
    {
        $record = Pedido::with(['usuario', 'endereco', 'itens.produto', 'pagamentos', 'historicos'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pedido não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    /**
     * Atualiza o pedido no banco de dados MySQL.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $record = Pedido::with('itens')->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pedido não encontrado.'
            ], 404);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $statusAnterior = strtolower(trim((string)$record->status));
            $novoStatusRaw = $request->input('status') ?: $request->input('statusEnvio');

            if ($novoStatusRaw) {
                $novoStatus = strtolower(trim((string)$novoStatusRaw));
                $record->status = $novoStatus;

                // Estorno automático de estoque se status virou cancelado
                if ($novoStatus === 'cancelado' && $statusAnterior !== 'cancelado') {
                    foreach ($record->itens as $item) {
                        $query = \App\Models\TamanhoProduto::where('produtos_id', $item->produtos_id);
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

                // Histórico de status
                \App\Models\HistoricoPedido::create([
                    'pedido_id' => $record->id,
                    'status' => $statusInt,
                    'data_status' => now(),
                    'observacao' => "Status atualizado para '{$novoStatus}' via painel",
                ]);
            }

            if ($request->has('codigo_rastreio') && \Illuminate\Support\Facades\Schema::hasColumn('pedido', 'codigo_rastreio')) {
                $record->codigo_rastreio = $request->input('codigo_rastreio');
            }

            if ($request->has('valor_total') || $request->has('valorTotal')) {
                $record->valor_total = (float) ($request->input('valor_total') ?: $request->input('valorTotal'));
            }

            $record->save();

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pedido atualizado com sucesso no banco de dados!',
                'data' => $record
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao atualizar pedido.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove o pedido do banco de dados MySQL.
     */
    public function destroy($id): JsonResponse
    {
        $record = Pedido::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pedido não encontrado.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Pedido removido com sucesso do banco de dados!'
        ], 200);
    }
}
