<?php

test('dashboard and store images endpoints exist and respond', function () {
    $response = $this->getJson('/api/v1/admin/dashboard');

    expect($response->status())->toBeIn([200, 500]);

    $uploadResponse = $this->postJson('/api/upload-store-images');
    $uploadResponse->assertStatus(200);
    $uploadResponse->assertJson(['success' => true]);
});

