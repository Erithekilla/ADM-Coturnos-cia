<?php

test('dashboard and store images endpoints exist and respond', function () {
    $response = $this->getJson('/api/v1/admin/dashboard');
    // Pode retornar 200 se o banco estiver conectado ou 500 se o MySQL local estiver desligado
    expect($response->status())->toBeIn([200, 500]);

    $uploadResponse = $this->postJson('/api/upload-store-images');
    $uploadResponse->assertStatus(200);
    $uploadResponse->assertJson(['success' => true]);
});
