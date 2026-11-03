<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Address;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Autenticação stateless via Bearer Token (Laravel Sanctum).
 * O token é guardado no banco apenas como hash SHA-256; o texto puro é
 * devolvido ao cliente uma única vez, no login/cadastro.
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'nullable|string|max:100',
            'first_name' => 'nullable|string|max:100',
            'cpf' => 'required|string',
            'email' => 'required|email|max:255',
            'nascimento' => 'nullable|string',
            'birth_date' => 'nullable|string',
            'cep' => 'nullable|string',
            'zip_code' => 'nullable|string',
            'logradouro' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:45',
            'number' => 'nullable|string|max:45',
            'complemento' => 'nullable|string|max:60',
            'complement' => 'nullable|string|max:60',
            'bairro' => 'nullable|string|max:60',
            'neighborhood' => 'nullable|string|max:60',
            'cidade' => 'nullable|string|max:60',
            'city' => 'nullable|string|max:60',
            'estado' => 'nullable|string|max:45',
            'state' => 'nullable|string|max:45',
            'telefone' => 'nullable|string|max:30',
            'phone' => 'nullable|string|max:30',
            'senha' => 'nullable|string|min:8',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Por favor, corrija os erros nos campos informados.',
                'errors' => $validator->errors()
            ], 422);
        }

        $cpfLimpo = User::cleanCpf((string) $request->input('cpf'));
        $cepLimpo = preg_replace('/\D/', '', (string) ($request->input('zip_code') ?: $request->input('cep')));

        if (!$cpfLimpo || strlen($cpfLimpo) !== 11) {
            return response()->json([
                'status' => 'error',
                'message' => 'CPF inválido.',
                'errors' => ['cpf' => ['Informe um CPF com 11 dígitos.']]
            ], 422);
        }

        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este e-mail já está cadastrado.',
                'errors' => ['email' => ['Este e-mail já está cadastrado no sistema.']]
            ], 422);
        }

        // Busca por Blind Index (cpf_hash), já que o CPF está cifrado no banco.
        if (User::findByCpf($cpfLimpo)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este CPF já está cadastrado.',
                'errors' => ['cpf' => ['Este CPF já está cadastrado no sistema.']]
            ], 422);
        }

        $nomeCompleto = trim((string) ($request->input('first_name') ?: $request->input('nome') ?: 'Cliente'));
        $nomePartes = explode(' ', $nomeCompleto, 2);
        $primeiroNome = $nomePartes[0];
        $sobrenome = $request->input('last_name') ?: $request->input('sobrenome') ?: (isset($nomePartes[1]) ? trim($nomePartes[1]) : '');

        $nascInput = $request->input('birth_date') ?: $request->input('nascimento');
        $nascimentoFormatado = null;
        if ($nascInput) {
            try {
                if (str_contains($nascInput, '/')) {
                    $nascimentoFormatado = Carbon::createFromFormat('d/m/Y', $nascInput)->format('Y-m-d');
                } else {
                    $nascimentoFormatado = Carbon::parse($nascInput)->format('Y-m-d');
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data de nascimento inválida.',
                    'errors' => ['nascimento' => ['Data de nascimento em formato inválido. Use DD/MM/AAAA.']]
                ], 422);
            }
        }

        $senha = $request->input('password') ?: $request->input('senha') ?: 'senha-padrao-123';

        try {
            DB::beginTransaction();

            $usuario = new User([
                'first_name' => mb_substr($primeiroNome, 0, 45),
                'last_name' => mb_substr($sobrenome, 0, 45),
                'cpf' => $cpfLimpo,
                'phone' => ($request->input('phone') ?: $request->input('telefone')) ? preg_replace('/\D/', '', (string) ($request->input('phone') ?: $request->input('telefone'))) : null,
                'birth_date' => $nascimentoFormatado,
                'email' => $request->email,
                'password' => Hash::make($senha),
                'google_id' => null,
                'provider' => 'local',
            ]);
            // Papel e status NUNCA vêm do cliente.
            $usuario->forceFill(['role' => 'cliente', 'status' => 'ativo', 'is_deleted' => 'N'])->save();

            Address::create([
                'zip_code' => substr($cepLimpo, 0, 10),
                'street' => mb_substr((string) ($request->input('street') ?: $request->input('logradouro') ?: 'Rua'), 0, 100),
                'neighborhood' => mb_substr((string) ($request->input('neighborhood') ?: $request->input('bairro') ?: 'Bairro'), 0, 60),
                'city' => mb_substr((string) ($request->input('city') ?: $request->input('cidade') ?: 'Cidade'), 0, 60),
                'state' => mb_substr((string) ($request->input('state') ?: $request->input('estado') ?: 'UF'), 0, 45),
                'number' => mb_substr((string) ($request->input('number') ?: $request->input('numero') ?: 'S/N'), 0, 20),
                'complement' => mb_substr((string) ($request->input('complement') ?: $request->input('complemento') ?: ''), 0, 60) ?: null,
                'is_default' => 1,
                'user_id' => $usuario->id,
            ]);

            $token = $usuario->createToken('cliente', ['cliente'], $this->tokenExpiration())->plainTextToken;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cadastro realizado com sucesso!',
                'data' => [
                    'usuario' => $this->usuarioPayload($usuario->load('addresses')),
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Falha ao realizar cadastro. Tente novamente.', $e);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'senha' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $senhaInput = $request->input('password') ?: $request->input('senha');

        if ($validator->fails() || empty($senhaInput)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados de acesso inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        $usuario = User::where('email', $request->email)->first();

        // Sempre executa Hash::check para não revelar, pelo tempo de resposta, se o e-mail existe.
        $hash = $usuario?->password ?: '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $senhaOk = Hash::check($senhaInput, $hash) && $usuario !== null;

        if (!$senhaOk) {
            AuditLogger::record('AUTH_LOGIN_FAILED', 'users', $usuario?->id ?? 0, null, ['email' => $request->email],
                'Tentativa de login com credenciais inválidas: ' . AuditLogger::maskEmail((string) $request->email));

            return response()->json([
                'status' => 'error',
                'message' => 'E-mail ou senha incorretos.',
                'errors' => [
                    'senha' => ['E-mail ou senha incorretos.']
                ]
            ], 401);
        }

        if (!$usuario->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Conta suspensa, inativa ou desativada pelo sistema.'
            ], 403);
        }

        $abilities = $usuario->isAdmin() ? ['admin'] : ['cliente'];
        $token = $usuario->createToken($usuario->isAdmin() ? 'painel-admin' : 'cliente', $abilities, $this->tokenExpiration());

        if ($usuario->isAdmin()) {
            AuditLogger::record('AUTH_LOGIN', 'users', $usuario->id, null, null,
                'Login administrativo: ' . AuditLogger::maskEmail((string) $usuario->email), $usuario);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Login realizado com sucesso!',
            'data' => [
                'usuario' => $this->usuarioPayload($usuario->load('addresses')),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ]
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user()->load('addresses');

        return response()->json([
            'status' => 'success',
            'data' => [
                'usuario' => $this->usuarioPayload($usuario),
                'abilities' => $usuario->currentAccessToken()?->abilities ?? [],
            ]
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $token = $usuario->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        if ($usuario->isAdmin()) {
            AuditLogger::record('AUTH_LOGOUT', 'users', $usuario->id, null, null, 'Logout administrativo', $usuario);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Desconectado com sucesso!'
        ], 200);
    }

    private function tokenExpiration(): ?\DateTimeInterface
    {
        $minutes = (int) config('sanctum.expiration');

        return $minutes > 0 ? now()->addMinutes($minutes) : null;
    }

    private function usuarioPayload(User $usuario): array
    {
        $enderecos = $usuario->relationLoaded('addresses') ? $usuario->addresses->toArray() : ($usuario->relationLoaded('enderecos') ? $usuario->enderecos->toArray() : []);

        return [
            'id' => $usuario->hashid,
            'firstName' => $usuario->first_name,
            'lastName' => $usuario->last_name,
            'first_name' => $usuario->first_name,
            'last_name' => $usuario->last_name,
            'nome' => $usuario->first_name,
            'sobrenome' => $usuario->last_name,
            'email' => $usuario->email,
            'role' => $usuario->role,
            'status' => $usuario->status ?: 'ativo',
            'provider' => $usuario->provider,
            'enderecos' => $enderecos,
            'addresses' => $enderecos,
        ];
    }
}
