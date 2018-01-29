<?php

namespace GHT\DevToolsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * Load the GHTDevToolsBundle configuration.
 */
class GHTDevToolsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        // Process the configs
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Load the commands as services
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        // Set the parameters for this extension recursively in the container
        $container->setParameter($this->getAlias(), $config);
        array_walk(
            $config,
            array($this, 'setParameters'),
            array('parentKey' => $this->getAlias(), 'container' => $container)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getAlias()
    {
        return 'd_tools';
    }

    /**
     * Recursively set all the configuration values as parameters.
     *
     * @param array|string $config
     * @param string $key
     * @param array $params
     */
    public function setParameters($config, string $key, array $params)
    {
        // Recursively set the parameter name
        $parameterName = $params['parentKey'] . '.' . $key;

        // Set the parameter
        $params['container']->setParameter($parameterName, $config);

        // If the configuration has children, recursively call this function for each array value
        if (is_array($config)) {
            array_walk(
                $config,
                array($this, 'setParameters'),
                array('parentKey' => $parameterName, 'container' => $params['container'])
            );
        }
    }
}
