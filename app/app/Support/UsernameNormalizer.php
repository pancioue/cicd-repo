<?php

namespace App\Support;

final class UsernameNormalizer
{
    public function normalize(string $input): string
    {
        $trimmed = trim($input);
        $lowered = strtolower($trimmed);
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $lowered) ?? '';

        return trim($normalized, '-');
    }
}
