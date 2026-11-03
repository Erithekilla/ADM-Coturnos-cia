<?php

namespace App\Http\Controllers;

use App\Http\Presenters\UsuarioPresenter;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = Usuario::with('enderecos')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
            })
            ->orderBy('id', 'desc')
            ->get();

        $data = $usuarios->map(fn (Usuario $u) => UsuarioPresenter::listItem($u));

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function store(Request $request): JsonResponse
    {
        $nome = $request->input('nome') ?: $request->input('firstName');
        $sobrenome = $request->input('sobrenome') ?: $request->input('lastName');
        $email = $request->input('email');
        $cpf = $request->input('cpf');
        $phone = $request->input('phone') ?: $request->input('telefone');
        $type = $request->input('type');
        $role = $request->input('role') ?: (strtolower($type ?? '') === 'administrador' ? 'admin' : 'cliente');
        $provider = $request->input('provider') ?: 'local';

        if (!$nome || !$sobrenome || !$email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nome, sobrenome e email são obrigatórios.'
            ], 422);
        }

        $senhaFornecida = $request->input('senha') ?: $request->input('password');
        $senhaTemporaria = null;
        if (empty($senhaFornecida)) {
            $senhaTemporaria = Str::password(16, symbols: false);
            $senhaFinal = $senhaTemporaria;
        } else {
            $senhaFinal = $senhaFornecida;
        }

        $statusRaw = $request->input('status') ?: 'ativo';
        $status = strtolower((string) $statusRaw) === 'inativo' ? 'inativo' : 'ativo';

        $record = new Usuario([
            'nome' => $nome,
            'sobrenome' => $sobrenome,
            'cpf' => $cpf,
            'telefone' => $phone,
            'email' => $email,
            'data_criacao' => now(),
            'senha' => Hash::make($senhaFinal),
            'provider' => strtolower($provider),
        ]);

        $record->forceFill([
            'role' => $role,
            'status' => $status,
            'is_deleted' => 'N',
        ])->save();

        $response = [
            'status' => 'success',
            'message' => 'Usuário criado com sucesso!',
            'data' => UsuarioPresenter::listItem($record),
        ];

        if ($senhaTemporaria !== null) {
            $response['senha_temporaria'] = $senhaTemporaria;
        }

        return response()->json($response, 201);
    }

    public function show($id): JsonResponse
    {
        $record = Usuario::findByHashid($id, ['enderecos', 'carrinhos', 'pedidos']);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => UsuarioPresenter::detail($record)
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = Usuario::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        $nome = $request->input('nome') ?: $request->input('firstName') ?: $record->nome;
        $sobrenome = $request->input('sobrenome') ?: $request->input('lastName') ?: $record->sobrenome;
        $email = $request->input('email') ?: $record->email;
        $phone = $request->has('phone') ? $request->input('phone') : ($request->has('telefone') ? $request->input('telefone') : $record->telefone);

        if ($request->has('cpf')) {
            $novoCpf = (string) $request->input('cpf');
            // Ignora valor mascarado das listagens
            if (!str_contains($novoCpf, '*')) {
                $limpoNovo = Usuario::cleanCpf($novoCpf);
                $limpoAtual = Usuario::cleanCpf($record->cpf);

                if (!empty($limpoAtual) && $limpoAtual !== $limpoNovo) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'O CPF não pode ser alterado após o cadastro.',
                        'errors' => ['cpf' => ['O CPF não pode ser alterado após o cadastro.']]
                    ], 422);
                }

                if (empty($limpoAtual)) {
                    $record->cpf = $novoCpf;
                }
            }
        }

        $record->nome = $nome;
        $record->sobrenome = $sobrenome;
        $record->email = $email;
        $record->telefone = $phone;

        if ($request->filled('senha') || $request->filled('password')) {
            $record->senha = Hash::make($request->input('senha') ?: $request->input('password'));
        }

        $record->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Usuário atualizado com sucesso!',
            'data' => UsuarioPresenter::listItem($record)
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = Usuario::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $record->forceFill([
                'is_deleted' => 'S',
                'status' => 'inativo',
            ])->save();

            if ($record->relationLoaded('carrinhos') || method_exists($record, 'carrinhos')) {
                foreach ($record->carrinhos as $carrinho) {
                    if (Schema::hasTable('cart_items')) {
                        DB::table('cart_items')->where('cart_id', $carrinho->id)->delete();
                    } elseif (Schema::hasTable('itens_carrinho')) {
                        DB::table('itens_carrinho')->where('carrinho_id_carrinho', $carrinho->id_carrinho)->delete();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Usuário removido com sucesso!'
            ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao remover usuário.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
}
