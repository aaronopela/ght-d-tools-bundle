<?php

$loader = require (__DIR__ . '/../vendor/autoload.php');

$loader->add('GHT\\DevToolsBundle\\Fixtures', __DIR__);

function registerContainerConfiguration($loader)
{
    $loader->load(__DIR__ . '/GHT/DevToolsBundle/Fixtures/Resources/config/config_test.yml');
}

function registerBundles()
{
    return array(
        new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
        new GHT\DevToolsBundle\GHTDevToolsBundle(),
    );
}
