<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testRequiredRuntimeIsAvailable(): void
    {
        self::assertGreaterThanOrEqual(80401, PHP_VERSION_ID);

        foreach (['pdo_mysql', 'dom', 'filter', 'json', 'libxml', 'mbstring', 'xmlwriter'] as $extension) {
            self::assertTrue(extension_loaded($extension), "Missing PHP extension: {$extension}");
        }

        self::assertContains('mysql', PDO::getAvailableDrivers());
    }

    public function testApplicationAndBuiltAssetsArePackaged(): void
    {
        $root = dirname(__DIR__);
        foreach (['index.php', 'dashboard.php', 'includes/config.php', 'css/main.min.css', 'js/main.min.js'] as $file) {
            self::assertFileIsReadable($root . '/' . $file);
            self::assertGreaterThan(0, filesize($root . '/' . $file), "Empty application file: {$file}");
        }
    }
}
