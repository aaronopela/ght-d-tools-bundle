<?php

namespace GHT\DevToolsBundle\Tests;

use GHT\DevToolsBundle\Tests\Fixtures\DBAL\DatabasePrimer;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

abstract class DevToolsCommandFunctionalTestCase extends KernelTestCase
{
    /**
     * @var \Symfony\Bundle\FrameworkBundle\Console\Application
     */
    protected $application;

    /**
     * @var \Symfony\Component\Console\Tester\CommandTester
     */
    protected $tester;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $this->application = new Application($kernel);

        // Initialize the container if an extension of this class hasn't yet
        if (!(self::$container instanceof \appTestDebugProjectContainer)
            && !(self::$container instanceof \appWeb_testDebugProjectContainer)
        ) {
            self::$container = $kernel->getContainer();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        unset($this->tester);
        unset($this->application);
    }

    /**
     * Initialize a command.
     *
     * @param string $name The command name.
     */
    public function configureCommand($name)
    {
        $command = $this->application->find($name);

        $this->tester = new CommandTester($command);
    }
}
