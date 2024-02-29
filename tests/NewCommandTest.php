<?php

namespace AHDCreative\AHDPressInstaller\Console\Tests;

use AHDCreative\AHDPressInstaller\Console\NewCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NewCommandTest extends TestCase
{
    protected string $testDirectory = 'test-output';

    protected function setUp(): void
    {
        parent::setUp();

        $testDirectoryName = __DIR__ . '/../' . $this->testDirectory;

        if (!file_exists($testDirectoryName)) {
            exec("mkdir" . __DIR__ . '/../' . $this->testDirectory);
        }
    }

    public function test_it_can_scaffold_a_new_ahdpress_theme()
    {
        $scaffoldDirectoryName = $this->testDirectory . '/just-ahdpress';
        $scaffoldDirectory = __DIR__ . '/../' . $scaffoldDirectoryName;

        if (file_exists($scaffoldDirectory)){
            if (PHP_OS_FAMILY == 'Windows') {
                exec("rd /s /q \"$scaffoldDirectory\"");
            } else {
                exec("rm -rf \"$scaffoldDirectory\"");
            }
        }

        $app = new Application('AHDPress Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $tester->execute(['folder' => $scaffoldDirectoryName, '--name' => 'Just AHDPress']);

        $this->assertDirectoryExists($scaffoldDirectory);
        $this->assertFileExists($scaffoldDirectory . '/functions.php');
        $this->assertStringContainsString('just_ahdpress', file_get_contents($scaffoldDirectory . '/functions.php'));
    }

    public function test_it_can_scaffold_a_new_ahdpress_theme_with_wordpress()
    {
        $scaffoldDirectoryName = $this->testDirectory . '/with-wordpress';
        $scaffoldDirectory = __DIR__ . '/../' . $scaffoldDirectoryName;

        if (file_exists($scaffoldDirectory)){
            if (PHP_OS_FAMILY == 'Windows') {
                exec("rd /s /q \"$scaffoldDirectory\"");
            } else {
                exec("rm -rf \"$scaffoldDirectory\"");
            }
        }

        $app = new Application('AHDPress Installer');
        $app->add(new NewCommand);

        $tester = new CommandTester($app->find('new'));

        $tester->execute(['folder' => $scaffoldDirectoryName, '--name' => 'Just AHDPress', '--wordpress' => true]);

        $this->assertDirectoryExists($scaffoldDirectory);
        $this->assertFileExists($scaffoldDirectory . '/wp-content/themes/with-wordpress/functions.php');
        $this->assertStringContainsString(
            'with_wordpress',
            file_get_contents($scaffoldDirectory . '/wp-content/themes/with-wordpress/functions.php')
        );
    }
}