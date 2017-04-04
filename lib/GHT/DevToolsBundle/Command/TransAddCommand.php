<?php
namespace GHT\DevToolsBundle\Command;

use GHT\DevToolsBundle\Command\DevToolsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Add one or more translations.
 */
class TransAddCommand extends DevToolsCommand
{
    /**
     * @var string
     */
    protected $bundle;

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var string
     */
    protected $fileFormat;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var boolean
     */
    protected $refresh;

    /**
     * @var string
     */
    protected $translations;

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('d:trans:add')
            ->setDescription('Add one or more translations.')
            ->addArgument('bundle', InputArgument::OPTIONAL, 'The target bundle.')
            ->addOption('domain', 'd', InputOption::VALUE_REQUIRED, 'The translation domain')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'The translation locale')
            ->addOption('trans', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The translation token to add (trans-unit resname property and source value).  Set the target value at the same time by adding a ":" followed by the translation string.')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Should the translation refresh be run after adding new translations (if refresh is not configured by default)')
            ->addOption('no-refresh', null, InputOption::VALUE_NONE, 'Suppress the auto translation refresh (if refresh is configured by default)')
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

        // Process the translations
        $this->returnCode = $this->processTranslations();
        if ($this->returnCode || $this->error) {
            return $this->end();
        }

        // Insert the translations
        $this->returnCode = $this->insertTranslations();
        if ($this->returnCode || $this->error) {
            return $this->end();
        }

        // Refresh translation files if requested
        if ($this->refresh) {
            $this->runTransRefresh();
        }

        // End the command process
        $this->end();
    }

    /**
     * {@inheritdoc}
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
        parent::init($input, $output);

        $this->defaults = $this->getContainer()->getParameter('d_tools.translation_add.defaults');

        // This command should only be run in a dev environment
        if (strpos($this->environment, 'dev') === false && strpos($this->environment, 'test') === false) {
            $this->error = "This command can only be run on a development environment!";
        }

        $options = $this->resolveOptions();

        $this->bundle = $options['bundle'];
        $this->domain = $options['domain'];
        $this->fileFormat = $options['file_format'];
        $this->locale = $options['locale'];
        $this->refresh = $options['refresh'];
        $this->translations = $this->input->getOption('trans');

        // For now, only XLF is supported
        if ($this->fileFormat !== 'xlf') {
            $this->error('This command currently only supports the <fg=black>XLF</> format.');
        }
    }

    /**
     * Insert the translations into the file matching the set translation
     * domain and locale.
     *
     * @return integer
     */
    protected function insertTranslations(): int
    {
        // Get the translation file names for the target bundle
        $directory = $this->getContainer()->get('file_locator')->locate(
            sprintf('%s/Resources/translations', $this->bundle)
        );
        $fileNames = $this->scanDir($directory);
        $targetFileName = sprintf('%s.%s.%s', $this->domain, $this->locale, $this->fileFormat);

        if (in_array($targetFileName, $fileNames)) {
            // Load the XML
            $xml = new \SimpleXMLElement(sprintf('%s/%s', $directory, $targetFileName), LIBXML_NOBLANKS, true);
        }
        else {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf('Translation file "<info>%s</info>" does\'t exist.  Create? (no) ', $targetFileName),
                false
            );

            if (!$helper->ask($this->input, $this->output, $question)) {
                $this->error = 'No translation file.';
                return 1;
            }

            $xmlString = sprintf(
                "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<xliff xmlns=\"urn:oasis:names:tc:xliff:document:1.2\" version=\"1.2\">\n  <file source-language=\"en\" target-language=\"%s\" datatype=\"plaintext\" original=\"%s.en.xlf\">\n    <body/>\n  </file>\n</xliff>\n",
                $this->locale,
                $this->domain
            );
            $xml = new \SimpleXMLElement($xmlString, LIBXML_NOBLANKS);
        }

        // Add the translations to the body
        $bodyNode = $xml->children()->file->body;

        foreach ($this->translations as $token => $transText) {
            $newTransUnit = $bodyNode->addChild('trans-unit');
            $newTransUnit->addAttribute('id', $token);
            $newTransUnit->addAttribute('resname', $token);
            $newTransUnit->addChild('source', $token);
            $newTransUnit->addChild('target', $transText);
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

        foreach ($targetNodes as $targetNode) {
            $target = $targetNode->textContent;

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

        if ($dom->save(sprintf('%s/%s', $directory, $targetFileName))) {
            if ($this->isVerbose) {
                $this->output->writeln(sprintf('-- Added <comment>%d</comment> trans-unit elements.', count($this->translations)));
            }
        }
        else {
            $this->output->writeln(sprintf('<error>Could not save XML to file!</error>'));
        }

        return 0;
    }

    /**
     * Process the input translations.
     *
     * @return integer
     */
    protected function processTranslations(): int
    {
        $processed = array();
        $prefix = $this->getContainer()->getParameter('d_tools.translation_update.defaults.prefix');

        foreach ($this->translations as $translation) {
            $transText = $prefix . $translation;

            if (strpos($translation, ':') !== false) {
                $matches = array();
                if (!preg_match('/^([a-z0-9\._\-]+):(.*)/i', $translation, $matches)) {
                    $this->output->writeln(sprintf('<error>Invalid token and translation:</error> <fg=red>%s</>', $translation));
                    continue 1;
                }

                $translation = $matches[1];
                $transText = $matches[2];
            }
            elseif (!preg_match('/^[a-z0-9\._\-]+$/i', $translation)) {
                $this->output->writeln(sprintf('<error>Invalid token:</error> <fg=red>%s</>', $translation));
                continue 1;
            }

            if (isset($processed[$translation])) {

                // if both aren't translated, don't worry about it
                if (preg_match('/^__/', $processed[$translation]) && preg_match('/^__/', $transText)) {
                    continue 1;
                }

                // if translated already
                if (!preg_match('/^__/', $processed[$translation])) {
                    // if not translated this time, don't worry about it
                    if (preg_match('/^__/', $transText)) {
                        continue 1;
                    }

                    // warn that the previous translation is getting overridden
                    $this->output->writeln(sprintf(
                        '<error>WARNING:</error> Token <fg=red>%s</> provided more than once, overriding with translated text: <comment>%s</comment>',
                        $translation,
                        $transText
                    ));
                }
            }

            $processed[$translation] = $transText;
        }

        $this->translations = $processed;

        return 0;
    }

    /**
     * Resolve the option values with the default values.
     *
     * @return array
     */
    protected function resolveOptions(): array
    {
        return array(
            'bundle' => $this->input->getArgument('bundle') ?? $this->getContainer()->getParameter('d_tools.bundle'),
            'domain' => $this->input->getOption('domain') ?? $this->defaults['domain'],
            'file_format' => strtolower($this->defaults['file_format']),
            'locale' => $this->input->getOption('locale') ?? $this->defaults['locale'],
            'refresh' => $this->input->getOption('refresh')
                ? true
                : ($this->input->getOption('no-refresh') ? false : $this->defaults['refresh']),
        );
    }

    /**
     * Run the translation refresh command.
     *
     * @return integer
     */
    protected function runTransRefresh(): int
    {
        // Refresh translations
        $refresh = $this->getApplication()->find('d:trans:refresh');

        $refreshArgs = array(
            'command' => 'd:trans:refresh',
            '--bundle' => $this->bundle,
        );

        $returnCode = $refresh->run(new ArrayInput($refreshArgs), $this->output);

        return $returnCode ?? 0;
    }
}
