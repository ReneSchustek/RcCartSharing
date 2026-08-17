<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

/**
 * Erzeugt die Kennung, unter der ein gespeicherter Warenkorb wiederzufinden ist.
 *
 * Sie steht in einer Adresse, die per E-Mail verschickt wird, und sie ist der einzige Schutz des
 * Inhalts: Wer sie hat, sieht den Warenkorb samt Kundeneingaben. Deshalb `random_int` und nicht
 * `uniqid` oder `mt_rand` — beide sind vorhersagbar, wenn man einen Wert kennt.
 *
 * Das Alphabet lässt Zeichen weg, die sich beim Vorlesen oder Abtippen verwechseln lassen
 * (0/O, 1/l/I). Eine Kennung wird am Telefon durchgegeben, und eine verwechselte führt nicht zu
 * einer Fehlermeldung, sondern in einen fremden Warenkorb oder ins Leere.
 */
final class ShareTokenGenerator
{
    /**
     * 32 Zeichen aus 32 möglichen ergeben 160 Bit. Raten scheidet damit aus, auch bei einer
     * Million Versuche je Sekunde über Jahre.
     */
    public const TOKEN_LENGTH = 32;

    /** Ohne `i` und `l` (verwechseln sich mit `1`), ohne `0` und `1` — bleiben genau 32 Zeichen. */
    private const ALPHABET = 'abcdefghjkmnopqrstuvwxyz23456789';

    public function generate(): string
    {
        $alphabetLength = strlen(self::ALPHABET) - 1;
        $token = '';

        for ($position = 0; $position < self::TOKEN_LENGTH; ++$position) {
            $token .= self::ALPHABET[random_int(0, $alphabetLength)];
        }

        return $token;
    }
}
