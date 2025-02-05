<?php
namespace App\Models;
use App\Services\Database;

class File {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    public function getAllFiles() {
        $stmt = $this->pdo->query("SELECT id, name FROM files WHERE status = 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFileById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM files WHERE id = ? AND status = 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
