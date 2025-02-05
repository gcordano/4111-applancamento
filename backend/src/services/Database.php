<?php
namespace App\Services;
use PDO;
use PDOException;
use Dotenv\Dotenv;

class Database {
    private static $pdo;

    public static function connect() {
        if (!self::$pdo) {
            try {
                $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
                $dotenv->load();

                self::$pdo = new PDO(
                    "pgsql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};port={$_ENV['DB_PORT']}",
                    $_ENV['DB_USER'],
                    $_ENV['DB_PASSWORD'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                die(json_encode(["message" => "Erro ao conectar ao banco: " . $e->getMessage()]));
            }
        }
        return self::$pdo;
    }
}
