<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Entfernt gespeicherte Warenkörbe, deren Ablaufdatum vorbei ist.
 *
 * Einmal am Tag genügt: Ein Warenkorb, der ein paar Stunden länger lebt als vorgesehen, schadet
 * niemandem — eine Tabelle, die jahrelang wächst, schon.
 */
final class ExpiredSharedCartCleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'rc_cart_sharing.cleanup_expired';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // täglich
    }
}
