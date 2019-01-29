<?php

namespace GHT\DevToolsBundle\Tests\Command;

use GHT\DevToolsBundle\Command\TransAddCommand;
use GHT\DevToolsBundle\Tests\DevToolsCommandFunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Command\TranslationUpdateCommand;

/**
 * Exercises the d:trans:add command.
 */
class TransAddCommandFunctionalTest extends DevToolsCommandFunctionalTestCase
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
            self::$container->get(TransAddCommand::class),
            self::$container->get(TranslationUpdateCommand::class),
        ));

        $this->configureCommand('d:trans:add');

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
     * Verify that a translation can be added.
     */
    public function testExecute()
    {
        $this->tester->execute(array(
            'bundle' => $this->bundle,
            '--trans' => array('test.translation'),
        ));

        $updatedContents = file_get_contents($this->messagesPathName);

        $this->assertContains('<trans-unit id="test.translation" resname="test.translation">', $updatedContents);
        $this->assertContains('<source>test.translation</source>', $updatedContents);
        $this->assertContains('<target>__test.translation</target>', $updatedContents);
        $this->assertContains('</trans-unit>', $updatedContents);
    }

    /**
     * Verify that a translation can be added with the text already set.
     */
    public function testExecuteWithTranslationSet()
    {
        $this->tester->execute(array(
            'bundle' => $this->bundle,
            '--trans' => array('test.translation:Test translation'),
        ));

        $updatedContents = file_get_contents($this->messagesPathName);

        $this->assertContains('<target>Test translation</target>', $updatedContents);
    }

    /**
     * Verify that translations can be added if running in a dev environment.
     */
    public function testExecuteDevEnvironment()
    {
        $this->tester->execute(array(
            'bundle' => $this->bundle,
            '--env' => 'dev',
        ));

        $this->assertContains(
            'Done!',
            $this->tester->getDisplay()
        );
    }

    /**
     * Verify that no translations are refreshed if not running in a dev or
     * test environment.
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
