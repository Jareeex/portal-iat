<?php

test('the application health endpoint responds successfully', function () {
    $response = $this->get('/up');

    $response->assertOk();
});
