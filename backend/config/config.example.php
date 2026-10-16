<?php
declare(strict_types=1);
// Copiar como config.local.php FUERA de public_html. Nunca versionar credenciales.
return [
    'environment' => 'production',
    'app_key' => 'REEMPLAZAR_POR_UNA_CLAVE_ALEATORIA_DE_64_CARACTERES',
    'storage_driver' => 'mysql', // json solo para desarrollo o demostración privada.
    'mysql' => [
        'host' => 'localhost', 'port' => 3306,
        'database' => 'USUARIO_altetour', 'username' => 'USUARIO_alte',
        'password' => 'REEMPLAZAR',
    ],
];
