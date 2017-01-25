<?php

use Symfony\Component\Config\Loader\LoaderInterface;

$loader = require (__DIR__ . '/../vendor/autoload.php');

$loader->add('GHT\DevToolsBundle\Fixtures', __DIR__);

function registerContainerConfiguration(LoaderInterface $loader)
{
    $loader->load(__DIR__ . '/Fixtures/config/config.yml');
}

function registerBundles() {
    return array(
        new GHT\DevToolsBundle\GHTDevToolsBundle(),
    );
}
