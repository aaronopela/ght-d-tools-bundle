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
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config/'));

        // Load preset configs and merge with incoming configs
        $path = $loader->getLocator()->locate('config.yml');
        $container->addResource(new FileResource($path));

        $yamlParser = new YamlParser();
        $content = $yamlParser->parse(file_get_contents($path));
        $configs = array_merge($content, $configs);

        $configuration = new Configuration();

        // Process the merged configs
        $config = $this->processConfiguration($configuration, $configs);

        // Set the parameters for this extension recursively in the container
        $container->setParameter($this->getAlias(), $config);
        array_walk(
            $config,
            array($this, 'setParameters'),
            array('parentKey' => $this->getAlias(), 'container' => $container)
        );

        // Load services
        $loader->load('services.yml');
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
