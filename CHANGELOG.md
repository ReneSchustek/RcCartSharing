# Changelog

## [0.2.1] - 2026-10-09 — Sicherer Versand, keine Fehlerseiten mehr

> **Deployment:** `php bin/console plugin:update RcCartSharing`, `php bin/console cache:clear`.

### Behoben

- **Der Name des Warenkorbs kann nichts mehr in die Mail einschleusen.** Er stand bisher im Text der
  Mail-Vorlage, die Shopware als Vorlage ausführt; ein Name mit `{{ … }}` oder HTML landete
  ausgeführt beziehungsweise ungefiltert in der Nachricht an den Empfänger. Name und Adresse kommen
  jetzt als Werte dazu, der HTML-Teil wird dabei maskiert.
- **Ein Warenkorb mit Gutschein lässt sich wieder aufrufen.** Der Gutscheincode geriet in die Suche
  nach Artikeln, der Aufruf endete mit einer Fehlerseite.
- **Eine ungültige Kennung im Kundenkonto führt nicht mehr zu einer Fehlerseite.** Umbenennen und
  Löschen behandeln sie wie einen fremden Warenkorb.
- **Ein geteilter Warenkorb gilt nur im Verkaufskanal, in dem er gespeichert wurde.**
- **Der Kasten mit der Adresse erscheint nur für den eigenen, gerade gespeicherten Warenkorb.** Bisher
  zeigte er jede Zeichenfolge aus der Adresse als „Ihr gespeicherter Warenkorb“ an.
- **Ließ sich gar nichts übernehmen, nennt die Meldung jetzt auch die fehlenden Artikelnummern.**

### Intern

- Kommentare im ganzen Bestand überarbeitet, Bezeichner durchgehend englisch.
- Deinstallation über die Datenbankverbindung des Kernels statt über den Container; Zeitangaben in UTC.
- Zwei Ausnahmen in `phpstan.neon` entfernt, die unter 6.7.15 nichts mehr trafen.

## [0.2.0] - 2026-08-17 — Kundenkonto und Versand per E-Mail

### Neu

- **Gespeicherte Warenkörbe im Kundenkonto.** Der angemeldete Kunde findet seine Warenkörbe unter
  „Gespeicherte Warenkörbe" wieder, kann sie umbenennen und löschen. Ein fremder Warenkorb verhält
  sich dabei wie ein nicht vorhandener — die Antwort verrät nicht, dass es ihn gibt.
- **Versand der Adresse per E-Mail.** Verschickt wird eine feste Vorlage mit dem Namen des
  Warenkorbs und der Adresse. Es gibt kein Feld für einen eigenen Text: Ein Formular, das
  beliebigen Text an beliebige Adressen schickt, wäre ein Spam-Verteiler mit der Absenderadresse
  des Betriebs. Drei Nachrichten in zehn Minuten je Absender, und die Antwort ist immer dieselbe —
  ob eine Adresse existiert, geht den Absender nichts an.

### Bekannte Grenze dieser Fassung

Der Text der Nachricht steht in den Textbausteinen, nicht als Mail-Vorlage im Verwaltungsbereich.
Wer ihn ändern will, ändert den Textbaustein. Eine im Backoffice bearbeitbare Vorlage wäre ein
eigener Schritt.

## [0.1.0] - 2026-08-17 — Warenkorb speichern und über eine Adresse teilen

### Neu

- **Speichern.** Unter dem Warenkorb steht ein Feld für einen Namen und die Schaltfläche
  „Warenkorb speichern und teilen". Danach erscheint über dem Warenkorb die Adresse zum Kopieren.
- **Aufrufen.** Wer die Adresse öffnet, bekommt dieselben Artikel in seinen eigenen Warenkorb —
  mit Meterlängen, Farben und Kundeneingaben. Die Preise werden dabei neu berechnet; im
  gespeicherten Warenkorb steht gar keiner.
- **Was fehlt, wird benannt.** Artikel, die es nicht mehr gibt oder die nicht mehr bestellbar
  sind, erscheinen als Hinweis mit Artikelnummer statt stillschweigend zu verschwinden.
- **Haltbarkeit und Aufräumen.** Ein gespeicherter Warenkorb verfällt nach 90 Tagen (einstellbar);
  eine tägliche Aufgabe räumt die abgelaufenen ab.
- **Begrenzung.** Zehn Speichervorgänge je Minute und Absender — ohne sie füllt eine Schleife die
  Tabelle, und aufgeräumt wird nur einmal am Tag.

### Grenzen dieser Fassung — beide mit 0.2.0 aufgehoben

- Kein Kundenkonto: Ein gespeicherter Warenkorb war über seine Adresse erreichbar, aber nicht in
  einer Liste im Konto.
- Kein Versand per E-Mail: Die Adresse wurde kopiert, nicht verschickt.
