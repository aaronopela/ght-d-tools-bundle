<?php

namespace GHT\DevToolsBundle\Tests\DependencyInjection;

use GHT\DevToolsBundle\DependencyInjection\GHTDevToolsExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

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
    public function testParametersContainApiMethodMap()
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('d_tools.some.parameter', array('some' => 'value'));
    }

    /**
     * Verify that the service is loaded.
     */
    public function testDevToolsServiceIsLoaded()
    {
        $this->load();

        $this->assertContainerBuilderHasService('ght_mojang_api_client', 'GHT\MojangApiClientBundle\Service\GHTMojangApiClientService');
    }
}