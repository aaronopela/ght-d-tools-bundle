<?php
namespace GHT\DevToolsBundle\Command;

use GHT\DevToolsBundle\Command\DevToolsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Refresh entities.
 */
class EntitiesRefreshCommand extends DevToolsCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('d:ent:refresh')
            ->setDescription('Refresh the Doctrine entities with preset configurations.')
            ->addArgument('name', InputArgument::OPTIONAL, 'A bundle name, a namespace, or a class name')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Do not backup existing entities files (if backup is configured default)')
            ->addOption('force-backup', null, InputOption::VALUE_NONE, 'Force backup of existing entities files (if backup is not configured by default)')
        ;
    }

    /**
     * Execute the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface Input interface
     * @param \Symfony\Component\Console\Output\OutputInterface Output interface
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($input, $output);

        $options = $this->resolveOptions();

        // Configure the command
        $refresh = $this->getApplication()->find('doctrine:generate:entities');
        $refreshArgs = array(
            'command' => 'doctrine:generate:entities',
            'name' => $options['name'],
        );

        if ($options['no_backup']) {
            $refreshArgs['--no-backup'] = true;
        }

        // Run the command
        $refresh->run(new ArrayInput($refreshArgs), $this->output);

        // End the command process
        $this->end();
    }

    /**
     * {@inheritdoc}
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
        parent::init($input, $output);

        $this->defaults = $this->getContainer()->getParameter('d_tools.doctrine_generate_entities.defaults');

        // This command should only be run in a dev environment
        if (strpos($this->environment, 'dev') === false) {
            $this->error = "This command can only be run on a development environment!";
        }
    }

    /**
     * Resolve the option values with the default values.
     *
     * @return array
     */
    protected function resolveOptions(): array
    {
        return array(
            'name' => $this->input->getArgument('name') ?? $this->getContainer()->getParameter('d_tools.bundle'),
            'no_backup' => $this->input->getOption('no-backup')
                ? true
                : ($this->input->getOption('force-backup') ? false : $this->defaults['no_backup']),
        );
    }
}
