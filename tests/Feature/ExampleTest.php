<?php

test('root redirects to admin', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});

test('admin returns successful react frontend response', function () {
    $response = $this->get('/admin');

    $response->assertStatus(200);
});

test('admin subroutes return react frontend response', function () {
    $response = $this->get('/admin/produtos');

    $response->assertStatus(200);
});
