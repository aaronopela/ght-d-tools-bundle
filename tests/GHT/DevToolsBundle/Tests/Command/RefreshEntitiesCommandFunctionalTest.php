<?php

namespace GHT\DevToolsBundle\Tests\Command;

use Doctrine\Bundle\DoctrineBundle\Command\GenerateEntitiesDoctrineCommand;
use GHT\DevToolsBundle\Command\RefreshEntitiesCommand;
use GHT\DevToolsBundle\Tests\DevToolsCommandFunctionalTestCase;

class RefreshEntitiesCommandFunctionalTest extends DevToolsCommandFunctionalTestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->application->addCommands(array(
            new GenerateEntitiesDoctrineCommand(),
            new RefreshEntitiesCommand(),
        ));

        $this->configureCommand('d:ent:refresh');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * Verify that the entities are refreshed.
     */
    public function testExecute()
    {
        $this->tester->execute(array(
            '--env' => 'dev',
        ));

        $display = $this->tester->getDisplay();

        $this->assertContains('Generating entities for bundle "DevToolsBundle"', $display);
    }

    /**
     * Verify that no entities are refreshed if not running on a dev
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
