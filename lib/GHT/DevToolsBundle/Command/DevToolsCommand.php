<?php

namespace GHT\DevToolsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines the abstract D Tools command framework.
 */
abstract class DevToolsCommand extends ContainerAwareCommand
{
    /**
     * @var array
     */
    protected $defaults;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var string
     */
    protected $error;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var boolean
     */
    protected $isVerbose;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var integer
     */
    protected $returnCode;

    /**
     * End the command process nicely.
     */
    protected function end()
    {
        // Check if errors occurred
        if ($this->returnCode) {
            $this->output->writeln("<error>Process ended with error code: " . $this->returnCode . "</error>");
        }
        if ($this->error) {
            $this->output->writeln("<error>" . $this->error . "</error>");
        }
        if ($this->returnCode || $this->error) {
            $this->output->writeln("");
            return;
        }

        $this->output->writeln("<comment>Done!</comment>");
        $this->output->writeln("");
    }

    /**
     * Initialize common variables.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Input interface.
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output interface.
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
        $this->environment = $input->getOption('env', 'dev');
        $this->error = null;
        $this->input = $input;
        $this->isVerbose = $this->input->getOption('verbose');
        $this->output = $output;
    }

    /**
     * Output the command line being run
     *
     * @param array The arguments array for running the command.
     */
    protected function outputCommandLine(array $arguments)
    {
        // Ignore if not verbose
        if (!$this->isVerbose) {
            return;
        }

        // Build the command line to display
        $commandLine = '';

        foreach ($arguments as $key => $val) {
            $commandLine = sprintf(
                '%s %s%s',
                $commandLine,
                strpos($key, '--') === 0 ? $key . (is_bool($val) ? '' : '=') : '',
                is_bool($val) ? '' : $val
            );
        }

        // Output the command line being run
        $this->output->writeln(sprintf('<comment>%s</comment>', $commandLine));
    }

    /**
     * Clear the cache.
     *
     * @return integer Console return code
     */
    protected function refreshCache(): int
    {
        // Get the cache:clear command
        $command = $this->getApplication()->find('cache:clear');

        // Set the default arguments
        $arguments = array(
            'command' => 'cache:clear',
        );
        if ($this->isVerbose) {
            $arguments['--verbose'] = true;
        }

        // Initiate the input object with our arguments
        $localInput = new ArrayInput($arguments);

        // Run the cache:clear command
        $this->output->writeln('<comment>Running the cache:clear command...</comment>');
        $this->outputCommandLine($arguments);

        return $command->run($localInput, $this->output);
    }

    /**
     * Scan a directory to get the contents without the dot directories.
     *
     * @param string $directory The directory path to scan.
     *
     * @return array
     */
    public function scanDir(string $directory): array
    {
        $directory = rtrim($directory, '/');
        if ($this->isVerbose) {
            $this->output->writeln(sprintf('Scanning <info>%s</info>...', $directory));
        }

        if (!is_dir($directory)) {
            if ($this->isVerbose) {
                $this->output->writeln(sprintf(
                    '<error>The path <fg=black>%s</> %s!</error>',
                    $directory,
                    is_file($directory) ? 'is a file' : 'does not exist'
                ));
            }

            return array();
        }

        $fileNames = array_diff(scandir($directory), array('..', '.'));

        if ($this->isVerbose) {
            foreach ($fileNames as $fileName) {
                $this->output->writeln(sprintf(
                    '-- <comment>(%s)</comment> <info>%s</info>',
                    is_file(sprintf('%s/%s', $directory, $fileName)) ? 'file' : 'dir',
                    $fileName
                ));
            }
        }

        return $fileNames;
    }
}
