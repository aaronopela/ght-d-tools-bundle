<?php

namespace GHT\DevToolsBundle\Tests\DependencyInjection;

use GHT\DevToolsBundle\DependencyInjection\GHTDevToolsExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Exercises the dependency injection on a compiler pass of the
 * GHTDevToolsBundle.
 */
class GHTDevToolsExtensionTest extends AbstractExtensionTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerExtensions()
    {
        return array(
            new GHTDevToolsExtension(),
        );
    }

    /**
     * Verify that the API method fails to load without minimum requirements.
     */
    public function testParametersContainApiMethodMapMissingRequiredConfigs()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child node "bundle" at path "d_tools" must be configured.');

        $this->load();
    }

    /**
     * Verify that the API method map is loaded.
     */
    public function testParametersContainApiMethodMapWithRequiredConfigs()
    {
        $this->load(array(
            'bundle' => 'GHTDevToolsBundle',
        ));

        $this->assertContainerBuilderHasParameter('d_tools.bundle', 'GHTDevToolsBundle');

        $this->assertContainerBuilderHasParameter(
            'd_tools.doctrine_generate_entities',
            array('defaults' => array('no_backup' => false))
        );

        $this->assertContainerBuilderHasParameter(
            'd_tools.translation_update',
            array('defaults' => array(
                'clean' => false,
                'domain' => 'messages',
                'locales' => array('en'),
                'mode' => 'force',
                'no_backup' => false,
                'no_prefix' => false,
                'output_format' => 'xlf',
                'prefix' => '__',
            ))
        );
    }
}