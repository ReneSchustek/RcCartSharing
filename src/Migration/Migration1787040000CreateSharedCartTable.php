<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Der gespeicherte Warenkorb: ein Abzug seiner Positionen unter einer Kennung.
 *
 * Die Positionen stehen als JSON in einer Spalte und nicht in einer eigenen Tabelle. Sie werden
 * ausschließlich als Ganzes gelesen und als Ganzes geschrieben; eine Kindtabelle brächte drei
 * weitere Dateien, einen Verbund je Lesevorgang und keinen einzigen Anwendungsfall. Was in der
 * Nutzlast steht, ist ohnehin je Erweiterung verschieden — eine Spaltenstruktur dafür gäbe es gar
 * nicht.
 *
 * Der Kundenbezug darf leer sein: Ein geteilter Warenkorb ohne Konto ist der Regelfall. Ist er
 * gesetzt, verschwindet der Datensatz mit dem Konto, weil er dann einer Person zugeordnet ist.
 * Mit dem Verkaufskanal verschwindet er ebenso, denn ohne Kanal lässt er sich nicht mehr aufrufen.
 *
 * Die eindeutige Kennung trägt jeden Aufruf eines Verweises, der Index auf `expires_at` die
 * tägliche Aufräum-Aufgabe, der auf `customer_id` die Liste im Kundenkonto.
 *
 * Eine ausgelieferte Migration bleibt unverändert; Änderungen am Schema kommen als neue Migration.
 */
final class Migration1787040000CreateSharedCartTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787040000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `rc_shared_cart` (
                `id` BINARY(16) NOT NULL,
                `token` VARCHAR(64) NOT NULL,
                `name` VARCHAR(255) NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NULL,
                `line_items` JSON NOT NULL,
                `expires_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `uniq.rc_shared_cart.token` UNIQUE (`token`),
                KEY `idx.rc_shared_cart.customer_id` (`customer_id`),
                KEY `idx.rc_shared_cart.expires_at` (`expires_at`),
                CONSTRAINT `fk.rc_shared_cart.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.rc_shared_cart.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `json.rc_shared_cart.line_items` CHECK (JSON_VALID(`line_items`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Die Tabelle entfernt nur die Deinstallation ohne „Daten behalten“, siehe RcCartSharing::uninstall().
    }
}
