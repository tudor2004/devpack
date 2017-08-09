<?php

namespace Tudorica\Devpack;

use Composer\Composer;
use Composer\EventDispatcher\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Factory;

class DevpackPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var  Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
            PluginEvents::INIT => array(
                array('onInit', 0)
            ),
        );
    }

    /**
     * Replace remote file system on S3 protocol download
     *
     * @param Event $event
     */
    public function onInit(Event $event)
    {
        $composerConfig = $this->composer->getConfig();

        $packagesDir = $composerConfig->get('vendor-dir') . '/../packages/plentymarkets/';

        if (!is_dir($packagesDir))
        {
            return;
        }


        $it = new \RecursiveDirectoryIterator($packagesDir, \RecursiveDirectoryIterator::SKIP_DOTS);

        $localComposer = [
            'require'      => '',
        ];

        foreach ($it as $dir)
        {
            if ($dir->isDir())
            {
                $localComposer['require']['plentymarkets/' . $dir->getFilename()] = '*';
                /*
								$localComposer['repositories'][] = [
									'type' => 'git',
									'url'  => $dir->getPathname(),
								];*/
            }
        }

        $composerLocalPath = $composerConfig->get('vendor-dir') . '/../packages/composer.local.json';

        file_put_contents($composerLocalPath, json_encode($localComposer, JSON_UNESCAPED_SLASHES));

        if (!file_exists($composerLocalPath))
        {
            return;
        }

        $composer = $this->composer;

        $factory = new Factory();

        $localComposer = $factory->createComposer(
            $this->io,
            $composerLocalPath,
            true,
            null,
            false
        );

        // Merge repositories.
        $repositories = array_merge($composer->getPackage()->getRepositories(), $localComposer->getPackage()->getRepositories());
        if (method_exists($composer->getPackage(), 'setRepositories'))
        {
            $composer->getPackage()->setRepositories($repositories);
        }

        // Merge requirements.
        $requires = array_merge($composer->getPackage()->getRequires(), $localComposer->getPackage()->getRequires());
        $composer->getPackage()->setRequires($requires);
    }
}
