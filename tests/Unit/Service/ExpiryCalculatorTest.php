<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Tests\Unit\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCartSharing\Service\ExpiryCalculator;

final class ExpiryCalculatorTest extends TestCase
{
    private ExpiryCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ExpiryCalculator();
    }

    public function testExpiryIsTheGivenNumberOfDaysAhead(): void
    {
        $now = new DateTimeImmutable('2026-08-17 10:00:00');

        $expiresAt = $this->calculator->expiresAt($now, 90);

        self::assertNotNull($expiresAt);
        self::assertSame('2026-11-15 10:00:00', $expiresAt->format('Y-m-d H:i:s'));
    }

    public function testZeroDaysMeansNoExpiry(): void
    {
        self::assertNull($this->calculator->expiresAt(new DateTimeImmutable(), 0));
    }

    public function testNegativeDaysMeanNoExpiry(): void
    {
        // Kein Ablauf in der Vergangenheit: Ein Warenkorb, der beim Speichern schon abgelaufen
        // ist, wäre für den Kunden nicht von einem Fehler zu unterscheiden.
        self::assertNull($this->calculator->expiresAt(new DateTimeImmutable(), -5));
    }

    public function testAnEmptySettingFallsBackToTheDefault(): void
    {
        self::assertSame(ExpiryCalculator::DEFAULT_EXPIRY_DAYS, $this->calculator->readExpiryDays(null));
        self::assertSame(ExpiryCalculator::DEFAULT_EXPIRY_DAYS, $this->calculator->readExpiryDays(''));
        self::assertSame(ExpiryCalculator::DEFAULT_EXPIRY_DAYS, $this->calculator->readExpiryDays('unfug'));
    }

    public function testAConfiguredValueWins(): void
    {
        self::assertSame(30, $this->calculator->readExpiryDays(30));
        self::assertSame(30, $this->calculator->readExpiryDays('30'));
        self::assertSame(0, $this->calculator->readExpiryDays('0'), 'Die ausdrückliche Null bleibt Null.');
    }
}
