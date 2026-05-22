<?php
// ParlReg — UploadedFile Model

class UploadedFile {
    public static function findById(int $id): ?array {
        return DB::row('SELECT * FROM uploaded_files WHERE id = ?', [$id]);
    }

    public static function forRegistration(int $registrationId): array {
        return DB::all(
            'SELECT * FROM uploaded_files WHERE registration_id = ? ORDER BY uploaded_at ASC',
            [$registrationId]
        );
    }

    public static function create(int $registrationId, int $eventId, string $fieldName, array $stored): int {
        return DB::insert(
            'INSERT INTO uploaded_files
             (registration_id, event_id, field_name, stored_filename, original_filename, mime_type, filesize, stored_path)
             VALUES (?,?,?,?,?,?,?,?)',
            [$registrationId, $eventId, $fieldName,
             $stored['stored_filename'], $stored['original_filename'],
             $stored['mime_type'], $stored['filesize'], $stored['stored_path']]
        );
    }

    public static function deleteForRegistration(int $registrationId): void {
        $files = self::forRegistration($registrationId);
        foreach ($files as $f) {
            $path = STORAGE_PATH . '/' . $f['stored_path'];
            if (file_exists($path)) @unlink($path);
        }
        DB::run('DELETE FROM uploaded_files WHERE registration_id = ?', [$registrationId]);
    }
}
