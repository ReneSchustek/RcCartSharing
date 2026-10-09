<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Plant das Löschen gespeicherter Warenkörbe, deren Ablaufdatum vorbei ist; gelöscht wird in
 * {@see ExpiredSharedCartCleanupTaskHandler}.
 *
 * Einmal am Tag genügt. Ein abgelaufener Warenkorb lässt sich schon vor dem Löschen nicht mehr
 * aufrufen, weil der SharedCartLoader das Datum selbst prüft. Die Aufgabe hält nur die Tabelle
 * klein, und dafür kommt es auf ein paar Stunden nicht an.
 */
final class ExpiredSharedCartCleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'rc_cart_sharing.cleanup_expired';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // 24 Stunden in Sekunden
    }
}
