<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use DateInterval;
use DateTimeImmutable;

/**
 * Rechnet aus der eingestellten Haltbarkeit das Ablaufdatum eines gespeicherten Warenkorbs.
 *
 * Eigene Klasse, weil sie die einzige Rechnung im Speicherweg ist und ohne Datenbank prüfbar sein
 * soll. Der Schreiber übergibt den Zeitpunkt, statt ihn hier holen zu lassen; sonst hinge jeder
 * Test an der Uhr des Rechners.
 */
final class ExpiryCalculator
{
    /**
     * Ohne Ablauf sammelt die Tabelle Warenkörbe, die niemand mehr kennt, und ein Verweis aus dem
     * vorletzten Jahr zeigt Preise, die niemand mehr erklärt. Neunzig Tage sind lang genug für
     * eine Anfrage, die über Wochen liegen bleibt, und kurz genug, dass der Bestand überschaubar
     * bleibt.
     *
     * Derselbe Wert steht als `defaultValue` in `config.xml`; beide müssen übereinstimmen, sonst
     * gilt je nach Weg eine andere Haltbarkeit.
     */
    public const DEFAULT_EXPIRY_DAYS = 90;

    /**
     * Null oder weniger Tage heißt kein Ablauf, die Rückgabe ist dann null. Das ist zulässig: Wer
     * 0 einstellt, will Verweise, die nicht verfallen, und nimmt den wachsenden Bestand in Kauf.
     * Die Aufräum-Aufgabe läuft weiter, lässt solche Warenkörbe aber stehen.
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
     * ergibt er keine Zahl, gilt die Vorgabe und nicht „kein Ablauf“. Eine leere Einstellung darf
     * nicht dazu führen, dass die Tabelle unbegrenzt wächst. Ein Text mit Nachkommastellen wird
     * abgeschnitten, „30.9“ ergibt 30.
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
