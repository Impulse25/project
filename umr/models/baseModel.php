<?php

abstract class baseModel {
    protected $pdo;
    protected $table;


    //Конструктор при создании
    public function __construct() {
        $this->pdo = $this->getDbConnection();
    }

    //Подключение к базе данных
    protected function getDbConnection()
    {
        $dbPath = dirname(__DIR__, 2) . '/requests/config/db.php';

        if (!file_exists($dbPath)) {
            die("❌ Файл db.php не найден по пути:<br>" . $dbPath);
        }

        if (!isset($GLOBALS['pdo'])) {
            require_once $dbPath;
        }

        if (isset($GLOBALS['pdo'])) {
            return $GLOBALS['pdo'];
        }

        die("❌ Не удалось создать подключение к базе данных.");
    }

    //Получение одной строки по id
    public function find(int $id): ?array {

        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");

        $stmt->execute([$id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    //Получение всей таблицы
    public function getAll(string $where = '', array $params = []): array {

        $sql = "SELECT * FROM {$this->table} " . $where;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Создание записи
    public function create(array $data): int {

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int)$this->pdo->lastInsertId();
    }

    //Обновление записи
    public function update(int $id, array $data) : bool {

        $sets = implode(', ', array_map(fn($col) => "$col = ?", array_keys($data)));
        
        $sql = "UPDATE {$this->table} SET $sets WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        $values = array_values($data);
        $values[] = $id;

        return $stmt->execute($values);
    }

    //Удаление записи
    public function delete(int $id) : bool {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
}