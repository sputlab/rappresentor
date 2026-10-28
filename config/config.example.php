<?php
/**
 * Rappresentor - File di configurazione di esempio
 * Rinomina questo file in config.php o esegui install.php
 * Versione SemVer: 0.1.0
 */

return [
    'app' => [
        'name' => 'Rappresentor',
        'url'  => 'https://tuodominio.it/rappresentor',
        'version' => '0.1.0',
        'env'  => 'production' // 'development' o 'production'
    ],
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'rappresentor_db',
        'user'    => 'rappresentor_user',
        'pass'    => 'la_tua_password_sicura',
        'charset' => 'utf8mb4'
    ]
];
