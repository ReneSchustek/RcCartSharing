<?php

declare(strict_types=1);

/*
 * Startet die Tests in zwei Umgebungen: allein im Plugin-Verzeichnis mit eigenem `vendor/` und
 * eingebaut unter `custom/plugins/` einer Shopware-Installation.
 *
 * Geladen werden erst der Autoloader des Plugins, dann der der Installation drei Ebenen über dem
 * Plugin. Der eigene PSR-4-Loader dahinter greift für die Klassen des Plugins und der Tests, wenn
 * keiner der beiden sie kennt.
 */

$pluginAutoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($pluginAutoloader)) {
    require_once $pluginAutoloader;
}

$shopwareAutoloader = dirname(__DIR__, 4) . '/vendor/autoload.php';
if (file_exists($shopwareAutoloader)) {
    require_once $shopwareAutoloader;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Ruhrcoder\\RcCartSharing\\Tests\\' => __DIR__ . '/',
        'Ruhrcoder\\RcCartSharing\\' => dirname(__DIR__) . '/src/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $length = strlen($prefix);
        if (strncmp($class, $prefix, $length) !== 0) {
            continue;
        }
        $file = $baseDir . str_replace('\\', '/', substr($class, $length)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/*
 * Den Kernel startet der Bootstrap nur, wenn das Plugin in einer Shopware-Installation liegt.
 * Erkennbar ist sie an `config/bundles.php`.
 *
 * `class_exists(TestBootstrapper::class)` allein taugt dafür nicht: `shopware/core` ist eine
 * `require`-Abhängigkeit, die Klasse gibt es also auch im eigenständigen Checkout. Der Bootstrap
 * versuchte dann einen Shop zu starten, den es nicht gibt, und bräche mit „Could not find plugin:
 * RcCartSharing" ab, bevor ein einziger Unit-Test läuft.
 *
 * Gesucht wird zuerst im Aufrufverzeichnis, denn Integrationstests werden aus dem
 * Wurzelverzeichnis der Installation gestartet (`vendor/bin/phpunit -c custom/plugins/…`).
 * Danach vier Ebenen über `tests/`, was bei `custom/plugins/<Plugin>/tests/` greift, solange der
 * Pfad kein Symlink ist.
 */
$shopwareRoot = null;
foreach ([getcwd(), \dirname(__DIR__, 4)] as $candidate) {
    // `getcwd()` kann false liefern, daher die Typprüfung. Einen leeren Text liefern weder
    // getcwd() noch dirname(), ein Vergleich darauf wäre überflüssig.
    if (\is_string($candidate) && is_file($candidate . '/config/bundles.php')) {
        $shopwareRoot = $candidate;
        break;
    }
}

// KernelTestBehaviour und IntegrationTestBehaviour erwarten, dass bootstrap() vor dem ersten Test
// `KernelLifecycleManager::prepare()` aufgerufen hat. Im eigenständigen Lauf entfällt der ganze
// Block; die Unit-Tests brauchen nur den Autoloader.
if ($shopwareRoot !== null && class_exists(\Shopware\Core\TestBootstrapper::class)) {
    // Eine `.env.test` kann `KERNEL_CLASS=App\Kernel` setzen, obwohl es in einer Installation, die
    // über `KernelFactory::create()` startet, keinen App-Kernel gibt. Ebenso kann ein Aufruf von
    // der Kommandozeile den Backslash verschlucken. Lässt sich der eingetragene Wert nicht laden,
    // gilt `Shopware\Core\Kernel`, die Vorgabe von `KernelFactory::$kernelClass`. Gesetzt wird vor
    // bootstrap(), weil Dotenv vorhandene Werte nicht überschreibt.
    $kernelClassFromEnv = getenv('KERNEL_CLASS');
    $currentKernelClass = $kernelClassFromEnv !== false && $kernelClassFromEnv !== ''
        ? $kernelClassFromEnv
        : ($_SERVER['KERNEL_CLASS'] ?? '');
    if ($currentKernelClass === '' || !class_exists($currentKernelClass)) {
        putenv('KERNEL_CLASS=Shopware\\Core\\Kernel');
        $_SERVER['KERNEL_CLASS'] = 'Shopware\\Core\\Kernel';
        $_ENV['KERNEL_CLASS'] = 'Shopware\\Core\\Kernel';
    }

    // addCallingPlugin() trägt RcCartSharing als aktives Plugin ein. Installiert wird es nur, wenn
    // die Testdatenbank neu aufgesetzt wird (keine Plugin-Tabelle oder FORCE_INSTALL).
    // setForceInstallPlugins(true) fehlt absichtlich: Es installiert bei jedem Lauf mit
    // `--reinstall`, also mit einer Deinstallation ohne „Daten behalten“, und die löscht die
    // Tabelle `rc_shared_cart` samt allen Testdaten.
    $bootstrapper = (new \Shopware\Core\TestBootstrapper())
        ->setPlatformEmbedded(false)
        ->addCallingPlugin();

    // `KernelFactory::getProjectDir()` liest `PROJECT_ROOT` aus `$_ENV` oder `$_SERVER`, bevor es
    // auf den Pfad der eigenen Klasse zurückfällt. Mit dem `vendor/` des Plugins zeigte dieser
    // Pfad in das Plugin statt in die Installation. setProjectDir() auf dem TestBootstrapper
    // allein erreicht die KernelFactory nicht, daher zusätzlich die Umgebungsvariable.
    $bootstrapper->setProjectDir($shopwareRoot);
    $_SERVER['PROJECT_ROOT'] = $shopwareRoot;
    $_ENV['PROJECT_ROOT'] = $shopwareRoot;
    putenv('PROJECT_ROOT=' . $shopwareRoot);

    $bootstrapper->bootstrap();
}
