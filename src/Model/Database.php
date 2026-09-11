<?php

declare(strict_types=1);

namespace Mt2Cms\Model;

class Database
{
    protected ?\PDO $conn = null;
    protected ?string $currentDatabase = null;

    /** @var array{host: string, port: string, user: string, password: string, database?: string|null, requirePassword?: bool} */
    private array $config;

    /**
     * @param array{host?: string, port?: string|int, user?: string, password?: string|null, database?: string|null, requirePassword?: bool} $config
     */
    public function __construct(array $config = [])
    {
        $env = Env::getInstance();

        $this->config = [
            'host' => (string) ($config['host'] ?? $env->get('DB_HOST', 'game')),
            'port' => (string) ($config['port'] ?? $env->get('DB_PORT', '3306')),
            'user' => (string) ($config['user'] ?? $env->get('DB_USER', 'root')),
            'password' => (string) ($config['password'] ?? $env->get('DB_PASSWORD', '')),
            'database' => $config['database'] ?? $env->get('DB_NAME'),
            'requirePassword' => $config['requirePassword'] ?? true,
        ];
    }

    public static function forCms(): self
    {
        $env = Env::getInstance();

        return new self([
            'host' => (string) ($env->get('CMS_DB_HOST', 'mysql') ?: 'mysql'),
            'port' => (string) ($env->get('CMS_DB_PORT', $env->get('DB_PORT', '3306')) ?: '3306'),
            'user' => (string) ($env->get('CMS_DB_USER', $env->get('DB_USER', 'root')) ?: 'root'),
            'password' => $env->get('CMS_DB_PASSWORD', $env->get('DB_PASSWORD')),
            'database' => (string) ($env->get('CMS_DB_NAME', 'cms') ?: 'cms'),
        ]);
    }

    /**
     * @param array{host: string, port: string|int, user: string, password: string, database?: string|null} $config
     */
    public static function testConnection(array $config): bool
    {
        try {
            $db = new self([
                'host' => $config['host'],
                'port' => (string) $config['port'],
                'user' => $config['user'],
                'password' => $config['password'],
                'database' => $config['database'] ?? null,
                'requirePassword' => false,
            ]);
            $db->connect();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{host: string, port: string|int, user: string, password: string} $config
     */
    public static function testGameSchema(array $config): bool
    {
        try {
            $db = new self([
                'host' => $config['host'],
                'port' => (string) $config['port'],
                'user' => $config['user'],
                'password' => $config['password'],
                'requirePassword' => false,
            ]);
            $db->fetchColumn('SELECT 1 FROM `account`.`account` LIMIT 1');
            $db->fetchColumn('SELECT 1 FROM `player`.`player` LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function connect(): void
    {
        if ($this->conn !== null) {
            return;
        }

        if (($this->config['requirePassword'] ?? true) && $this->config['password'] === '') {
            throw new \RuntimeException('DB_PASSWORD is required. Copy .env-example to .env and set it.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=utf8mb4',
            $this->config['host'],
            $this->config['port'],
        );

        $database = $this->config['database'];

        if (is_string($database) && $database !== '') {
            $this->currentDatabase = self::quoteIdentifier($database);
            $dsn .= ';dbname=' . $this->currentDatabase;
        }

        $this->conn = new \PDO(
            $dsn,
            $this->config['user'],
            $this->config['password'],
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
        $this->connect();
        $database = self::quoteIdentifier($database);

        if ($this->currentDatabase === $database) {
            return $this;
        }

        $this->conn->exec("USE `{$database}`");
        $this->currentDatabase = $database;

        return $this;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $this->connect();
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
