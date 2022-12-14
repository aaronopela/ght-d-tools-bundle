<?php
namespace GHT\DevToolsBundle\Command;

use GHT\DevToolsBundle\Command\DevToolsCommand;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Config\FileLocatorInterface;
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
     * @var array
     */
    protected $conversions;

    /**
     * @var string
     */
    protected $defaultPath;

    /**
     * @var array
     */
    protected $defaults;

    /**
     * \Symfony\Component\Config\FileLocatorInterface
     */
    protected $fileLocator;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $primaryLocale;

    /**
     * The constructor.
     *
     * @param Symfony\Component\Config\FileLocatorInterface $fileLocator
     * @param array $defaults
     * @param array $conversions
     * @param string $primaryLocale
     * @param string $bundle
     * @param string $defaultPath
     * @param string $path
     */
    public function __construct(FileLocatorInterface $fileLocator, array $defaults, array $conversions, string $primaryLocale = null, string $bundle = null, string $defaultPath = null, string $path = null)
    {
        parent::__construct();

        $this->fileLocator = $fileLocator;
        $this->defaults = $defaults;
        $this->conversions = $conversions;
        $this->primaryLocale = $primaryLocale;
        $this->bundle = $bundle;
        $this->defaultPath = $defaultPath;
        $this->path = $path;
    }

    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setDescription('Refresh the bundle translations.')
            ->addArgument('bundle', InputArgument::OPTIONAL, 'The target bundle.')
            ->addOption('domain', 'd', InputOption::VALUE_REQUIRED, 'Specify the domain to update')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'One or more locales, all configured locales by default')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'The translation files path')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Should the update be done (if configured default is to dump the messages in the console)')
            ->addOption('dump', null, InputOption::VALUE_NONE, 'Should the messages be dumped in the console (if configured default is to force the update)')
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
        return $this->end();
    }

    /**
     * Clean up the translation XML.
     *
     * @return integer
     */
    protected function cleanXml(): int
    {
        // Get the translation file names for the target bundle
        try {
            $directory = $this->fileLocator->locate($this->findTranslationsDir($this->input->getArgument('bundle')));
        }
        catch (FileLocatorFileNotFoundException $e) {
            $directory = $this->fileLocator->locate($this->input->getOption('path') ?? $this->path);
        }
        $fileNames = $this->scanDir($directory);

        $options = $this->resolveOptions();

        foreach ($fileNames as $fileName) {

            if (preg_match('/~$/', $fileName) || !preg_match(sprintf('/^%s\./', $options['domain']), $fileName)) {
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

            // Get the target language from the file name, considering possible
            // culture string
            $matches = array();
            if (!preg_match('/\.([a-z]{2})_*([A-Z]{0,2})\./', $fileName, $matches)) {
                $this->output->writeln(sprintf('<fg=red>Could not detect language from file name "</>%s<fg=red>", skipping file!</>', $fileNode));
                continue 1;
            }
            $targetLanguage = $matches[1];
            $targetCulture = $matches[2] ?? '';

            $fileNode->attributes()['original'] = preg_replace('/\.[a-z]{2}_*[A-Z]{0,2}\./', sprintf('.%s.', $sourceLanguage), $fileName);
            $fileNode->attributes()['target-language'] = $targetLanguage . ($targetCulture ? '-' . $targetCulture : '');

            // Collect all the tokens
            $transUnits = array();
            foreach ($xml->children()->file->body->children() as $transUnit) {
                $resname = $transUnit->attributes()['resname'];
                $transUnits[(string) $resname] = $transUnit;
            }

            // Reset the XML body
            $bodyDom = new \DOMDocument('1.0');
            $bodyNode = $bodyDom->appendChild($bodyDom->createElement('body'));

            // Add the trans-units back to the body in sorted order
            ksort($transUnits, SORT_NATURAL);

            foreach ($transUnits as $resname => $transUnit) {

                $target = (string) $transUnit->children()->target;

                // If this is not yet translated, check if it should be ignored
                if ($this->primaryLocale
                    && ($this->primaryLocale !== $targetLanguage || $targetCulture)
                    && ($target === '' || substr($target, 0, 2) === '__')
                ) {
                    continue 1;
                }

                $newTransUnit = $bodyNode->appendChild($bodyDom->createElement('trans-unit'));
                $idAttribute = $bodyDom->createAttribute('id');
                $idAttribute->value = $transUnit->attributes()['id'];
                $resnameAttribute = $bodyDom->createAttribute('resname');
                $resnameAttribute->value = $resname;
                $newTransUnit->appendChild($idAttribute);
                $newTransUnit->appendChild($resnameAttribute);

                try {
                    $source = (string) $transUnit->children()->source;
                    $newTransUnit->appendChild($bodyDom->createElement('source', $source));
                }
                catch (\Exception $e) {
                    $this->output->writeln(sprintf('-- Bad source for trans-unit <info>%s</info>: <fg=red>%s</>', $resname, $source));
                    $this->output->writeln(sprintf('-- (%s: <comment>%s</comment>)', get_class($e), $e->getMessage()));
                }

                try {
                    $target = $this->convertCharacters($target);

                    $newTransUnit->appendChild($bodyDom->createElement('target'))
                        ->appendChild($bodyDom->createTextNode($target))
                    ;
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

            // Process all the target nodes for any zapped CDATA wrapping
            $targetNodes = $bodyDom->getElementsByTagName('target');
            $helper = $this->getHelper('question');

            foreach ($targetNodes as $targetNode) {

                $target = $targetNode->textContent;

                if ($this->defaults['prefix'] && !$this->defaults['no_prefix']) {
                    // Prompt for untranslated strings, default to the original
                    $regex = sprintf('/^%s(.*)/', $this->defaults['prefix']);
                    $matches = array();
                    if (preg_match($regex, $target, $matches)) {
                        $question = new Question(sprintf('Translation (<comment>%s</comment>): ', $matches[1]), $target);
                        $target = $helper->ask($this->input, $this->output, $question);
                        $target = $this->convertCharacters($target);
                        $targetNode->textContent = $target;
                    }
                }

                // If any ampersands or greater than / less than characters,
                // replace content with a child CDATA node
                if (strpos($target, '&') !== false || strpos($target, '<') !== false || strpos($target, '>') !== false) {
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
     * Apply any configured character conversions.
     *
     * @param string $string The string with characters subject to conversion.
     *
     * @return string
     */
    protected function convertCharacters(string $string): string
    {
        if ($this->conversions['amp_as_char']) {
            // Rewrite ampersand entities as bare ampersand character
            $string = str_replace('&amp;', '&', $string);
        }
        elseif ($this->conversions['amp_as_entity']) {
            // Rewrite ampersands not in HTML entities as HTML entity
            $string = preg_replace('/\&([a-z]+;)/i', "~$1", $string);
            $string = str_replace('&', '&amp;', $string);
            $string = preg_replace('/~([a-z]+;)/i', "&$1", $string);
        }

        if ($this->conversions['ltgt_as_char']) {
            // Rewrite less than / greater than entities as characters
            $string = str_replace('&lt;', '<', $string);
            $string = str_replace('&gt;', '>', $string);
        }
        elseif ($this->conversions['ltgt_as_entity']) {
            // Rewrite less than / greater than characters as HTML entities
            $string = str_replace('<', '&lt;', $string);
            $string = str_replace('>', '&gt;', $string);
        }

        if ($this->conversions['nbsp_as_char']) {
            // Rewrite non-breaking space entities as UTF-8 character
            $string = str_replace('&nbsp;', '??', $string);
        }
        elseif ($this->conversions['nbsp_as_entity']) {
            // Rewrite non-breaking space characters as HTML entity
            $string = str_replace('??', '&nbsp;', $string);
        }

        return $string;
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

        $this->bundle = $input->getArgument('bundle') ?? $this->bundle;
        $this->path = $this->path ?? $this->defaultPath;
        $this->path = $this->path ? rtrim($this->path, '/') : null;
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
            );

            if ($this->input->getArgument('bundle') || ($this->bundle && !$this->path)) {

                // get the path of the bundle's translation directory
                $translationDir = $this->findTranslationsDir($this->input->getArgument('bundle'));

                // if found, set the bundle as the parent directory
                if ($translationDir) {
                    $refreshArgs['bundle'] = preg_replace('/\/translations$/', '', $translationDir);
                }
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
