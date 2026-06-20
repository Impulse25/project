<?php

abstract class baseModel {
    protected PDO $pdo;
    protected string $table = '';


    //Конструктор при создании
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
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

    //Счетчик
    public function count(string $select = 'COUNT(*)', string $where = '', array $params = [], string $groupBy = ''): array
    {
        $sql = "SELECT {$select} FROM {$this->table} " . $where;

        if ($groupBy) {
            $sql .= " GROUP BY " . $groupBy;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}