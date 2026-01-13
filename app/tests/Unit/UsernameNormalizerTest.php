<?php

namespace Tests\Unit;

use App\Support\UsernameNormalizer;
use PHPUnit\Framework\TestCase;

final class UsernameNormalizerTest extends TestCase
{
    public function test_normalizes_spaces_and_case(): void
    {
        $normalizer = new UsernameNormalizer();

        $this->assertSame('patrick-lee', $normalizer->normalize('  Patrick   Lee  '));
    }

    public function test_strips_symbols_and_condenses_separators(): void
    {
        $normalizer = new UsernameNormalizer();

        $this->assertSame('user-007', $normalizer->normalize('**User__007!!'));
    }

    public function test_returns_empty_when_only_symbols(): void
    {
        $normalizer = new UsernameNormalizer();

        $this->assertSame('', $normalizer->normalize('$$$'));
    }
}
