<?php

namespace BrianHenryIE\Strauss\Tests\Integration;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Composer\ProjectComposerPackage;
use BrianHenryIE\Strauss\Helpers\FileSystem;
use BrianHenryIE\Strauss\Pipeline\Copier;
use BrianHenryIE\Strauss\Pipeline\FileCopyScanner;
use BrianHenryIE\Strauss\Pipeline\FileEnumerator;
use BrianHenryIE\Strauss\Tests\Integration\Util\IntegrationTestCase;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\NullLogger;
use stdClass;

/**
 * Class CopierTest
 * @package BrianHenryIE\Strauss
 * @coversNothing
 */
class CopierIntegrationTest extends IntegrationTestCase
{

    public function testsPrepareTarget()
    {

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/strauss",
  "require": {
    "league/container": "*"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "BrianHenryIE\\Strauss\\",
      "classmap_prefix": "BrianHenryIE_Strauss_",
      "delete_vendor_files": false
    }
  }
}
EOD;

        file_put_contents($this->testsWorkingDir . 'composer.json', $composerJsonString);

        chdir($this->testsWorkingDir);

        exec('composer install');

        $projectComposerPackage = new ProjectComposerPackage($this->testsWorkingDir . 'composer.json');

        $dependencies = array_map(function ($element) {
            $composerFile = $this->testsWorkingDir . 'vendor/' . $element . '/composer.json';
            return ComposerPackage::fromFile($composerFile);
        }, $projectComposerPackage->getRequiresNames());

        $targetDir = $this->testsWorkingDir . 'vendor-prefixed/';
        $vendorDir = $this->testsWorkingDir . 'vendor/';

        $config = $this->createStub(StraussConfig::class);
        $config->method('getVendorDirectory')->willReturn($vendorDir);
        $config->method('getTargetDirectory')->willReturn($targetDir);

        $fileEnumerator = new FileEnumerator(
            $config,
            new Filesystem(
                new \League\Flysystem\Filesystem(
                    new LocalFilesystemAdapter('/')
                ),
                $this->testsWorkingDir
            )
        );
        $files = $fileEnumerator->compileFileListForDependencies($dependencies);

        $fileCopyScanner = new FileCopyScanner($config, new Filesystem(new \League\Flysystem\Filesystem(new LocalFilesystemAdapter('/')), $this->testsWorkingDir));
        $fileCopyScanner->scanFiles($files);

        $copier = new Copier($files, $config, new Filesystem(new \League\Flysystem\Filesystem(new LocalFilesystemAdapter('/')), $this->testsWorkingDir), new NullLogger());

        $file = 'ContainerAwareTrait.php';
        $relativePath = 'league/container/src/';
        $targetPath = $targetDir . $relativePath;
        $targetFile = $targetPath . $file;

        mkdir(rtrim($targetPath, '\\/'), 0777, true);

        file_put_contents($targetFile, 'dummy file');

        assert(file_exists($targetFile));

        $copier->prepareTarget();

        self::assertFileDoesNotExist($targetFile);
    }

    public function testsCopy()
    {

        $composerJsonString = <<<'EOD'
{
  "name": "brianhenryie/copierintegrationtest",
  "require": {
    "google/apiclient": "*"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "BrianHenryIE\\Strauss\\",
      "classmap_prefix": "BrianHenryIE_Strauss_",
      "delete_vendor_files": false
    }
  }
}
EOD;

        file_put_contents($this->testsWorkingDir . 'composer.json', $composerJsonString);

        chdir($this->testsWorkingDir);

        exec('composer install');

        $projectComposerPackage = new ProjectComposerPackage($this->testsWorkingDir . 'composer.json');

        $dependencies = array_map(function ($element) {
            $composerFile = $this->testsWorkingDir . 'vendor/' . $element . '/composer.json';
            return ComposerPackage::fromFile($composerFile);
        }, $projectComposerPackage->getRequiresNames());

        $targetDir = $this->testsWorkingDir . 'vendor-prefixed/';
        $vendorDir = $this->testsWorkingDir . 'vendor/';

        $config = $this->createStub(StraussConfig::class);
        $config->method('getVendorDirectory')->willReturn($vendorDir);
        $config->method('getTargetDirectory')->willReturn($targetDir);

        $fileEnumerator = new FileEnumerator(
            $config,
            new Filesystem(
                new \League\Flysystem\Filesystem(
                    new LocalFilesystemAdapter('/')
                ),
                $this->testsWorkingDir
            )
        );
        $files = $fileEnumerator->compileFileListForDependencies($dependencies);

        (new FileCopyScanner($config, new Filesystem(new \League\Flysystem\Filesystem(new LocalFilesystemAdapter('/')), $this->testsWorkingDir)))->scanFiles($files);

        $copier = new Copier($files, $config, new Filesystem(new \League\Flysystem\Filesystem(new LocalFilesystemAdapter('/')), $this->testsWorkingDir), new NullLogger());

        $file = 'Client.php';
        $relativePath = 'google/apiclient/src/';
        $targetPath = $targetDir . $relativePath;
        $targetFile = $targetPath . $file;

        $copier->prepareTarget();

        $copier->copy();

        self::assertFileExists($targetFile);
    }




    /**
     * Set up a common settings object.
     * @see MoverTest.php
     */
    protected function createComposer(): void
    {
//        parent::setUp();

        $this->testsWorkingDir = __DIR__ . '/temptestdir/';
        if (!file_exists($this->testsWorkingDir)) {
            mkdir($this->testsWorkingDir);
        }

        $mozartConfig = new stdClass();
        $mozartConfig->dep_directory = "/dep_directory/";
        $mozartConfig->classmap_directory = "/classmap_directory/";
        $mozartConfig->packages = array(
            "pimple/pimple",
            "ezyang/htmlpurifier"
        );

        $pimpleAutoload = new stdClass();
        $pimpleAutoload->{'psr-0'} = new stdClass();
        $pimpleAutoload->{'psr-0'}->Pimple = "src/";

        $htmlpurifierAutoload = new stdClass();
        $htmlpurifierAutoload->classmap = new stdClass();
        $htmlpurifierAutoload->classmap->Pimple = "library/";

        $mozartConfig->override_autoload = array();
        $mozartConfig->override_autoload["pimple/pimple"] = $pimpleAutoload;
        $mozartConfig->override_autoload["ezyang/htmlpurifier"] = $htmlpurifierAutoload;

        $composer = new stdClass();
        $composer->extra = new stdClass();
        $composer->extra->mozart = $mozartConfig;

        $composerFilepath = $this->testsWorkingDir . 'composer.json';
        $composerJson = json_encode($composer) ;
        file_put_contents($composerFilepath, $composerJson);

        $this->config = StraussConfig::loadFromFile($composerFilepath);
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` are absent, create them.
     * @see MoverTest.php
     * @test
     */
    public function it_creates_absent_dirs(): void
    {
        $this->markTestIncomplete();

        $mover = new Mover($this->testsWorkingDir, $this->config);

        // Make sure the directories don't exist.
        assert(! file_exists($this->testsWorkingDir . $this->config->gett()), "{$this->testsWorkingDir}{$this->config->getDepDirectory()} already exists");
        assert(! file_exists($this->testsWorkingDir . $this->config->getClassmapDirectory()));

        $packages = array();

        $mover->deleteTargetDirs($packages);

        self::assertTrue(file_exists($this->testsWorkingDir
            . $this->config->getDepDirectory()));
        self::assertTrue(file_exists($this->testsWorkingDir
            . $this->config->getClassmapDirectory()));
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` already exists with contents, it is not an issue.
     *
     * @see MoverTest.php
     *
     * @test
     */
    public function it_is_unpertrubed_by_existing_dirs(): void
    {
        $this->markTestIncomplete();

        $mover = new Mover($this->testsWorkingDir, $this->config);

        if (!file_exists($this->testsWorkingDir . $this->config->getDepDirectory())) {
            mkdir($this->testsWorkingDir . $this->config->getDepDirectory());
        }
        if (!file_exists($this->testsWorkingDir . $this->config->getClassmapDirectory())) {
            mkdir($this->testsWorkingDir . $this->config->getClassmapDirectory());
        }

        self::assertDirectoryExists($this->testsWorkingDir . $this->config->getDepDirectory());
        self::assertDirectoryExists($this->testsWorkingDir . $this->config->getClassmapDirectory());

        $packages = array();

        ob_start();

        $mover->deleteTargetDirs($packages);

        $output = ob_get_clean();

        self::assertEmpty($output);
    }

    /**
     * If the specified `dep_directory` or `classmap_directory` contains a subdir we are going to need when moving,
     * delete the subdir. aka:  If subfolders exist for dependencies we are about to manage, delete those subfolders.
     *
     * @see MoverTest.php
     *
     * @test
     */
    public function it_deletes_subdirs_for_packages_about_to_be_moved(): void
    {
        $this->markTestIncomplete();

        $mover = new Mover($this->testsWorkingDir, $this->config);

        @mkdir($this->testsWorkingDir . $this->config->getDepDirectory());
        @mkdir($this->testsWorkingDir . $this->config->getClassmapDirectory());

        @mkdir($this->testsWorkingDir . $this->config->getDepDirectory() . 'Pimple');
        @mkdir($this->testsWorkingDir . $this->config->getClassmapDirectory() . 'ezyang');

        $packages = array();
        foreach ($this->config->getPackages() as $packageString) {
            $testDummyComposerDir = $this->testsWorkingDir  . 'vendor/' . $packageString;
            @mkdir($testDummyComposerDir, 0777, true);
            $testDummyComposerPath = $testDummyComposerDir . '/composer.json';
            $testDummyComposerContents = json_encode(new stdClass());

            file_put_contents($testDummyComposerPath, $testDummyComposerContents);
            $parsedPackage = new ComposerPackageConfig($testDummyComposerDir, $this->config->getOverrideAutoload()[$packageString]);
            $parsedPackage->findAutoloaders();
            $packages[] = $parsedPackage;
        }

        $mover->deleteTargetDirs($packages);

        self::assertDirectoryDoesNotExist($this->testsWorkingDir . $this->config->getDepDirectory() . 'Pimple');
        self::assertDirectoryDoesNotExist($this->testsWorkingDir . $this->config->getDepDirectory() . 'ezyang');
    }
}
