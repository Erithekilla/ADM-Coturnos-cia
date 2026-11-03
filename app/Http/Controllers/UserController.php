<?php

namespace App\Http\Controllers;

use App\Http\Presenters\UserPresenter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $query = User::with('addresses');
        if (Schema::hasColumn('users', 'is_deleted')) {
            $query->where(function ($q) {
                $q->whereNull('is_deleted')->orWhereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
            });
        }
        $users = $query->orderBy('id', 'desc')->get();

        $data = $users->map(fn (User $u) => UserPresenter::listItem($u));

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function store(Request $request): JsonResponse
    {
        $firstName = $request->input('first_name') ?: $request->input('firstName') ?: $request->input('nome');
        $lastName = $request->input('last_name') ?: $request->input('lastName') ?: $request->input('sobrenome');
        $email = $request->input('email');
        $cpf = $request->input('cpf');
        $phone = $request->input('phone') ?: $request->input('telefone');
        $type = $request->input('type');
        $role = $request->input('role') ?: (strtolower($type ?? '') === 'administrador' ? 'admin' : 'cliente');
        $provider = $request->input('provider') ?: 'local';

        if (!$firstName || !$lastName || !$email) {
            return response()->json([
                'status' => 'error',
                'message' => 'First name, last name, and email are required.'
            ], 422);
        }

        $providedPassword = $request->input('password') ?: $request->input('senha');
        $temporaryPassword = null;
        if (empty($providedPassword)) {
            $temporaryPassword = Str::password(16, symbols: false);
            $finalPassword = $temporaryPassword;
        } else {
            $finalPassword = $providedPassword;
        }

        $statusRaw = $request->input('status') ?: 'ativo';
        $status = strtolower((string) $statusRaw) === 'inativo' ? 'inativo' : 'ativo';

        $record = new User([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'cpf' => $cpf,
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make($finalPassword),
            'provider' => strtolower($provider),
        ]);

        $forceFields = [
            'role' => (strtolower($role) === 'cliente' || strtolower($role) === 'customer') ? 'customer' : 'admin',
        ];
        if (Schema::hasColumn('users', 'active')) {
            $forceFields['active'] = ($status === 'ativo');
        }
        if (Schema::hasColumn('users', 'status')) {
            $forceFields['status'] = $status;
        }
        if (Schema::hasColumn('users', 'is_deleted')) {
            $forceFields['is_deleted'] = 'N';
        }
        $record->forceFill($forceFields)->save();

        $response = [
            'status' => 'success',
            'message' => 'User created successfully!',
            'data' => UserPresenter::listItem($record),
        ];

        if ($temporaryPassword !== null) {
            $response['senha_temporaria'] = $temporaryPassword;
            $response['temporary_password'] = $temporaryPassword;
        }

        return response()->json($response, 201);
    }

    public function show($id): JsonResponse
    {
        $record = User::findByHashid($id, ['addresses', 'carts', 'orders']);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => UserPresenter::detail($record)
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = User::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }

        $firstName = $request->input('first_name') ?: $request->input('firstName') ?: $request->input('nome') ?: $record->first_name;
        $lastName = $request->input('last_name') ?: $request->input('lastName') ?: $request->input('sobrenome') ?: $record->last_name;
        $email = $request->input('email') ?: $record->email;
        $phone = $request->has('phone') ? $request->input('phone') : ($request->has('telefone') ? $request->input('telefone') : $record->phone);

        if ($request->has('cpf')) {
            $novoCpf = (string) $request->input('cpf');
            // Ignora valor mascarado das listagens
            if (!str_contains($novoCpf, '*')) {
                $limpoNovo = User::cleanCpf($novoCpf);
                $limpoAtual = User::cleanCpf($record->cpf);

                if (!empty($limpoAtual) && $limpoAtual !== $limpoNovo) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'CPF cannot be changed after registration.',
                        'errors' => ['cpf' => ['CPF cannot be changed after registration.']]
                    ], 422);
                }

                if (empty($limpoAtual)) {
                    $record->cpf = $novoCpf;
                }
            }
        }

        $record->first_name = $firstName;
        $record->last_name = $lastName;
        $record->email = $email;
        $record->phone = $phone;

        if ($request->filled('password') || $request->filled('senha')) {
            $record->password = Hash::make($request->input('password') ?: $request->input('senha'));
        }

        $record->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully!',
            'data' => UserPresenter::listItem($record)
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = User::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $record->forceFill([
                'is_deleted' => 'S',
                'status' => 'inativo',
            ])->save();

            if ($record->relationLoaded('carts') || method_exists($record, 'carts')) {
                foreach ($record->carts as $cart) {
                    if (Schema::hasTable('cart_items')) {
                        DB::table('cart_items')->where('cart_id', $cart->id)->delete();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully!'
            ], 200)->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting user.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
}

