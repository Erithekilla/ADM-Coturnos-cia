<?php

namespace App\Http\Controllers;

use App\Http\Presenters\UserPresenter;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UserPrivilegeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function __construct(private UserPrivilegeService $privileges)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::with('addresses')->where(function ($q) {
            $q->whereNull('is_deleted')->orWhereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
        })->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");

                // Encrypted CPF: exact lookup via Blind Index (cpf_hash)
                if ($hash = User::generateCpfHash($search)) {
                    $q->orWhere('cpf_hash', $hash);
                }
            });
        }

        $users = $query->get()->map(fn (User $u) => UserPresenter::listItem($u));

        return response()->json([
            'status' => 'success',
            'data' => $users,
            'total' => $users->count()
        ], 200);
    }

    /** Full profile with decrypted sensitive data (access is audited). */
    public function show(Request $request, $id): JsonResponse
    {
        $user = User::findByHashid($id, ['addresses']);

        if (!$user) {
            return $this->notFound('User not found.');
        }

        AuditLogger::record('SENSITIVE_DATA_VIEW', 'users', $user->id, null, null,
            'Visualização de dados pessoais (CPF/telefone/nascimento) do usuário ' . $user->hashid);

        return response()->json([
            'status' => 'success',
            'data' => UserPresenter::detail($user),
        ], 200);
    }

    /** Search user by CPF using Blind Index. */
    public function searchByCpf(Request $request): JsonResponse
    {
        $request->validate(['cpf' => 'required|string']);

        $user = User::findByCpf($request->input('cpf'));

        if (!$user) {
            return $this->notFound('User not found.');
        }

        return $this->show($request, $user->hashid);
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $user = User::findByHashid($id);

        if (!$user) {
            return $this->notFound('User not found.');
        }

        $newStatus = strtolower((string) $user->status) === 'ativo' ? 'inativo' : 'ativo';
        $this->privileges->changeStatus($request->user(), $user, $newStatus);

        return response()->json([
            'status' => 'success',
            'message' => "User status updated to '{$newStatus}'.",
            'data' => UserPresenter::listItem($user->fresh('addresses'))
        ], 200);
    }

    public function toggleRole(Request $request, $id): JsonResponse
    {
        $user = User::findByHashid($id);

        if (!$user) {
            return $this->notFound('User not found.');
        }

        $newRole = $user->isAdmin() ? 'cliente' : 'admin';
        $this->privileges->changeRole($request->user(), $user, $newRole);

        return response()->json([
            'status' => 'success',
            'message' => "User role updated to '{$newRole}'.",
            'data' => UserPresenter::listItem($user->fresh('addresses'))
        ], 200);
    }
}
