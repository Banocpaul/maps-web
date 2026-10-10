<?php

namespace App\Support;

class PredictionConfidence
{
    public static function percent(array $prediction): ?string
    {
        $code = strtoupper((string) ($prediction['flood_code'] ?? ''));
        if (! in_array($code, ['A', 'B', 'C', 'D'], true)) {
            return null;
        }
        // API confidence values are fractions for the selected severity window.
        // Legacy risk probabilities combine C and D, so they cannot stand in for either code.
        $value = $prediction['flood_severity_confidence'] ?? $prediction['confidence']
            ?? data_get($prediction, 'flood_severity_probabilities.'.$code)
            ?? data_get($prediction, 'probabilities.'.$code);
        if (! is_numeric($value)) {
            return null;
        }
        $numeric = (float) $value;
        if (! is_finite($numeric) || $numeric < 0 || $numeric > 1) {
            return null;
        }
        if ($numeric == 0) {
            return '0';
        }

        // Shift the decimal point instead of rounding or multiplying a binary float.
        $decimal = is_string($value) ? trim($value) : json_encode($value);
        preg_match('/^\+?(\d*)(?:\.(\d*))?(?:e([+-]?\d+))?$/i', $decimal, $parts);
        $digits = $parts[1].($parts[2] ?? '');
        $point = strlen($parts[1]) + (int) ($parts[3] ?? 0) + 2;
        if ($point <= 0) {
            $digits = str_repeat('0', 1 - $point).$digits;
            $point = 1;
        }
        $digits = str_pad($digits, $point, '0');
        $whole = ltrim(substr($digits, 0, $point), '0') ?: '0';
        $fraction = rtrim(substr($digits, $point), '0');

        return $whole.($fraction !== '' ? '.'.$fraction : '');
    }
}
