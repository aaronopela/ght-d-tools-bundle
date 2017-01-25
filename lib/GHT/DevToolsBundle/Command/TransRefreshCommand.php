<?php
namespace GHT\DevToolsBundle\Command;

use GHT\DevToolsBundle\Command\DevToolsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Refresh translations.
 */
class TransRefreshCommand extends DevToolsCommand
{
    /**
     * @var string
     */
    protected $bundle;

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->defaults = $this->getContainer()->getParameter('d_tools.translation_update.defaults');

        $this
            ->setName('d:trans:refresh')
            ->setDescription('Refresh the bundle translations.')
            ->addArgument('bundle', InputArgument::OPTIONAL, 'The target bundle.', $this->getContainer()->getParameter('d_tools.bundle'))
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Specify the domain to update', $this->defaults['domain'] ?? : null)
        ;

        // If default mode is dump, allow the force
        if ($this->defaults['mode'] === 'dump') {
            $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Should the update be done (configured default is to dump the messages in the console)');
        }
        // Otherwise, the messages to be dumped
        else {
            $this->addOption('dump', 'd', InputOption::VALUE_NONE, 'Should the messages be dumped in the console (configured default is to force the update)');
        }

        // If no_backup is not a default, allow it to be set
        if (empty($this->defaults['no_backup'])) {
            $this->addOption('no_backup', 'n', InputOption::VALUE_NONE, 'Do not backup existing entities files (backup is configured default)');
        }
        // Otherwise, allow a backup to be forced
        else {
            $this->addOption('force_backup', 'f', InputOption::VALUE_NONE, 'Force backup of existing entities files (backup is not configured by default)');
        }
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
        if ($this->error) {
            return $this->end();
        }

        // Run the translation command targeting the bundle
        $this->returnCode = $this->runTranslationUpdate();
        if ($this->returnCode || $this->error) {
            return $this->end();
        }

        // Clean up the translation files
        $this->returnCode = $this->cleanXml();

        // End the command process
        $this->end();
    }

    /**
     * Clean up the translation XML.
     *
     * @return integer
     */
    protected function cleanXml(): int
    {
        // Get the translation file names for the target bundle
        $directory = sprintf('src/%s/Resources/translations', $this->bundle);
        $fileNames = $this->scanDir($directory);

        foreach ($fileNames as $fileName) {
            $this->output->writeln(sprintf('Cleaning up <info>%s</info>...', $fileName));

            // Load the XML
            $xml = new \SimpleXMLElement(sprintf('%s/%s', $directory, $fileName), LIBXML_NOBLANKS, true);

            // Fix the file attributes
            $matches = array();
            preg_match('/\.([a-z]{2})\./', $fileName, $matches);
            $originalName = preg_replace('/\.[a-z]{2}\./', '.en.', $fileName);
            $fileNode = $xml->children()->file;
            $fileNode->attributes()['original'] = $originalName;
            $fileNode->attributes()['target-language'] = $matches[1];

            // Collect all the tokens
            $transUnits = array();
            foreach ($xml->children()->file->body->children() as $transUnit) {
                $resname = $transUnit->attributes()['resname'];
                $transUnits[(string) $resname] = $transUnit;
            }

            // Reset the XML body
            $bodyNode = new \SimpleXMLElement('<body></body>');

            // Add the trans-units back to the body in sorted order
            ksort($transUnits, SORT_NATURAL);

            foreach ($transUnits as $resname => $transUnit) {
                $newTransUnit = $bodyNode->addChild('trans-unit');
                $newTransUnit->addAttribute('id', $transUnit->attributes()['id']);
                $newTransUnit->addAttribute('resname', $resname);

                try {
                    $source = (string) $transUnit->children()->source;
                    $newTransUnit->addChild('source', $source);
                }
                catch (\Exception $e) {
                    $this->output->writeln(sprintf('-- Bad source for trans-unit <info>%s</info>: <fg=red>%s</>', $resname, $source));
                }

                try {
                    $target = (string) $transUnit->children()->target;
                    $newTransUnit->addChild('target', $target);
                }
                catch (\Exception $e) {
                    $this->output->writeln(sprintf('-- Bad target for trans-unit <info>%s</info>: <fg=red>%s</>', $resname, $target));
                }
            }

            // Load the XML into a formatted DOM document
            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());

            // Load the generated body XML into a DOM document
            $bodyDom = new \DOMDocument('1.0');
            $bodyDom->loadXML($bodyNode->asXML());

            // Process all the target nodes for any zapped CDATA wrapping
            $targetNodes = $bodyDom->getElementsByTagName('target');
            $helper = $this->getHelper('question');

            foreach ($targetNodes as $targetNode) {
                if ($this->defaults['prefix'] && !$this->defaults['no_prefix']) {
                    // prompt for untranslated strings, default to the original
                    $regex = sprintf('/^%s(.*)/', $this->defaults['prefix']);
                    $target = $targetNode->textContent;
                    $matches = array();
                    if (preg_match($regex, $target, $matches)) {
                        $question = new Question(sprintf('Translation (<comment>%s</comment>): ', $matches[1]), $target);
                        $target = $helper->ask($this->input, $this->output, $question);
                        $targetNode->textContent = $target;
                    }
                }

                // decode and encode entities to catch any strays
                $target = html_entity_decode($target);
                $target = htmlspecialchars($target, ENT_NOQUOTES);

                // if any entities, replace content with a child CDATA node
                if (strpos($target, '&') !== false) {
                    $targetNode->textContent = '';
                    $cdata = $targetNode->ownerDocument->createCDataSection($target);
                    $targetNode->appendChild($cdata);
                }
            }

            // Import the new body node into the master DOM document
            $newBodyNode = $bodyDom->getElementsByTagName('body')->item(0);
            $newBodyNode = $dom->importNode($newBodyNode, true);

            // Get the old body node and replace it with the imported node
            $oldBodyNode = $dom->getElementsByTagName('body')->item(0);
            $fileNode = $dom->getElementsByTagName('file')->item(0);
            $fileNode->replaceChild($newBodyNode, $oldBodyNode);

            if ($dom->save(sprintf('%s/%s', $directory, $fileName))) {
                if ($this->isVerbose) {
                    $this->output->writeln(sprintf('-- Sorted <comment>%d</comment> trans-unit elements.', count($transUnits)));
                }
            }
            else {
                $this->output->writeln(sprintf('<error>Could not save sorted XML to file!</error>'));
            }
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
        parent::init($input, $output);

        // This command should only be run in a dev environment
        if (strpos($this->environment, 'dev') === false) {
            $this->error = "This command can only be run on a development environment!";
        }

        $this->bundle = $input->getArgument('bundle');
    }

    /**
     * Resolve the option values with the default values.
     *
     * @return array
     */
    protected function resolveOptions(): array
    {
        return array(
            'clean' => $this->defaults['clean'],
            'domain' => $this->input->getOption('domain'),
            'locales' => $this->input->getOption('locales'),
            'mode' => $this->input->getOption('dump')
                ? 'dump-messages'
                : ($this->input->getOption('force') ? 'force' : $this->defaults['mode']),
            'no_backup' => $this->input->getOption('no_backup')
                ? true
                : ($this->input->getOption('force_backup') ? false : $this->defaults['no_backup']),
            'no_prefix' => $this->defaults['no_prefix'],
            'output_format' => $this->defaults['output_format'],
            'prefix' => $this->defaults['prefix'],
        );
    }

    /**
     * Run the translation update command.
     *
     * @return integer
     */
    protected function runTranslationUpdate(): int
    {
        $options = $this->resolveOptions();

        // Get the command
        $refresh = $this->getApplication()->find('translation:update');

        foreach ($locales as $locale) {

            // Configure the command
            $refreshArgs = array(
                'command' => 'translation:update',
                '--' . $options['mode'] => true,
                '--output-format' => $options['output_format'],
                'locale' => $locale,
                'bundle' => $this->bundle,
            );

            if ($options['no_backup']) {
                $refreshArgs['--no-backup'] = true;
            }

            if ($options['no_prefix']) {
                $refreshArgs['--no-prefix'] = true;
            }
            elseif ($options['prefix']) {
                $refreshArgs['prefix'] = $options['prefix'];
            }

            if ($options['domain']) {
                $refreshArgs['domain'] = $options['domain'];
            }

            if ($options['clean']) {
                $refreshArgs['--clean'] = true;
            }

            // Run the command for this locale
            $returnCode = $refresh->run(new ArrayInput($refreshArgs), $this->output);

            if ($returnCode) {
                return $returnCode;
            }
        }

        return 0;
    }
}
