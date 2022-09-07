<?php

namespace GHT\DevToolsBundle\Tests;

use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class Kernel extends BaseKernel
{
    /**
     * {@inheritdoc}
     */
    public function registerBundles(): array
    {
        $bundles = array(
            new \GHT\DevToolsBundle\GHTDevToolsBundle(),
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
        );

        return $bundles;
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(sprintf('%s/Fixtures/Resources/config/config_%s.yml', __DIR__, $this->getEnvironment()));
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/ght-d-tools-test/cache';
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/ght-d-tools-test/logs';
    }
}
