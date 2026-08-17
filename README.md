# RcCartSharing — Warenkorb speichern und teilen

Der Kunde speichert seinen Warenkorb und bekommt eine Adresse, unter der er ihn wiederfindet. Wer
die Adresse aufruft, bekommt dieselben Artikel in seinen eigenen Warenkorb gelegt — mit
Meterlängen, Farben und Kundeneingaben, aber mit heutigen Preisen.

## Warum es diese Erweiterung gibt

Es gibt fertige Erweiterungen für geteilte Warenkörbe. Sie speichern Artikelnummer und Menge und
bauen daraus einen neuen Warenkorb. Für einen Shop, in dem an jeder Position eine Zuschnittlänge,
eine Farbe oder eine Kundeneingabe hängt, ist das zu wenig: Der wiederhergestellte Warenkorb sieht
aus wie der ursprüngliche und ist ein anderer — und das fällt erst in der Bestellung auf.

Diese Erweiterung nimmt deshalb die **vollständige Nutzlast** jeder Position mit und entfernt nur,
was Shopware beim Wiederherstellen ohnehin neu bestimmt. Was eine künftige Erweiterung an eine
Position schreibt, überlebt damit, ohne dass hier eine Zeile geändert werden muss.

## Was der Kunde sieht

Unter dem Warenkorb steht ein Feld für einen Namen und die Schaltfläche „Warenkorb speichern und
teilen". Nach dem Speichern erscheint über dem Warenkorb ein Kasten mit der Adresse zum Kopieren.

Wer eine solche Adresse aufruft, bekommt die Artikel in seinen Warenkorb. Fehlt einer — nicht mehr
im Sortiment, abgeschaltet, nicht mehr bestellbar —, nennt ein Hinweis die betroffenen
Artikelnummern. Ein Warenkorb, der stumm um zwei Positionen kürzer zurückkommt, wäre der
schlechteste Ausgang.

Die Adresse lässt sich auch verschicken. Verschickt wird eine feste Vorlage: der Name des
Warenkorbs und die Adresse, sonst nichts. Ein Feld für einen eigenen Text gibt es bewusst nicht —
ein Formular, das beliebigen Text an beliebige Adressen schickt, ist ein Spam-Verteiler mit der
Absenderadresse des Betriebs. Drei Nachrichten in zehn Minuten je Absender, und die Antwort lautet
immer gleich: ob eine Adresse existiert, geht den Absender nichts an.

**Im Kundenkonto** stehen die eigenen gespeicherten Warenkörbe unter „Gespeicherte Warenkörbe" —
mit Adresse zum Kopieren, zum Umbenennen und zum Löschen. Ein Warenkorb, der ohne Anmeldung
gespeichert wurde, gehört niemandem und taucht dort nicht auf; er verfällt über sein Ablaufdatum.

## Einstellungen

| Einstellung | Vorgabe | Wirkung |
|---|---|---|
| Haltbarkeit in Tagen | 90 | Nach dieser Zeit wird der gespeicherte Warenkorb gelöscht, der Verweis führt ins Leere. `0` bedeutet: kein Ablauf |

## Betrieb

Die tägliche Aufgabe `rc_cart_sharing.cleanup_expired` entfernt abgelaufene Warenkörbe in Blöcken
von 500. Sie braucht einen laufenden Scheduler; ohne ihn wächst die Tabelle `rc_shared_cart`.

Beim Deinstallieren bleiben die Daten erhalten, solange der Betreiber sie behalten will — erst
„Daten löschen" entfernt die Tabelle.

## Datenschutz

Ein geteilter Warenkorb ohne angemeldeten Kunden trägt **keinen Personenbezug**: keine Kennung,
keine Adresse, kein Name eines Menschen. Speichert ein angemeldeter Kunde, wird sein Konto
vermerkt; der Datensatz verschwindet dann mit dem Konto.

## Entwicklung

```bash
composer install
composer quality     # Stil, statische Prüfung, Tests
```

Die Tests laufen ohne Shopware-Installation: Was am Wiederherstellen schiefgehen kann, steckt in
zwei Klassen ohne Datenbankzugriff (`CartSnapshotBuilder`, `RestoreRequestBuilder`), und genau die
sind vollständig geprüft.
