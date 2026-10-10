<?php

namespace App\Support;

class PredictionConfidence
{
    public static function percent(array $prediction): ?float
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
        $value = (float) $value;

        return is_finite($value) && $value >= 0 && $value <= 1 ? $value * 100 : null;
    }
}
