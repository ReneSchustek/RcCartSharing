<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Einstieg des Plugins: lädt die eigene Paket-Konfiguration und räumt beim Deinstallieren auf.
 *
 * Die Tabelle entsteht über die Migration. Beim Deinstallieren wird sie nur entfernt, wenn der
 * Betreiber die Daten ausdrücklich nicht behalten will. Gespeicherte Warenkörbe sind Arbeit von
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
     * das Speichern am laufenden Shop mit „Rate limiter factory not found" ab.
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

        // Die Verbindung des Kernels, nicht ein Dienst aus dem Container: In den Lebenszyklus-
        // Methoden ist der Container nicht immer vollständig aufgebaut.
        Kernel::getConnection()->executeStatement('DROP TABLE IF EXISTS `rc_shared_cart`');
    }
}
