<?php
// ParlReg — Registration Model

class Registration {
    public static function findById(int $id): ?array {
        return DB::row('SELECT * FROM registrations WHERE id = ?', [$id]);
    }

    public static function findByReference(string $ref): ?array {
        return DB::row('SELECT * FROM registrations WHERE reference_no = ?', [$ref]);
    }

    public static function getFiles(int $registrationId): array {
        return DB::all(
            'SELECT id, field_name, original_filename, mime_type, filesize, uploaded_at
             FROM uploaded_files WHERE registration_id = ?
             ORDER BY uploaded_at ASC',
            [$registrationId]
        );
    }

    public static function generateReference(): string {
        do {
            $ref = 'PARL-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
        } while (DB::row('SELECT id FROM registrations WHERE reference_no = ?', [$ref]));
        return $ref;
    }

    public static function setStatus(int $id, string $status): void {
        DB::run("UPDATE registrations SET status = ? WHERE id = ?", [$status, $id]);
    }

    public static function getCountByStatus(int $eventId): array {
        $rows = DB::all(
            'SELECT status, COUNT(*) as cnt FROM registrations WHERE event_id = ? GROUP BY status',
            [$eventId]
        );
        $result = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $result[$row['status']] = (int)$row['cnt'];
            $result['total'] += (int)$row['cnt'];
        }
        return $result;
    }
}
