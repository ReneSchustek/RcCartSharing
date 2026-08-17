<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use DateInterval;
use DateTimeImmutable;

/**
 * Rechnet aus der eingestellten Haltbarkeit das Ablaufdatum eines gespeicherten Warenkorbs.
 *
 * Eigene Klasse, weil sie die einzige Rechnung im Speicherweg ist und ohne Datenbank prüfbar sein
 * soll. Der Schreiber übergibt den Zeitpunkt, statt ihn selbst zu holen — sonst hinge jeder Test
 * an der Uhr des Rechners.
 */
final class ExpiryCalculator
{
    /**
     * Ohne Ablauf sammelt die Tabelle Warenkörbe, die niemand mehr kennt, und ein Verweis aus dem
     * vorletzten Jahr zeigt Preise, die niemand mehr erklärt. Die Vorgabe ist ein Kompromiss:
     * lang genug für eine Anfrage, die über Wochen liegen bleibt, kurz genug, dass der Bestand
     * überschaubar bleibt.
     */
    public const DEFAULT_EXPIRY_DAYS = 90;

    /**
     * Null bedeutet: kein Ablauf. Das ist ausdrücklich zulässig — ein Betreiber, der die Aufräum-
     * Aufgabe abschalten will, stellt 0 ein und weiß, was er tut.
     */
    public function expiresAt(DateTimeImmutable $now, int $expiryDays): ?DateTimeImmutable
    {
        if ($expiryDays <= 0) {
            return null;
        }

        return $now->add(new DateInterval('P' . $expiryDays . 'D'));
    }

    /**
     * Ein Wert aus der Einstellungsmaske kommt als String, als Zahl oder gar nicht. Fehlt er oder
     * ergibt er keine Zahl, gilt die Vorgabe — nicht „kein Ablauf". Eine leere Einstellung darf
     * nicht dazu führen, dass die Tabelle unbegrenzt wächst.
     */
    public function readExpiryDays(mixed $configuredValue): int
    {
        if (is_int($configuredValue)) {
            return $configuredValue;
        }

        if (is_string($configuredValue) && is_numeric($configuredValue)) {
            return (int) $configuredValue;
        }

        return self::DEFAULT_EXPIRY_DAYS;
    }
}
