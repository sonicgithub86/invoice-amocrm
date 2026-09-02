<?php

declare(strict_types=1);

namespace InvoiceService\AmoCRM;

final class AmoCustomFieldValue
{
    /** @param array<string, mixed> $entity */
    public static function first(array $entity, int $fieldId): string
    {
        $fields = $entity['custom_fields_values'] ?? [];
        if (!is_array($fields)) {
            return '';
        }

        foreach ($fields as $field) {
            if (!is_array($field) || ($field['field_id'] ?? null) !== $fieldId || !isset($field['values']) || !is_array($field['values'])) {
                continue;
            }
            foreach ($field['values'] as $value) {
                if (is_array($value) && isset($value['value']) && (is_string($value['value']) || is_int($value['value']) || is_float($value['value']) || is_bool($value['value']))) {
                    return trim((string) $value['value']);
                }
            }
        }

        return '';
    }

    /** @param array<string, mixed> $entity */
    public static function isChecked(array $entity, int $fieldId): bool
    {
        return in_array(mb_strtolower(self::first($entity, $fieldId)), ['1', 'true', 'yes', 'да', 'y'], true);
    }
}
