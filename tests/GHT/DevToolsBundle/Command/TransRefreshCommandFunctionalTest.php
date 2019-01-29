<?php

namespace GHT\DevToolsBundle\Tests\Command;

use GHT\DevToolsBundle\Command\TransRefreshCommand;
use GHT\DevToolsBundle\Tests\DevToolsCommandFunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Command\TranslationUpdateCommand;

/**
 * Exercises the d:trans:refresh command.
 */
class TransRefreshCommandFunctionalTest extends DevToolsCommandFunctionalTestCase
{
    /**
     * @var string
     */
    protected $bundle;

    /**
     * @var string
     */
    protected $originalContents;

    /**
     * @var string
     */
    protected $messagesPathName;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->application->addCommands(array(
            self::$container->get(TransRefreshCommand::class),
            self::$container->get(TranslationUpdateCommand::class),
        ));

        $this->configureCommand('d:trans:refresh');

        // Override the default bundle path
        $this->bundle = self::$container->get('kernel')->getProjectDir() . '/tests/GHT/DevToolsBundle/Fixtures';
        $this->messagesPathName = sprintf('%s/messages.en.xlf', self::$container->getParameter('translator.default_path'));

        // File contents may be updated, so capture the original
        $this->originalContents = file_get_contents($this->messagesPathName);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        // Restore the original file contents to the translation file
        file_put_contents($this->messagesPathName, $this->originalContents);

        parent::tearDown();
    }

    /**
     * Verify that the translations are refreshed.
     */
    public function testExecute()
    {
        $this->tester->execute(array(
            'bundle' => $this->bundle,
            '--env' => 'dev',
        ));

        $display = $this->tester->getDisplay();

        $this->assertContains('Generating "en" translation files for', $display);

        // Verify that existing translation units were sorted
        $updatedContents = file_get_contents($this->messagesPathName);
        $this->assertRegExp('/resname="test.existing".*resname="test.unsorted.existing"/s', $updatedContents);
    }

    /**
     * Verify that no translations are refreshed if not running on a dev
     * environment.
     */
    public function testExecuteWrongEnvironment()
    {
        $this->tester->execute(array(
            'bundle' => $this->bundle,
            '--env' => 'prod',
        ));

        $this->assertContains(
            'This command can only be run on a development environment!',
            $this->tester->getDisplay()
        );
    }
}
