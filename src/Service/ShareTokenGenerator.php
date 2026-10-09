<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

/**
 * Erzeugt die Kennung, unter der ein gespeicherter Warenkorb wiederzufinden ist.
 *
 * Sie steht in einer Adresse, die per E-Mail verschickt wird, und sie ist der einzige Schutz des
 * Inhalts: Wer sie hat, sieht den Warenkorb samt Kundeneingaben. Deshalb `random_int` und nicht
 * `uniqid` oder `mt_rand`. `uniqid` leitet sich aus der Uhrzeit ab, `mt_rand` lässt sich aus
 * wenigen beobachteten Werten zurückrechnen.
 *
 * Das Alphabet lässt Zeichen weg, die sich beim Vorlesen oder Abtippen verwechseln lassen
 * (0/O, 1/l/I). Eine Kennung wird auch am Telefon durchgegeben, und eine verwechselte endet nur
 * in der Meldung, dass der Verweis nicht gilt, ohne zu verraten, welches Zeichen falsch war.
 */
final class ShareTokenGenerator
{
    /**
     * 32 Zeichen aus 32 möglichen ergeben 160 Bit. Raten scheidet damit aus, auch bei einer
     * Million Versuche je Sekunde über Jahre. Die Spalte fasst 64 Zeichen.
     */
    public const TOKEN_LENGTH = 32;

    /**
     * Nur Kleinbuchstaben, also kein `O` und kein `I`. Dazu fehlen `i` und `l`, die sich mit `1`
     * verwechseln, sowie `0` und `1`. Es bleiben 24 Buchstaben und 8 Ziffern, genau 32 Zeichen,
     * also 5 Bit je Stelle.
     */
    private const ALPHABET = 'abcdefghjkmnopqrstuvwxyz23456789';

    public function generate(): string
    {
        // Der höchste gültige Index, nicht die Länge: random_int schließt die Obergrenze ein.
        $alphabetLength = strlen(self::ALPHABET) - 1;
        $token = '';

        for ($position = 0; $position < self::TOKEN_LENGTH; ++$position) {
            $token .= self::ALPHABET[random_int(0, $alphabetLength)];
        }

        return $token;
    }
}
