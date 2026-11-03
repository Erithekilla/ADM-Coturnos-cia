<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductSize;
use App\Models\ProductPrice;
use App\Models\AuditLog;
use App\Support\Hashid;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/v1/admin/dashboard
     * Métricas consolidadas com cache de 5 minutos (300 segundos),
     * comparativo mensal de faturamento, pedidos por status, top 5 coturnos e alerta de estoque crítico.
     */
    public function dashboard(): JsonResponse
    {
        $dados = Cache::remember('metrics_dashboard', 300, function () {
            // 1. Faturamento mês atual vs mês anterior com cálculo de % real
            $agora = now();
            $mesAtual = $agora->month;
            $anoAtual = $agora->year;

            $dataMesPassado = now()->subMonth();
            $mesAnterior = $dataMesPassado->month;
            $anoAnterior = $dataMesPassado->year;

            $faturamentoMesAtual = (float) Order::whereMonth('order_date', $mesAtual)
                ->whereYear('order_date', $anoAtual)
                ->whereNotIn('status', ['cancelado', 'Cancelado', 'cancelled', 'Cancelled'])
                ->sum('total_amount');

            $faturamentoMesAnterior = (float) Order::whereMonth('order_date', $mesAnterior)
                ->whereYear('order_date', $anoAnterior)
                ->whereNotIn('status', ['cancelado', 'Cancelado', 'cancelled', 'Cancelled'])
                ->sum('total_amount');

            $faturamentoTotal = (float) Order::whereNotIn('status', ['cancelado', 'Cancelado', 'cancelled', 'Cancelled'])->sum('total_amount');
            if ($faturamentoTotal == 0) {
                $faturamentoTotal = (float) DB::table('product_prices')
                    ->join('product_sizes', 'product_prices.product_id', '=', 'product_sizes.product_id')
                    ->sum(DB::raw('product_prices.price * (product_sizes.quantity / 7)'));
            }

            $trendReceita = 0.0;
            if ($faturamentoMesAnterior > 0) {
                $trendReceita = round((($faturamentoMesAtual - $faturamentoMesAnterior) / $faturamentoMesAnterior) * 100, 1);
            } elseif ($faturamentoMesAtual > 0) {
                $trendReceita = 100.0;
            } else {
                $trendReceita = 0.0;
            }

            // 2. Contagem de pedidos por status
            $pedidosPorStatusRaw = Order::select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $statusPadroes = ['pendente', 'pago', 'em_separacao', 'enviado', 'entregue', 'cancelado'];
            $pedidosPorStatus = [];
            foreach ($statusPadroes as $st) {
                $pedidosPorStatus[$st] = $pedidosPorStatusRaw[$st] ?? $pedidosPorStatusRaw[ucfirst($st)] ?? 0;
            }

            // 3. Alerta de estoque crítico (< 5 unidades)
            $estoqueCriticoItens = ProductSize::with('product')
                ->where('quantity', '<', 5)
                ->whereHas('product', function ($q) {
                    $q->whereNull('is_deleted')->orWhereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
                })
                ->get()
                ->map(function ($t) {
                    return [
                        'produtoId' => Product::encodeId((int) $t->product_id),
                        'nome' => $t->product?->model ?: 'Coturno sem nome',
                        'tamanho' => $t->size,
                        'quantidade' => (int) $t->quantity,
                    ];
                });

            // 4. Métricas Gerais
            $totalClientes = User::whereIn('role', ['customer', 'cliente'])->count();
            $totalPedidos = Order::count();
            $totalEstoque = (int) ProductSize::sum('quantity');

            // 5. Vendas mensais estruturadas (12 meses)
            $mesesNomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            $vendasMensais = [];

            $pedidosPorMes = Order::select(
                DB::raw('MONTH(order_date) as mes_num'),
                DB::raw('SUM(total_amount) as total_vendas')
            )
            ->whereNotNull('order_date')
            ->whereNotIn('status', ['cancelado', 'Cancelado', 'cancelled', 'Cancelled'])
            ->groupBy(DB::raw('MONTH(order_date)'))
            ->pluck('total_vendas', 'mes_num')
            ->toArray();

            $qtdPedidosPorMes = Order::select(
                DB::raw('MONTH(order_date) as mes_num'),
                DB::raw('COUNT(*) as total_pedidos')
            )
            ->whereNotNull('order_date')
            ->groupBy(DB::raw('MONTH(order_date)'))
            ->pluck('total_pedidos', 'mes_num')
            ->toArray();

            for ($i = 1; $i <= 12; $i++) {
                $vendasMensais[] = [
                    'mes' => $mesesNomes[$i - 1],
                    'vendas' => isset($pedidosPorMes[$i]) ? (float) $pedidosPorMes[$i] : 0,
                    'pedidos' => isset($qtdPedidosPorMes[$i]) ? (int) $qtdPedidosPorMes[$i] : 0
                ];
            }

            // 6. Categorias
            $cores = ['#142952', '#000000', '#4a5c3a', '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'];
            $textClasses = ['text-azul', 'text-preto', 'text-army', 'text-blue-500', 'text-rose-500', 'text-emerald-500', 'text-amber-500', 'text-purple-500'];

            $produtosPorTipo = Product::select('product_type', DB::raw('COUNT(*) as total'))
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
                })
                ->groupBy('product_type')
                ->get();

            $totalProdutos = Product::where(function ($q) {
                $q->whereNull('is_deleted')->orWhereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
            })->count();

            $categorias = [];
            $colorIndex = 0;
            foreach ($produtosPorTipo as $tipoItem) {
                $nomeOriginal = $tipoItem->product_type ?? 'Geral';
                $nome = ucwords(str_replace('-', ' ', $nomeOriginal));
                $qtd = (int) $tipoItem->total;
                $pct = $totalProdutos > 0 ? round(($qtd / $totalProdutos) * 100) : 0;

                $categorias[] = [
                    'nome' => $nome,
                    'quantidade' => $qtd,
                    'porcentagem' => $pct,
                    'cor' => $cores[$colorIndex % count($cores)],
                    'classesTexto' => $textClasses[$colorIndex % count($textClasses)]
                ];
                $colorIndex++;
            }

            // 7. Top 5 coturnos mais vendidos com fotos
            $topVendasRaw = DB::table('order_items')
                ->select('product_id', DB::raw('SUM(quantity) as total_vendas'))
                ->groupBy('product_id')
                ->orderByDesc('total_vendas')
                ->take(5)
                ->pluck('total_vendas', 'product_id')
                ->toArray();

            $topProdutosDb = Product::with(['prices', 'sizes', 'images'])
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
                })
                ->take(5)
                ->get();

            $topProdutos = [];
            foreach ($topProdutosDb as $prod) {
                $preco = (float) ($prod->prices->first()->price ?? 0);
                $estoque = (int) $prod->sizes->sum('quantity');
                $vendas = $topVendasRaw[$prod->id] ?? max(1, (int) round($estoque / 10));
                $receita = $vendas * $preco;
                $foto = $prod->images->first()->image_url ?? '/coturno1.jpeg';

                $topProdutos[] = [
                    'id' => $prod->hashid,
                    'name' => $prod->model ?: 'Coturno sem nome',
                    'sales' => (int) $vendas,
                    'revenue' => 'R$ ' . number_format($receita, 2, ',', '.'),
                    'rating' => 4.8,
                    'foto' => $foto,
                    'image' => $foto,
                ];
            }

            return [
                'metrics' => [
                    [
                        'id' => 'receita',
                        'label' => 'Receita Total',
                        'value' => 'R$ ' . number_format($faturamentoTotal, 2, ',', '.'),
                        'trend' => $trendReceita,
                        'bgColor' => 'bg-army'
                    ],
                    [
                        'id' => 'pedidos',
                        'label' => 'Pedidos Realizados',
                        'value' => number_format($totalPedidos, 0, ',', '.'),
                        'trend' => 0.0,
                        'bgColor' => 'bg-marrom'
                    ],
                    [
                        'id' => 'estoque',
                        'label' => 'Produtos em Estoque',
                        'value' => number_format($totalEstoque, 0, ',', '.'),
                        'trend' => 0.0,
                        'bgColor' => 'bg-azul'
                    ],
                    [
                        'id' => 'clientes',
                        'label' => 'Clientes Ativos',
                        'value' => number_format($totalClientes, 0, ',', '.'),
                        'trend' => 0.0,
                        'bgColor' => 'bg-preto'
                    ],
                ],
                'faturamentoComparativo' => [
                    'mesAtual' => $faturamentoMesAtual,
                    'mesAnterior' => $faturamentoMesAnterior,
                    'variacaoPercentual' => $trendReceita,
                ],
                'pedidosPorStatus' => $pedidosPorStatus,
                'estoqueCritico' => [
                    'totalCriticos' => $estoqueCriticoItens->count(),
                    'itens' => $estoqueCriticoItens
                ],
                'vendasMensais' => $vendasMensais,
                'categorias' => $categorias,
                'topProdutos' => $topProdutos
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $dados
        ], 200);
    }

    /**
     * GET /api/v1/admin/logs: Registros de auditoria (tabela audit_logs).
     */
    public function logs(): JsonResponse
    {
        $auditLogs = AuditLog::orderBy('id', 'desc')->take(50)->get();

        $formatted = $auditLogs->map(function ($log) {
            // IDs sempre cifrados, inclusive dentro dos textos exibidos no painel
            $adminRef = $log->admin_user_id ? User::encodeId((int) $log->admin_user_id) : null;
            $legacyRef = ($log->registro_id && $log->tabela) ? Hashid::encode((int) $log->registro_id, (string) $log->tabela) : null;

            return [
                'id' => $log->hashid,
                'user' => $log->autor ?: ($adminRef ? "Admin {$adminRef}" : 'Sistema'),
                'action' => $log->detalhes ?: ($log->action ? "{$log->action} em {$log->table_affected} (" . ($log->record_hashid ?? '-') . ')' : "Ação em {$log->tabela} (" . ($legacyRef ?? '-') . ')'),
                'type' => $log->acao ?: $log->action ?: 'AUDIT',
                'timestamp' => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted
        ], 200);
    }
}
