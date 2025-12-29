<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost',       // Scribeやブラウザ用
        'http://localhost:80',  // ポート指定がある場合用
        'http://127.0.0.1',       // IP指定の場合用
        'http://127.0.0.1:80',
        'http://0.0.0.0',
        'http://0.0.0.0:80',
    ],
    'allowed_headers' => ['*'],
    'supports_credentials' => true,
];
