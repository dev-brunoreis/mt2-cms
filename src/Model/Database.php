<?php

namespace Mt2Cms\Model;

class Database
{
    protected \PDO $conn;

    public function __construct()
    {
        $env = Env::getInstance();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=utf8mb4',
            $env->get('DB_HOST', 'game'),
            $env->get('DB_PORT', '3306'),
        );

        $database = $env->get('DB_NAME');

        if ($database) {
            $dsn .= ';dbname=' . $database;
        }

        $this->conn = new \PDO(
            $dsn,
            $env->get('DB_USER', 'root'),
            $env->get('DB_PASSWORD', 'admin123@'),
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        );
    }

    public function useDatabase(string $database): static
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new \InvalidArgumentException('Invalid database name');
        }

        $this->conn->exec("USE `{$database}`");

        return $this;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [])
    {
        $value = $this->query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->conn->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->conn->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->conn->commit();
    }

    public function rollBack(): bool
    {
        return $this->conn->rollBack();
    }
}