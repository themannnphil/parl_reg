<?php
// ParlReg — FormSchema Model
// Handles validation of field definitions and rendering metadata

class FormSchema {
    public const VALID_TYPES = [
        'text', 'email', 'phone', 'number', 'textarea',
        'select', 'radio', 'checkbox', 'date', 'file', 'header'
    ];

    /**
     * Validate a schema array before saving.
     * Returns array of errors, empty if valid.
     */
    public static function validate(array $schema): array {
        $errors = [];
        $ids    = [];

        foreach ($schema as $i => $field) {
            $prefix = "Field #" . ($i + 1);

            if (empty($field['id'])) {
                $errors[] = "$prefix: 'id' is required.";
            } elseif (in_array($field['id'], $ids, true)) {
                $errors[] = "$prefix: Duplicate id '{$field['id']}'.";
            } else {
                $ids[] = $field['id'];
            }

            if (empty($field['type']) || !in_array($field['type'], self::VALID_TYPES, true)) {
                $errors[] = "$prefix: Invalid or missing 'type'. Must be one of: " . implode(', ', self::VALID_TYPES);
            }

            if ($field['type'] !== 'header') {
                if (empty($field['label']['en'])) {
                    $errors[] = "$prefix: label.en is required.";
                }
            }

            if (in_array($field['type'], ['select', 'radio', 'checkbox'], true)) {
                if (empty($field['options']) || !is_array($field['options'])) {
                    $errors[] = "$prefix: 'options' array is required for {$field['type']}.";
                }
            }
        }

        return $errors;
    }

    /**
     * Get all required field IDs from a schema.
     */
    public static function getRequiredIds(array $schema): array {
        return array_column(
            array_filter($schema, fn($f) => !empty($f['required']) && $f['type'] !== 'header'),
            'id'
        );
    }

    /**
     * Get all file-upload field IDs from a schema.
     */
    public static function getFileFieldIds(array $schema): array {
        return array_column(
            array_filter($schema, fn($f) => $f['type'] === 'file'),
            'id'
        );
    }

    /**
     * Produce a flat label map: field_id → label_en for CSV export headers.
     */
    public static function labelMap(array $schema, string $lang = 'en'): array {
        $map = [];
        foreach ($schema as $field) {
            if ($field['type'] === 'header') continue;
            $map[$field['id']] = $field['label'][$lang] ?? $field['label']['en'] ?? $field['id'];
        }
        return $map;
    }

    /**
     * Sort schema by order field.
     */
    public static function sorted(array $schema): array {
        usort($schema, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return $schema;
    }
}
