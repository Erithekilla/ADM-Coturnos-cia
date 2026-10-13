<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = Usuario::with(['enderecos', 'carrinhos', 'pedidos'])
            ->where(function ($q) {
                $q->whereNull('excluido')->orWhereRaw("TRIM(UPPER(COALESCE(excluido, 'N'))) != 'S'");
            })
            ->orderBy('id', 'desc')
            ->get();

        $data = $usuarios->map(function ($u) {
            $end = $u->enderecos->first();
            $isAtivo = strtolower((string)($u->status ?? 'ativo')) !== 'inativo';
            return [
                'id' => $u->id,
                'nome' => $u->nome,
                'sobrenome' => $u->sobrenome,
                'firstName' => $u->nome,
                'lastName' => $u->sobrenome,
                'email' => $u->email,
                'cpf' => $u->cpf ?? '',
                'phone' => '(11) 9' . str_pad((string)($u->id * 1111 % 10000), 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)($u->id * 2222 % 10000), 4, '0', STR_PAD_LEFT),
                'cep' => $end->cep ?? '01001-000',
                'sreet' => $end->rua ?? 'Rua Principal',
                'sreet_number' => $end->numero ?? '100',
                'block' => $end->bairro ?? 'Centro',
                'city' => $end->cidade ?? 'São Paulo',
                'state' => $end->estado ?? 'SP',
                'type' => strtolower($u->role) === 'admin' ? 'Administrador' : 'Cliente',
                'role' => $u->role,
                'provider' => ucfirst($u->provider ?? 'Local'),
                'status' => $isAtivo ? 'ativo' : 'inativo',
                'statusLabel' => $isAtivo ? 'Ativo' : 'Inativo',
                'createdAt' => $u->data_criacao ? date('d/m/Y', strtotime($u->data_criacao)) : date('d/m/Y'),
            ];
        });

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
        $type = $request->input('type');
        $role = $request->input('role') ?: (strtolower($type) === 'administrador' ? 'admin' : 'cliente');
        $provider = $request->input('provider') ?: 'local';
        $senha = $request->input('senha') ?: $request->input('password') ?: '12345678';

        if (!$nome || !$sobrenome || !$email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nome, sobrenome e email são obrigatórios.'
            ], 422);
        }

        $statusRaw = $request->input('status') ?: 'ativo';
        $status = strtolower((string)$statusRaw) === 'inativo' ? 'inativo' : 'ativo';

        $record = Usuario::create([
            'nome' => $nome,
            'sobrenome' => $sobrenome,
            'cpf' => $cpf,
            'email' => $email,
            'data_criacao' => now(),
            'senha' => Hash::make($senha),
            'provider' => strtolower($provider),
            'role' => $role,
            'status' => $status,
            'excluido' => 'N',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Usuário criado com sucesso!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = Usuario::with(['enderecos', 'carrinhos', 'pedidos'])->find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = Usuario::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        $nome = $request->input('nome') ?: $request->input('firstName') ?: $record->nome;
        $sobrenome = $request->input('sobrenome') ?: $request->input('lastName') ?: $record->sobrenome;
        $email = $request->input('email') ?: $record->email;
        $cpf = $request->has('cpf') ? $request->input('cpf') : $record->cpf;

        $role = $record->role;
        if ($request->has('role')) {
            $role = $request->input('role');
        } elseif ($request->has('type')) {
            $role = strtolower($request->input('type')) === 'administrador' ? 'admin' : 'cliente';
        }

        $provider = $request->input('provider') ?: $record->provider;

        $status = $record->status ?: 'ativo';
        if ($request->has('status')) {
            $status = strtolower((string)$request->input('status')) === 'inativo' ? 'inativo' : 'ativo';
        }

        $record->update([
            'nome' => $nome,
            'sobrenome' => $sobrenome,
            'email' => $email,
            'cpf' => $cpf,
            'role' => $role,
            'provider' => strtolower($provider),
            'status' => $status,
        ]);

        if ($request->filled('senha')) {
            $record->senha = Hash::make($request->input('senha'));
            $record->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Usuário atualizado com sucesso!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = Usuario::find($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            DB::table('usuario')->where('id', $id)->update([
                'excluido' => 'S',
                'status' => 'inativo',
            ]);

            foreach ($record->carrinhos as $carrinho) {
                DB::table('itens_carrinho')->where('carrinho_id_carrinho', $carrinho->id_carrinho)->delete();
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

