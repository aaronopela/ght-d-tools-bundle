<?php

namespace GHT\DevToolsBundle\Tests\Command;

use GHT\DevToolsBundle\Command\RefreshTransCommand;
use GHT\DevToolsBundle\Tests\DevToolsCommandFunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Command\TranslationUpdateCommand;

class RefreshTransCommandFunctionalTest extends DevToolsCommandFunctionalTestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->application->addCommands(array(
            new RefreshTransCommand(),
            new TranslationUpdateCommand(),
        ));

        $this->configureCommand('d:trans:refresh');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * Verify that the translations are refreshed.
     */
    public function testExecute()
    {
        $this->tester->execute(array(
            '--env' => 'test_dev',
        ));

        $display = $this->tester->getDisplay();

        $this->assertContains('Generating "en" translation files for "DevToolsBundle"', $display);
    }

    /**
     * Verify that no translations are refreshed if not running on a dev
     * environment.
     */
    public function testExecuteWrongEnvironment()
    {
        $this->tester->execute(array(
            '--env' => 'prod',
        ));

        $this->assertContains(
            'This command can only be run on a development environment!',
            $this->tester->getDisplay()
        );
    }
}
