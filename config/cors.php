<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // フロントエンドのURL
    'allowed_headers' => ['*'],
    'supports_credentials' => true,
];
