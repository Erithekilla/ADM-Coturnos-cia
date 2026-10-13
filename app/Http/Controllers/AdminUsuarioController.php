<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminUsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Usuario::where(function ($q) {
            $q->whereNull('excluido')->orWhereRaw("TRIM(UPPER(COALESCE(excluido, 'N'))) != 'S'");
        })->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('sobrenome', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $usuarios = $query->get()->makeHidden(['senha']);

        return response()->json([
            'status' => 'success',
            'data' => $usuarios,
            'total' => $usuarios->count()
        ], 200);
    }

    public function toggleStatus($id): JsonResponse
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        $statusAtual = strtolower((string)$usuario->status);
        $novoStatus = ($statusAtual === 'ativo') ? 'inativo' : 'ativo';

        $usuario->status = $novoStatus;
        $usuario->save();

        return response()->json([
            'status' => 'success',
            'message' => "Status do usuário #{$usuario->id} alterado para '{$novoStatus}'.",
            'data' => $usuario->makeHidden(['senha'])
        ], 200);
    }

    public function toggleRole($id): JsonResponse
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário não encontrado.'
            ], 404);
        }

        $roleAtual = strtolower((string)$usuario->role);
        $novoRole = ($roleAtual === 'admin') ? 'cliente' : 'admin';

        $usuario->role = $novoRole;
        $usuario->save();

        return response()->json([
            'status' => 'success',
            'message' => "Perfil do usuário #{$usuario->id} alterado para '{$novoRole}'.",
            'data' => $usuario->makeHidden(['senha'])
        ], 200);
    }
}

