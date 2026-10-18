<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCartSharing\Service\SharedCartManager;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Umbenennen und Löschen aus dem Kundenkonto. Die Kennung kommt aus einem Formular und lässt sich beliebig
 * verändern; ungeprüft bräche ein Wert, der keine Kennung ist, in `new Criteria([$id])` mit Fehler 500 ab.
 */
#[CoversClass(SharedCartManager::class)]
final class SharedCartManagerTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function manipulatedIds(): iterable
    {
        yield 'Text' => ['nicht-eine-kennung'];
        yield 'leer' => [''];
        yield 'zu kurz' => ['0191abcd'];
        yield 'Einschleusung' => ["0191' OR 1=1 --"];
    }

    /**
     * Was: eine manipulierte Kennung beim Umbenennen und Löschen.
     * Erwartet: „gehört nicht dem Kunden", keine Abfrage an die Datenbank, keine Ausnahme.
     */
    #[DataProvider('manipulatedIds')]
    public function testAManipulatedIdIsRejectedWithoutTouchingTheDatabase(string $id): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::never())->method('searchIds');
        $repository->expects(self::never())->method('update');
        $repository->expects(self::never())->method('delete');

        $manager = new SharedCartManager($repository);

        self::assertFalse($manager->rename($id, '0191aaaabbbbccccddddeeeeffff0001', 'Neu', Context::createCLIContext()));
        self::assertFalse($manager->delete($id, '0191aaaabbbbccccddddeeeeffff0001', Context::createCLIContext()));
    }
}
