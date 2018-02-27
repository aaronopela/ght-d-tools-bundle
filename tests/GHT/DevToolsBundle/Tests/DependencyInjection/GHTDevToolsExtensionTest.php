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
     * Verify that the API method map is loaded.
     */
    public function testParametersContainApiMethodMapWithRequiredConfigs()
    {
        $this->load(array(
            'bundle' => 'GHTDevToolsBundle',
        ));

        $this->assertContainerBuilderHasParameter('d_tools.bundle', 'GHTDevToolsBundle');
        $this->assertContainerBuilderHasParameter('d_tools.path', null);
        $this->assertContainerBuilderHasParameter('d_tools.translations_path', null);

        $this->assertContainerBuilderHasParameter(
            'd_tools.doctrine_generate_entities',
            array('defaults' => array(
                'namespace' => null,
                'path' => null,
                'no_backup' => false,
            ))
        );

        $this->assertContainerBuilderHasParameter(
            'd_tools.translation_update',
            array(
                'primary_locale' => null,
                'conversions' => array(
                    'amp_as_char' => false,
                    'amp_as_entity' => false,
                    'ltgt_as_char' => false,
                    'ltgt_as_entity' => false,
                    'nbsp_as_char' => false,
                    'nbsp_as_entity' => false,
                ),
                'defaults' => array(
                    'clean' => false,
                    'domain' => 'messages',
                    'locales' => array('en'),
                    'mode' => 'force',
                    'no_backup' => false,
                    'no_prefix' => false,
                    'output_format' => 'xlf',
                    'prefix' => '__',
                ),
            )
        );

        $this->assertContainerBuilderHasParameter(
            'd_tools.translation_add',
            array(
                'defaults' => array(
                    'domain' => 'messages',
                    'locale' => 'en',
                    'file_format' => 'xlf',
                    'refresh' => false,
                ),
            )
        );
    }
}
