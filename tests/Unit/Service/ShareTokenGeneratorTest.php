<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCartSharing\Service\ShareTokenGenerator;

final class ShareTokenGeneratorTest extends TestCase
{
    private ShareTokenGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ShareTokenGenerator();
    }

    public function testTokenHasTheDefinedLength(): void
    {
        self::assertSame(ShareTokenGenerator::TOKEN_LENGTH, strlen($this->generator->generate()));
    }

    public function testTokenUsesOnlyUnambiguousCharacters(): void
    {
        // Ziffern und Buchstaben, die sich beim Vorlesen verwechseln lassen, kommen nicht vor:
        // 0/O, 1/l/I. Eine verwechselte Kennung führt nicht zu einer Fehlermeldung, sondern ins
        // Leere — oder in einen fremden Warenkorb.
        self::assertMatchesRegularExpression('/^[a-hj-km-z2-9]+$/', $this->generator->generate());
    }

    public function testTwoTokensDiffer(): void
    {
        $tokens = [];
        for ($run = 0; $run < 50; ++$run) {
            $tokens[] = $this->generator->generate();
        }

        self::assertCount(50, array_unique($tokens), 'Zwei gleiche Kennungen wären ein Weg in einen fremden Warenkorb.');
    }
}
