<?php
// ParlReg — Database Connection (PDO singleton)

class DB {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                Logger::error('DB connection failed: ' . $e->getMessage());
                http_response_code(500);
                die(json_encode(['success' => false, 'error' => 'Database unavailable']));
            }
        }
        return self::$instance;
    }

    // Execute a prepared statement and return the statement object
    public static function run(string $sql, array $params = []): PDOStatement {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Fetch a single row
    public static function row(string $sql, array $params = []): ?array {
        $row = self::run($sql, $params)->fetch();
        return $row ?: null;
    }

    // Fetch all rows
    public static function all(string $sql, array $params = []): array {
        return self::run($sql, $params)->fetchAll();
    }

    // Insert and return last insert ID
    public static function insert(string $sql, array $params = []): int {
        self::run($sql, $params);
        return (int) self::get()->lastInsertId();
    }

    public static function beginTransaction(): void { self::get()->beginTransaction(); }
    public static function commit(): void           { self::get()->commit(); }
    public static function rollback(): void         { self::get()->rollBack(); }
}
