<?php
declare(strict_types=1);

namespace Rappresentor\Core;

use PDO;
use PDOException;

/**
 * Gestione connessione Database con PDO (Singleton)
 * Versione SemVer: 0.1.0
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Restituisce l'istanza singleton di PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $configFile = __DIR__ . '/../config/config.php';
            if (!file_exists($configFile)) {
                throw new PDOException("File di configurazione non trovato. Esegui prima l'installazione.");
            }

            $config = require $configFile;
            $dbConfig = $config['db'] ?? [];

            $host = $dbConfig['host'] ?? 'localhost';
            $port = $dbConfig['port'] ?? 3306;
            $dbname = $dbConfig['name'] ?? '';
            $user = $dbConfig['user'] ?? '';
            $pass = $dbConfig['pass'] ?? '';
            $charset = $dbConfig['charset'] ?? 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $mysqlInitAttr = defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
                ? \Pdo\Mysql::ATTR_INIT_COMMAND
                : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? PDO::MYSQL_ATTR_INIT_COMMAND : 1002);

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
                $mysqlInitAttr               => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci, wait_timeout = 60"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                throw $e;
            }
        }

        return self::$instance;
    }

    /**
     * Chiude esplicitamente la connessione PDO al database
     */
    public static function closeConnection(): void
    {
        self::$instance = null;
    }

    /**
     * Verifica rapida se la connessione è configurata e funzionante
     */
    public static function testConnection(array $dbConfig): bool
    {
        $host = $dbConfig['host'] ?? 'localhost';
        $port = $dbConfig['port'] ?? 3306;
        $dbname = $dbConfig['name'] ?? '';
        $user = $dbConfig['user'] ?? '';
        $pass = $dbConfig['pass'] ?? '';
        $charset = $dbConfig['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
