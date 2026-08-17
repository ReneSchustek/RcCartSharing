<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\ScheduledTask;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * Löscht abgelaufene Warenkörbe in Blöcken statt in einem Rutsch. Ein Shop, in dem die
 * Aufräum-Aufgabe monatelang stillstand, hätte sonst einen einzigen Lauf mit zehntausenden
 * Datensätzen — und der räumt entweder gar nicht auf oder sperrt die Tabelle so lange, dass
 * niemand mehr speichern kann.
 */
#[AsMessageHandler(handles: ExpiredSharedCartCleanupTask::class)]
final class ExpiredSharedCartCleanupTaskHandler extends ScheduledTaskHandler
{
    private const BATCH_SIZE = 500;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     * @param EntityRepository<SharedCartCollection> $sharedCartRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly EntityRepository $sharedCartRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $now = (new DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $criteria = new Criteria();
        // Ohne Ablaufdatum bedeutet „unbegrenzt gültig" — solche Datensätze dürfen der Aufräum-
        // Aufgabe nicht zum Opfer fallen. Ein RangeFilter allein ließe sie zwar aus, doch der
        // ausdrückliche Ausschluss hält fest, dass das gewollt ist.
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('expiresAt', null),
        ]));
        $criteria->addFilter(new RangeFilter('expiresAt', [RangeFilter::LT => $now]));
        $criteria->setLimit(self::BATCH_SIZE);

        $deleted = 0;

        do {
            $ids = $this->sharedCartRepository->searchIds($criteria, $context)->getIds();
            if ($ids === []) {
                break;
            }

            $this->sharedCartRepository->delete(
                array_map(static fn (mixed $id): array => ['id' => $id], $ids),
                $context
            );

            $deleted += count($ids);
        } while (count($ids) === self::BATCH_SIZE);

        if ($deleted > 0) {
            $this->logger->info('rc_cart_sharing.cleanup.expired', ['deleted' => $deleted]);
        }
    }
}
