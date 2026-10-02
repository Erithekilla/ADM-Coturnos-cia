<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CloudinaryService
{
    /**
     * Realiza o upload de imagem para o Cloudinary (pasta coturnos_produtos/)
     * com fallback inteligente para armazenamento local se credenciais não forem fornecidas.
     */
    public function upload(UploadedFile $file, string $folder = 'coturnos_produtos'): array
    {
        $cloudinaryUrl = env('CLOUDINARY_URL');
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $uploadPreset = env('CLOUDINARY_UPLOAD_PRESET');
        $apiKey = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        // Se houver CLOUDINARY_URL no formato cloudinary://api_key:api_secret@cloud_name
        if ($cloudinaryUrl && preg_match('/cloudinary:\/\/([^:]+):([^@]+)@(.+)/', $cloudinaryUrl, $matches)) {
            $apiKey = $apiKey ?: $matches[1];
            $apiSecret = $apiSecret ?: $matches[2];
            $cloudName = $cloudName ?: $matches[3];
        }

        // Se tivermos as credenciais do Cloudinary, faz o envio via API REST oficial
        if ($cloudName && ($uploadPreset || ($apiKey && $apiSecret))) {
            try {
                $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";
                
                $data = [
                    'folder' => $folder,
                ];

                if ($uploadPreset) {
                    $data['upload_preset'] = $uploadPreset;
                } elseif ($apiKey && $apiSecret) {
                    $timestamp = time();
                    $data['timestamp'] = $timestamp;
                    $data['api_key'] = $apiKey;
                    // Assinatura SHA1 conforme documentação da API do Cloudinary
                    $paramsToSign = "folder={$folder}&timestamp={$timestamp}{$apiSecret}";
                    $data['signature'] = sha1($paramsToSign);
                }

                $response = Http::attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )->post($endpoint, $data);

                if ($response->successful()) {
                    $res = $response->json();
                    return [
                        'success' => true,
                        'provider' => 'cloudinary',
                        'url' => $res['secure_url'] ?? $res['url'],
                        'public_id' => $res['public_id'],
                        'format' => $res['format'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                // Em caso de falha de conexão com a Cloudinary, faz fallback para local
            }
        }

        // Fallback local: Salva na pasta public/coturnos_produtos/ para não interromper a aplicação
        $targetDir = public_path($folder);
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $publicId = $folder . '/' . Str::random(16) . '_' . time();
        $filename = basename($publicId) . '.' . $extension;

        $file->move($targetDir, $filename);

        return [
            'success' => true,
            'provider' => 'local_fallback',
            'url' => '/' . $folder . '/' . $filename,
            'public_id' => $publicId,
            'format' => $extension,
            'notice' => 'Salvo localmente. Para envio direto ao Cloudinary, configure CLOUDINARY_URL ou CLOUDINARY_CLOUD_NAME no .env'
        ];
    }
}
