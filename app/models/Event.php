<?php
// ParlReg — Event Model

class Event {
    public static function findById(int $id): ?array {
        return DB::row('SELECT * FROM events WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array {
        return DB::row("SELECT * FROM events WHERE slug = ? AND status = 'published'", [$slug]);
    }

    public static function exists(int $id): bool {
        return (bool) DB::row('SELECT id FROM events WHERE id = ?', [$id]);
    }

    public static function slugExists(string $slug, ?int $excludeId = null): bool {
        if ($excludeId) {
            return (bool) DB::row('SELECT id FROM events WHERE slug = ? AND id != ?', [$slug, $excludeId]);
        }
        return (bool) DB::row('SELECT id FROM events WHERE slug = ?', [$slug]);
    }

    public static function registrantCount(int $eventId): int {
        return (int) DB::row('SELECT COUNT(*) as cnt FROM registrations WHERE event_id = ?', [$eventId])['cnt'];
    }

    public static function isFull(int $eventId, int $capacity): bool {
        return self::registrantCount($eventId) >= $capacity;
    }

    public static function isDeadlinePassed(array $event): bool {
        return $event['registration_deadline']
            && strtotime($event['registration_deadline']) < time();
    }

    public static function setStatus(int $id, string $status): void {
        DB::run("UPDATE events SET status = ? WHERE id = ?", [$status, $id]);
    }

    public static function getWithStats(int $id): ?array {
        return DB::row(
            "SELECT e.*, u.fullname as created_by_name,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) as registrant_count,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'pending')   as pending_count,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'approved')  as approved_count,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'rejected')  as rejected_count
             FROM events e
             JOIN users u ON u.id = e.created_by
             WHERE e.id = ?", [$id]
        );
    }
}
