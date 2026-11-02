<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Endereco;
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
            'nome' => 'required|string|max:100',
            'cpf' => 'required|string',
            'email' => 'required|email|max:255',
            'nascimento' => 'required|string',
            'cep' => 'required|string',
            'logradouro' => 'required|string|max:45',
            'numero' => 'required|string|max:45',
            'complemento' => 'nullable|string|max:45',
            'bairro' => 'required|string|max:45',
            'cidade' => 'required|string|max:45',
            'estado' => 'required|string|max:45',
            'telefone' => 'nullable|string|max:20',
            'senha' => 'required|string|min:8',
        ], [
            'nome.required' => 'O nome completo é obrigatório.',
            'cpf.required' => 'O CPF é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'nascimento.required' => 'A data de nascimento é obrigatória.',
            'cep.required' => 'O CEP é obrigatório.',
            'logradouro.required' => 'O logradouro é obrigatório.',
            'numero.required' => 'O número é obrigatório.',
            'bairro.required' => 'O bairro é obrigatório.',
            'cidade.required' => 'A cidade é obrigatória.',
            'estado.required' => 'O estado é obrigatório.',
            'senha.required' => 'A senha é obrigatória.',
            'senha.min' => 'A senha deve conter no mínimo 8 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Por favor, corrija os erros nos campos informados.',
                'errors' => $validator->errors()
            ], 422);
        }

        $cpfLimpo = Usuario::cleanCpf($request->cpf);
        $cepLimpo = preg_replace('/\D/', '', $request->cep);

        if (!$cpfLimpo || strlen($cpfLimpo) !== 11) {
            return response()->json([
                'status' => 'error',
                'message' => 'CPF inválido.',
                'errors' => ['cpf' => ['Informe um CPF com 11 dígitos.']]
            ], 422);
        }

        if (Usuario::where('email', $request->email)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este e-mail já está cadastrado.',
                'errors' => ['email' => ['Este e-mail já está cadastrado no sistema.']]
            ], 422);
        }

        // Busca por Blind Index (cpf_hash), já que o CPF está cifrado no banco.
        if (Usuario::findByCpf($cpfLimpo)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este CPF já está cadastrado.',
                'errors' => ['cpf' => ['Este CPF já está cadastrado no sistema.']]
            ], 422);
        }

        $nomePartes = explode(' ', trim($request->nome), 2);
        $primeiroNome = $nomePartes[0];
        $sobrenome = isset($nomePartes[1]) ? trim($nomePartes[1]) : '';

        try {
            if (str_contains($request->nascimento, '/')) {
                $nascimentoFormatado = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d');
            } else {
                $nascimentoFormatado = Carbon::parse($request->nascimento)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data de nascimento inválida.',
                'errors' => ['nascimento' => ['Data de nascimento em formato inválido. Use DD/MM/AAAA.']]
            ], 422);
        }

        try {
            DB::beginTransaction();

            $usuario = new Usuario([
                'nome' => mb_substr($primeiroNome, 0, 45),
                'sobrenome' => mb_substr($sobrenome, 0, 45),
                'cpf' => $cpfLimpo,
                'telefone' => $request->telefone ? preg_replace('/\D/', '', $request->telefone) : null,
                'data_nascimento' => $nascimentoFormatado,
                'email' => $request->email,
                'data_criacao' => now(),
                'senha' => Hash::make($request->senha),
                'google_id' => null,
                'provider' => 'local',
            ]);
            // Papel e status NUNCA vêm do cliente.
            $usuario->forceFill(['role' => 'cliente', 'status' => 'ativo', 'excluido' => 'N'])->save();

            Endereco::create([
                'cep' => substr($cepLimpo, 0, 8),
                'rua' => mb_substr($request->logradouro, 0, 45),
                'bairro' => mb_substr($request->bairro, 0, 45),
                'cidade' => mb_substr($request->cidade, 0, 45),
                'estado' => mb_substr($request->estado, 0, 45),
                'numero' => mb_substr($request->numero, 0, 45),
                'complemento' => !empty($request->complemento) ? mb_substr($request->complemento, 0, 45) : null,
                'padrao' => 1,
                'usuario_id' => $usuario->id,
            ]);

            $token = $usuario->createToken('cliente', ['cliente'], $this->tokenExpiration())->plainTextToken;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cadastro realizado com sucesso!',
                'data' => [
                    'usuario' => $this->usuarioPayload($usuario->load('enderecos')),
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
            'senha' => 'required|string',
        ], [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'senha.required' => 'Informe sua senha.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dados de acesso inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        $usuario = Usuario::where('email', $request->email)->first();

        // Sempre executa Hash::check para não revelar, pelo tempo de resposta, se o e-mail existe.
        $hash = $usuario?->senha ?: '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $senhaOk = Hash::check($request->senha, $hash) && $usuario !== null;

        if (!$senhaOk) {
            AuditLogger::record('AUTH_LOGIN_FAILED', 'usuario', $usuario?->id ?? 0, null, ['email' => $request->email],
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
            AuditLogger::record('AUTH_LOGIN', 'usuario', $usuario->id, null, null,
                'Login administrativo: ' . AuditLogger::maskEmail((string) $usuario->email), $usuario);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Login realizado com sucesso!',
            'data' => [
                'usuario' => $this->usuarioPayload($usuario->load('enderecos')),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ]
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user()->load('enderecos');

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
            AuditLogger::record('AUTH_LOGOUT', 'usuario', $usuario->id, null, null, 'Logout administrativo', $usuario);
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

    private function usuarioPayload(Usuario $usuario): array
    {
        return [
            'id' => $usuario->hashid,
            'nome' => $usuario->nome,
            'sobrenome' => $usuario->sobrenome,
            'email' => $usuario->email,
            'role' => $usuario->role,
            'status' => $usuario->status ?: 'ativo',
            'provider' => $usuario->provider,
            'enderecos' => $usuario->relationLoaded('enderecos') ? $usuario->enderecos->toArray() : [],
        ];
    }
}
