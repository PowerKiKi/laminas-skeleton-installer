<?php

/**
 * @see       https://github.com/laminas/laminas-skeleton-installer for the canonical source repository
 * @copyright https://github.com/laminas/laminas-skeleton-installer/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-skeleton-installer/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\SkeletonInstaller;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Factory;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Laminas\SkeletonInstaller\Uninstaller;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use ReflectionProperty;

class UninstallerTest extends TestCase
{
    public function setUp()
    {
        $this->io = $this->setUpIo();
        $this->composer = $this->setUpComposerAndDependencies();
    }

    protected function setUpIo()
    {
        $io = $this->prophesize(IOInterface::class);
        $io->write('<info>Removing laminas/laminas-skeleton-installer...</info>')->shouldBeCalled();
        $io->write('<info>    Package not installed; nothing to do.</info>')->shouldNotBeCalled();
        $io->write('<info>    Removed plugin laminas/laminas-skeleton-installer.</info>')->shouldBeCalled();
        $io->write('<info>    Removing from composer.json</info>')->shouldBeCalled();
        $io->write('<info>    Complete!</info>')->shouldBeCalled();
        return $io;
    }

    protected function setUpComposerAndDependencies()
    {
        $composer = $this->prophesize(Composer::class);

        $package = $this->prophesize(PackageInterface::class);
        $repository = $this->prophesize(RepositoryInterface::class);
        $repository->findPackage(Uninstaller::PLUGIN_NAME, '*')->willReturn($package->reveal());

        $repoManager = $this->prophesize(RepositoryManager::class);
        $repoManager->getLocalRepository()->willReturn($repository->reveal());

        $composer->getRepositoryManager()->willReturn($repoManager->reveal());

        $installationManager = $this->prophesize(InstallationManager::class);
        $installationManager->uninstall($repository->reveal(), Argument::that(function ($arg) use ($package) {
            if (! $arg instanceof UninstallOperation) {
                return false;
            }

            return $package->reveal() === $arg->getPackage();
        }))->shouldBeCalled();

        $composer->getInstallationManager()->willReturn($installationManager->reveal());

        return $composer;
    }

    protected function setUpComposerJson(Uninstaller $uninstaller)
    {
        $project = vfsStream::setup('project');
        vfsStream::newFile('composer.json')
            ->at($project)
            ->setContent($this->createComposerJson());

        $r = new ReflectionProperty($uninstaller, 'composerFileFactory');
        $r->setAccessible(true);
        $r->setValue($uninstaller, function () {
            return vfsStream::url('project/composer.json');
        });
    }

    protected function createComposerJson()
    {
        return json_encode([
            'name' => 'test/project',
            'type' => 'project',
            'description' => 'This is a test project',
            'require' => [
                'laminas/laminas-skeleton-installer' => '^1.0.0-dev@dev',
            ],
        ]);
    }

    public function testRemovesPluginInstallation()
    {
        $uninstaller = new Uninstaller($this->composer->reveal(), $this->io->reveal());
        $this->setUpComposerJson($uninstaller);

        $uninstaller();

        $composer = json_decode(file_get_contents(vfsStream::url('project/composer.json')), true);
        $this->assertFalse(isset($composer['require']['laminas-skeleton-installer']));
    }
}
