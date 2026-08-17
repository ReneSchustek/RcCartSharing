<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Plugin-Bootstrapper für RcCartSharing.
 *
 * Die Tabelle entsteht über die Migration. Beim Deinstallieren wird sie nur entfernt, wenn der
 * Betreiber die Daten ausdrücklich nicht behalten will — gespeicherte Warenkörbe sind Arbeit von
 * Kunden, und eine Deinstallation zum Aktualisieren darf sie nicht mitnehmen.
 */
final class RcCartSharing extends Plugin
{
    /**
     * Zieht die eigene Paket-Konfiguration in den Container.
     *
     * `Bundle::build()` lädt sie nicht von selbst: `buildDefaultConfig()` ruft nur der Kern für
     * seine eigenen Bündel auf. Eine Datei unter `Resources/config/packages/` liegt sonst still
     * da und wirkt nicht. Hier hängt die Staffel der Begrenzung daran; ohne diesen Aufruf bricht
     * das Speichern mit „Rate limiter factory not found" ab — am laufenden Shop gesehen, nicht
     * vermutet.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->buildDefaultConfig($container);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $container = $this->container;
        if ($container === null) {
            return;
        }

        $connection = $container->get(Connection::class);
        if (!$connection instanceof Connection) {
            return;
        }

        $connection->executeStatement('DROP TABLE IF EXISTS `rc_shared_cart`');
    }
}
