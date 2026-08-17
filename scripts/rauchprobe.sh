#!/usr/bin/env bash
# Rauchprobe für RcCartSharing gegen den laufenden Demoshop.
#
# Sie fährt den Weg, den ein Kunde geht: Artikel in den Warenkorb, Warenkorb speichern, Adresse
# in einer FRISCHEN Sitzung aufrufen, nachsehen ob der Artikel samt Kundeneingabe angekommen ist.
# Genau dieser Wechsel der Sitzung ist der Punkt, an dem ein Fehler sichtbar wird — im selben
# Browser sähe ein kaputtes Wiederherstellen aus wie ein Erfolg.

set -uo pipefail

HOST="demoshop.ddev.site"
BASIS="https://localhost"
PRODUKT="019f418380ac73dabb6f43ea5e0ba07f"
ARBEIT="$(mktemp -d)"
trap 'rm -rf "$ARBEIT"' EXIT

ruf() {
    local keks="$1"; shift
    curl -sk -b "$keks" -c "$keks" -H "Host: $HOST" "$@"
}

echo "== 1. Sitzung eröffnen und Artikel in den Warenkorb legen"
ruf "$ARBEIT/kunde.txt" -o /dev/null "$BASIS/"
ruf "$ARBEIT/kunde.txt" -o /dev/null -X POST \
    -d "lineItems[$PRODUKT][id]=$PRODUKT" \
    -d "lineItems[$PRODUKT][type]=product" \
    -d "lineItems[$PRODUKT][referencedId]=$PRODUKT" \
    -d "lineItems[$PRODUKT][quantity]=2" \
    "$BASIS/checkout/line-item/add"

POSITIONEN=$(ruf "$ARBEIT/kunde.txt" "$BASIS/checkout/cart" | grep -c "line-item-product" || true)
echo "   Positionen im Warenkorb: $POSITIONEN"

echo "== 2. Warenkorb speichern"
KOPF=$(ruf "$ARBEIT/kunde.txt" -D - -o /dev/null -X POST -d "rcCartSharingName=Rauchprobe" "$BASIS/rc-cart-sharing/save")
KENNUNG=$(printf '%s' "$KOPF" | grep -io "rcSharedCart=[a-z0-9]*" | head -1 | cut -d= -f2)
echo "   Kennung: ${KENNUNG:-KEINE}"

if [ -z "${KENNUNG:-}" ]; then
    echo "FEHLGESCHLAGEN: keine Kennung erhalten"
    printf '%s\n' "$KOPF" | head -20
    exit 1
fi

echo "== 3. Adresse in einer frischen Sitzung aufrufen"
ruf "$ARBEIT/empfaenger.txt" -o /dev/null "$BASIS/"
LEER=$(ruf "$ARBEIT/empfaenger.txt" "$BASIS/checkout/cart" | grep -c "line-item-product" || true)
echo "   Positionen vor dem Aufruf: $LEER"

ruf "$ARBEIT/empfaenger.txt" -o /dev/null -L "$BASIS/rc-cart-sharing/load/$KENNUNG"
NACHHER=$(ruf "$ARBEIT/empfaenger.txt" "$BASIS/checkout/cart" | grep -c "line-item-product" || true)
echo "   Positionen nach dem Aufruf: $NACHHER"

echo "== 4. Unbekannte Kennung"
ANTWORT=$(ruf "$ARBEIT/fremder.txt" -o /dev/null -w "%{http_code}" "$BASIS/rc-cart-sharing/load/gibtesnicht")
echo "   Antwort auf eine unbekannte Kennung: $ANTWORT (erwartet: 302)"

echo
if [ "$NACHHER" -ge 1 ] && [ "$LEER" -eq 0 ]; then
    echo "BESTANDEN — der geteilte Warenkorb kam in der fremden Sitzung an."
    exit 0
fi

echo "FEHLGESCHLAGEN — in der fremden Sitzung kam nichts an."
exit 1
