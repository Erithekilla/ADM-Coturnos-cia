<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Address::with(['user'])->paginate(20);
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->decodeHashids($request, [
            'usuario_id' => User::class,
            'user_id' => User::class,
        ]);

        $zipCode = $request->input('zip_code') ?: $request->input('cep');
        $street = $request->input('street') ?: $request->input('rua');
        $neighborhood = $request->input('neighborhood') ?: $request->input('bairro');
        $city = $request->input('city') ?: $request->input('cidade');
        $state = $request->input('state') ?: $request->input('estado');
        $number = $request->input('number') ?: $request->input('numero');
        $complement = $request->input('complement') ?: $request->input('complemento');
        $isDefault = $request->input('is_default', $request->input('padrao', 0));
        $userId = $request->input('user_id') ?: $request->input('usuario_id');

        $request->merge([
            'zip_code' => $zipCode,
            'street' => $street,
            'neighborhood' => $neighborhood,
            'city' => $city,
            'state' => $state,
            'number' => $number,
            'complement' => $complement,
            'is_default' => $isDefault,
            'user_id' => $userId,
        ]);

        $validated = $request->validate([
            'zip_code' => 'required|string|max:20',
            'street' => 'required|string|max:100',
            'neighborhood' => 'required|string|max:60',
            'city' => 'required|string|max:60',
            'state' => 'required|string|max:45',
            'number' => 'required|string|max:20',
            'complement' => 'nullable|string|max:60',
            'is_default' => 'nullable|integer|in:0,1',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $record = Address::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Address created successfully!',
            'data' => $record
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $record = Address::findByHashid($id, ['user']);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $record
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $this->decodeHashids($request, [
            'usuario_id' => User::class,
            'user_id' => User::class,
        ]);

        $record = Address::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found.'
            ], 404);
        }

        $zipCode = $request->input('zip_code') ?: $request->input('cep') ?: $record->zip_code;
        $street = $request->input('street') ?: $request->input('rua') ?: $record->street;
        $neighborhood = $request->input('neighborhood') ?: $request->input('bairro') ?: $record->neighborhood;
        $city = $request->input('city') ?: $request->input('cidade') ?: $record->city;
        $state = $request->input('state') ?: $request->input('estado') ?: $record->state;
        $number = $request->input('number') ?: $request->input('numero') ?: $record->number;
        $complement = $request->has('complement') ? $request->input('complement') : ($request->has('complemento') ? $request->input('complemento') : $record->complement);
        $isDefault = $request->input('is_default', $request->input('padrao', $record->is_default));
        $userId = $request->input('user_id') ?: $request->input('usuario_id') ?: $record->user_id;

        $record->update([
            'zip_code' => $zipCode,
            'street' => $street,
            'neighborhood' => $neighborhood,
            'city' => $city,
            'state' => $state,
            'number' => $number,
            'complement' => $complement,
            'is_default' => $isDefault,
            'user_id' => $userId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Address updated successfully!',
            'data' => $record
        ], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = Address::findByHashid($id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Address not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Address removed successfully!'
        ], 200);
    }
}
