<?php
// ParlReg — User Model

class User {
    public static function findById(int $id): ?array {
        return DB::row('SELECT id, fullname, email, role, is_active, last_login, created_at FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array {
        return DB::row('SELECT * FROM users WHERE email = ? AND is_active = 1', [strtolower(trim($email))]);
    }

    public static function emailExists(string $email, ?int $excludeId = null): bool {
        if ($excludeId) {
            return (bool) DB::row('SELECT id FROM users WHERE email = ? AND id != ?', [strtolower(trim($email)), $excludeId]);
        }
        return (bool) DB::row('SELECT id FROM users WHERE email = ?', [strtolower(trim($email))]);
    }

    public static function create(string $fullname, string $email, string $passwordHash, string $role): int {
        return DB::insert(
            'INSERT INTO users (fullname, email, password_hash, role) VALUES (?,?,?,?)',
            [$fullname, strtolower(trim($email)), $passwordHash, $role]
        );
    }

    public static function updateLastLogin(int $id): void {
        DB::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$id]);
    }

    public static function setActive(int $id, bool $active): void {
        DB::run('UPDATE users SET is_active = ? WHERE id = ?', [(int)$active, $id]);
    }
}
