<?php
// ParlReg — File Upload Handler

class FileHandler {
    public static function store(array $file, int $eventId, int $registrationId, string $fieldName): array {
        // Validate size
        $maxBytes = UPLOAD_MAX_MB * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException("File exceeds maximum size of " . UPLOAD_MAX_MB . "MB.");
        }

        // Validate MIME type (server-side, not extension)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES, true)) {
            throw new RuntimeException("File type '$mimeType' is not permitted.");
        }

        // Build secure storage path: /storage/uploads/{event_id}/{registration_id}/
        $dir = STORAGE_PATH . "/$eventId/$registrationId";
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            throw new RuntimeException("Could not create upload directory.");
        }

        // Randomised UUID filename
        $ext            = self::safeExtension($file['name']);
        $storedFilename = sprintf('%s.%s', self::uuid(), $ext);
        $fullPath       = "$dir/$storedFilename";

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            Logger::file("Failed to move upload: {$file['name']} → $fullPath");
            throw new RuntimeException("File could not be stored.");
        }

        Logger::file("Stored: event=$eventId reg=$registrationId field=$fieldName file=$storedFilename");

        return [
            'stored_filename'   => $storedFilename,
            'original_filename' => basename($file['name']),
            'mime_type'         => $mimeType,
            'filesize'          => $file['size'],
            'stored_path'       => "$eventId/$registrationId/$storedFilename",
        ];
    }

    public static function serve(int $fileId): never {
        Auth::requireAuth();

        $row = DB::row('SELECT * FROM uploaded_files WHERE id = ?', [$fileId]);
        if (!$row) {
            http_response_code(404);
            exit('File not found');
        }

        $fullPath = STORAGE_PATH . '/' . $row['stored_path'];
        if (!file_exists($fullPath)) {
            http_response_code(404);
            exit('File missing from disk');
        }

        Audit::log('file_download', 'uploaded_file', $fileId, $row['original_filename']);

        header('Content-Type: ' . $row['mime_type']);
        header('Content-Disposition: attachment; filename="' . addslashes($row['original_filename']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('X-Content-Type-Options: nosniff');
        readfile($fullPath);
        exit;
    }

    private static function safeExtension(string $filename): string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['pdf','jpg','jpeg','png','gif','doc','docx'];
        return in_array($ext, $allowed, true) ? $ext : 'bin';
    }

    private static function uuid(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }
}
