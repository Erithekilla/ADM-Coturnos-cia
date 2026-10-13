<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CloudinaryService
{
    public function upload(UploadedFile $file, string $folder = 'coturnos_produtos'): array
    {
        $cloudinaryUrl = env('CLOUDINARY_URL');
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $uploadPreset = env('CLOUDINARY_UPLOAD_PRESET');
        $apiKey = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        if ($cloudinaryUrl && preg_match('/cloudinary:\/\/([^:]+):([^@]+)@(.+)/', $cloudinaryUrl, $matches)) {
            $apiKey = $apiKey ?: $matches[1];
            $apiSecret = $apiSecret ?: $matches[2];
            $cloudName = $cloudName ?: $matches[3];
        }

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
            }
        }

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

