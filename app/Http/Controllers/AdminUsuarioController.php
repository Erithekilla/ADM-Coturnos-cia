<?php

namespace App\Http\Controllers;

use App\Http\Presenters\UsuarioPresenter;
use App\Models\Usuario;
use App\Services\AuditLogger;
use App\Services\UserPrivilegeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminUsuarioController extends Controller
{
    public function __construct(private UserPrivilegeService $privileges)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Usuario::with('enderecos')->where(function ($q) {
            $q->whereNull('is_deleted')->orWhereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
        })->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");

                // CPF cifrado: busca exata pelo Blind Index.
                if ($hash = Usuario::generateCpfHash($search)) {
                    $q->orWhere('cpf_hash', $hash);
                }
            });
        }

        $usuarios = $query->get()->map(fn (Usuario $u) => UsuarioPresenter::listItem($u));

        return response()->json([
            'status' => 'success',
            'data' => $usuarios,
            'total' => $usuarios->count()
        ], 200);
    }

    /** Ficha completa com dados sensíveis descriptografados (acesso auditado). */
    public function show(Request $request, $id): JsonResponse
    {
        $usuario = Usuario::findByHashid($id, ['enderecos']);

        if (!$usuario) {
            return $this->notFound('Usuário não encontrado.');
        }

        AuditLogger::record('SENSITIVE_DATA_VIEW', 'users', $usuario->id, null, null,
            'Visualização de dados pessoais (CPF/telefone/nascimento) do usuário ' . $usuario->hashid);

        return response()->json([
            'status' => 'success',
            'data' => UsuarioPresenter::detail($usuario),
        ], 200);
    }

    /** Localiza cliente pelo CPF informado no filtro de busca do admin. */
    public function searchByCpf(Request $request): JsonResponse
    {
        $request->validate(['cpf' => 'required|string']);

        $usuario = Usuario::findByCpf($request->input('cpf'));

        if (!$usuario) {
            return $this->notFound('Cliente não encontrado.');
        }

        return $this->show($request, $usuario->hashid);
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $usuario = Usuario::findByHashid($id);

        if (!$usuario) {
            return $this->notFound('Usuário não encontrado.');
        }

        $novoStatus = strtolower((string) $usuario->status) === 'ativo' ? 'inativo' : 'ativo';
        $this->privileges->changeStatus($request->user(), $usuario, $novoStatus);

        return response()->json([
            'status' => 'success',
            'message' => "Status do usuário alterado para '{$novoStatus}'.",
            'data' => UsuarioPresenter::listItem($usuario->fresh('enderecos'))
        ], 200);
    }

    public function toggleRole(Request $request, $id): JsonResponse
    {
        $usuario = Usuario::findByHashid($id);

        if (!$usuario) {
            return $this->notFound('Usuário não encontrado.');
        }

        $novoRole = $usuario->isAdmin() ? 'cliente' : 'admin';
        $this->privileges->changeRole($request->user(), $usuario, $novoRole);

        return response()->json([
            'status' => 'success',
            'message' => "Perfil do usuário alterado para '{$novoRole}'.",
            'data' => UsuarioPresenter::listItem($usuario->fresh('enderecos'))
        ], 200);
    }
}
