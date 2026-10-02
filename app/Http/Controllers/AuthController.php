<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Endereco;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Realiza o cadastro completo do usuário e de seu endereço.
     */
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

        // Sanitização de CPF e CEP
        $cpfLimpo = preg_replace('/\D/', '', $request->cpf);
        $cepLimpo = preg_replace('/\D/', '', $request->cep);

        // Verificação de duplicidade de e-mail
        if (Usuario::where('email', $request->email)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este e-mail já está cadastrado.',
                'errors' => ['email' => ['Este e-mail já está cadastrado no sistema.']]
            ], 422);
        }

        // Verificação de duplicidade de CPF (se informado)
        if (!empty($cpfLimpo) && Usuario::where('cpf', $cpfLimpo)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este CPF já está cadastrado.',
                'errors' => ['cpf' => ['Este CPF já está cadastrado no sistema.']]
            ], 422);
        }

        // Separação de nome e sobrenome
        $nomePartes = explode(' ', trim($request->nome), 2);
        $primeiroNome = $nomePartes[0];
        $sobrenome = isset($nomePartes[1]) ? trim($nomePartes[1]) : '';

        // Formatação da data de nascimento
        $nascimentoFormatado = null;
        try {
            if (str_contains($request->nascimento, '/')) {
                $nascimentoFormatado = Carbon::createFromFormat('d/m/Y', $request->nascimento)->format('Y-m-d H:i:s');
            } else {
                $nascimentoFormatado = Carbon::parse($request->nascimento)->format('Y-m-d H:i:s');
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

            $usuario = Usuario::create([
                'nome' => substr($primeiroNome, 0, 45),
                'sobrenome' => substr($sobrenome, 0, 45),
                'cpf' => substr($cpfLimpo, 0, 45),
                'data_nascimento' => $nascimentoFormatado,
                'email' => $request->email,
                'data_criacao' => now(),
                'senha' => Hash::make($request->senha),
                'google_id' => null,
                'provider' => 'local',
                'role' => 'cliente',
            ]);

            $endereco = Endereco::create([
                'cep' => substr($cepLimpo, 0, 8),
                'rua' => substr($request->logradouro, 0, 45),
                'bairro' => substr($request->bairro, 0, 45),
                'cidade' => substr($request->cidade, 0, 45),
                'estado' => substr($request->estado, 0, 45),
                'numero' => substr($request->numero, 0, 45),
                'complemento' => !empty($request->complemento) ? substr($request->complemento, 0, 45) : null,
                'padrao' => 1,
                'usuario_id' => $usuario->id,
            ]);

            DB::commit();

            $token = encrypt([
                'user_id' => $usuario->id,
                'email' => $usuario->email,
                'timestamp' => time()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Cadastro realizado com sucesso!',
                'data' => [
                    'usuario' => $usuario->load('enderecos'),
                    'token' => $token,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Falha ao realizar cadastro. Tente novamente.',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Realiza a autenticação (Login) do usuário.
     */
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

        if (!$usuario || !Hash::check($request->senha, $usuario->senha)) {
            return response()->json([
                'status' => 'error',
                'message' => 'E-mail ou senha incorretos.',
                'errors' => [
                    'senha' => ['E-mail ou senha incorretos.']
                ]
            ], 401);
        }

        $token = encrypt([
            'user_id' => $usuario->id,
            'email' => $usuario->email,
            'timestamp' => time()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Login realizado com sucesso!',
            'data' => [
                'usuario' => $usuario->load('enderecos'),
                'token' => $token,
            ]
        ], 200);
    }

    /**
     * Retorna os dados do usuário autenticado a partir do token de sessão.
     */
    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?: $request->header('Authorization') ?: $request->input('token');

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token não fornecido.'
            ], 401);
        }

        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        try {
            $payload = decrypt($token);

            if (!isset($payload['user_id'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token inválido.'
                ], 401);
            }

            $usuario = Usuario::with('enderecos')->find($payload['user_id']);

            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuário não encontrado.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'usuario' => $usuario
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sessão expirada ou inválida.'
            ], 401);
        }
    }

    /**
     * Encerra a sessão.
     */
    public function logout(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Desconectado com sucesso!'
        ], 200);
    }
}

