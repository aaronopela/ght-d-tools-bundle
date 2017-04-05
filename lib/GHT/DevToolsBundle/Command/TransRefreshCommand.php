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
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $primaryLocale;

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('d:trans:refresh')
            ->setDescription('Refresh the bundle translations.')
            ->addArgument('bundle', InputArgument::OPTIONAL, 'The target bundle.')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Specify the domain to update')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'One or more locales, all configured locales by default')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Should the update be done (if configured default is to dump the messages in the console)')
            ->addOption('dump', null, InputOption::VALUE_NONE, 'Should the messages be dumped in the console (if configured default is to force the update)')
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
        $directory = $this->getContainer()->get('file_locator')->locate(sprintf(
            '%s/Resources/translations',
            $this->input->getArgument('bundle') ?? $this->path ?? $this->bundle
        ));
        $fileNames = $this->scanDir($directory);

        foreach ($fileNames as $fileName) {

            if (preg_match('/~$/', $fileName)) {
                continue 1;
            }

            $this->output->writeln(sprintf('Cleaning up <info>%s</info>...', $fileName));

            // Load the XML
            $xml = new \SimpleXMLElement(sprintf('%s/%s', $directory, $fileName), LIBXML_NOBLANKS, true);

            // Fix the file attributes
            $fileNode = $xml->children()->file;
            $sourceLanguage = $fileNode->attributes()['source-language'];
            if ($this->primaryLocale && $sourceLanguage !== $this->primaryLocale) {
                $sourceLanguage = $this->primaryLocale;
                $fileNode->attributes()['source-language'] = $this->primaryLocale;
            }

            $matches = array();
            preg_match('/\.([a-z]{2})\./', $fileName, $matches);
            $targetLanguage = $matches[1];

            $fileNode->attributes()['original'] = preg_replace('/\.[a-z]{2}\./', sprintf('.%s.', $sourceLanguage), $fileName);
            $fileNode->attributes()['target-language'] = $targetLanguage;

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

                $target = (string) $transUnit->children()->target;

                // if this is not yet translated, check if it should be ignored
                if ($this->primaryLocale
                    && $this->primaryLocale !== $targetLanguage
                    && ($target === '' || substr($target, 0, 2) === '__')
                ) {
                    continue 1;
                }

                $newTransUnit = $bodyNode->addChild('trans-unit');
                $newTransUnit->addAttribute('id', $transUnit->attributes()['id']);
                $newTransUnit->addAttribute('resname', $resname);

                try {
                    $source = (string) $transUnit->children()->source;
                    $newTransUnit->addChild('source', $source);
                }
                catch (\Exception $e) {
                    $this->output->writeln(sprintf('-- Bad source for trans-unit <info>%s</info>: <fg=red>%s</>', $resname, $source));
                    $this->output->writeln(sprintf('-- (%s: <comment>%s</comment>)', get_class($e), $e->getMessage()));
                }

                try {
                    // rewrite non-breaking space entities as UTF-8 character
                    $target = str_replace('&nbsp;', ' ', $target);

                    $newTransUnit->addChild('target', $target);
                }
                catch (\Exception $e) {
                    $this->output->writeln(sprintf('-- Bad target for trans-unit <info>%s</info>: <fg=red>%s</>', $resname, $target));
                    $this->output->writeln(sprintf('-- (%s: <comment>%s</comment>)', get_class($e), $e->getMessage()));
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

                $target = $targetNode->textContent;

                if ($this->defaults['prefix'] && !$this->defaults['no_prefix']) {
                    // prompt for untranslated strings, default to the original
                    $regex = sprintf('/^%s(.*)/', $this->defaults['prefix']);
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

        $this->defaults = $this->getContainer()->getParameter('d_tools.translation_update.defaults');

        // This command should only be run in a dev environment
        if (strpos($this->environment, 'dev') === false) {
            $this->error = "This command can only be run on a development environment!";
        }

        $this->bundle = $input->getArgument('bundle') ?? $this->getContainer()->getParameter('d_tools.bundle');
        $this->path = $this->getContainer()->getParameter('d_tools.path');
        $this->path = $this->path ? rtrim($this->path, '/') : null;
        $this->primaryLocale = $this->getContainer()->getParameter('d_tools.translation_update.primary_locale');
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
            'domain' => $this->input->getOption('domain') ?? $this->defaults['domain'],
            'locales' => $this->input->getOption('locale')
                ? $this->input->getOption('locale')
                : $this->defaults['locales'],
            'mode' => $this->input->getOption('dump')
                ? 'dump-messages'
                : ($this->input->getOption('force') ? 'force' : $this->defaults['mode']),
            'no_backup' => $this->input->getOption('no-backup')
                ? true
                : ($this->input->getOption('force-backup') ? false : $this->defaults['no_backup']),
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

        foreach ($options['locales'] as $locale) {

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
                $refreshArgs['--prefix'] = $options['prefix'];
            }

            if ($options['domain']) {
                $refreshArgs['--domain'] = $options['domain'];
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
