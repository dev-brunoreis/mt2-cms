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
            $dsn .= ';dbname=' . self::quoteIdentifier($database);
        }

        $this->conn = new \PDO(
            $dsn,
            $env->get('DB_USER', 'root'),
            $env->get('DB_PASSWORD', 'admin123@'),
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    public static function quoteIdentifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Invalid identifier');
        }

        return $name;
    }

    public function useDatabase(string $database): static
    {
        $database = self::quoteIdentifier($database);
        $this->conn->exec("USE `{$database}`");

        return $this;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $position = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_int($value) => \PDO::PARAM_INT,
                is_bool($value) => \PDO::PARAM_BOOL,
                $value === null => \PDO::PARAM_NULL,
                default => \PDO::PARAM_STR,
            };

            $stmt->bindValue($position, $value, $type);
        }

        $stmt->execute();

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