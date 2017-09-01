<?php

namespace App\Console;

use Kdyby;
use Nette;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Kdyby\Translation\MessageCatalogue;
use App\Translate\Extractor\PhpExtractor;
use Tracy\Debugger;

/**
 * Description of generateTranslation
 *
 * @author vsek
 */
class generateTranslation extends Command
{

    /**
     * @var string
     */
    public $defaultOutputDir = '%tempDir%/lang';

    /**
     * @var Kdyby\Translation\Translator
     */
    private $translator;

    /**
     * @var \Symfony\Component\Translation\Writer\TranslationWriter
     */
    private $writer;

    /**
     * @var \Symfony\Component\Translation\Extractor\ChainExtractor
     */
    private $extractor;

    /**
     * @var Nette\DI\Container
     */
    private $serviceLocator;

    /**
     * @var string
     */
    private $outputFormat;

    /**
     * @var array
     */
    private $scanDirs;

    /**
     * @var string
     */
    private $outputDir;

    /**
     *
     * @var string
     */
    private $langDir;

    private $defaultOutputFormat = 'php';

    /**
     *
     * @var \App\Model\Module\Translate
     */
    private $translates;

    /**
     *
     * @var \App\Model\Module\Language
     */
    private $languages;

    /**
     *
     * @var Kdyby\Translation\CatalogueCompiler;
     */
    private $catalogeCompiler;

    protected function configure()
    {
        $this->setName('cms:translation')
            ->setDescription('Extracts strings from application to translation files')
            ->addOption('scan-dir', 'd', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "The directory to parse the translations. Can contain %placeholders%.", ['%appDir%/FrontModule', '%appDir%/model'])
            ->addOption('output-format', 'f', InputOption::VALUE_REQUIRED, "Format name of the messages.", $this->defaultOutputFormat)
            ->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, "Directory to write the messages to. Can contain %placeholders%.", $this->defaultOutputDir)
            ->addOption('lang-dir', 'l', InputOption::VALUE_OPTIONAL, "Directory to write the messages to. Can contain %placeholders%.", '%appDir%/lang');
        // todo: append
    }



    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->translator = $this->getHelper('container')->getByType('Kdyby\Translation\Translator');
        $this->writer = $this->getHelper('container')->getByType('Symfony\Component\Translation\Writer\TranslationWriter');
        $this->extractor = $this->getHelper('container')->getByType('Symfony\Component\Translation\Extractor\ChainExtractor');
        $this->translates = $this->getHelper('container')->getByType('App\Model\Module\Translate');
        $this->languages = $this->getHelper('container')->getByType('App\Model\Module\Language');
        $this->catalogeCompiler = $this->getHelper('container')->getByType('Kdyby\Translation\CatalogueCompiler');
        $this->serviceLocator = $this->getHelper('container')->getContainer();
    }



    protected function validate(InputInterface $input, OutputInterface $output)
    {
        if (!in_array($this->outputFormat = trim($input->getOption('output-format'), '='), $formats = $this->writer->getFormats(), TRUE)) {
            $output->writeln('<error>Unknown --output-format</error>');
            $output->writeln(sprintf("<info>Choose one of: %s</info>", implode(', ', $formats)));

            return FALSE;
        }

        $this->scanDirs = $this->serviceLocator->expand($input->getOption('scan-dir'));
        foreach ($this->scanDirs as $dir) {
            if (!is_dir($dir)) {
                $output->writeln(sprintf('<error>Given --scan-dir "%s" does not exists.</error>', $dir));

                return FALSE;
            }
        }

        if (!is_dir($this->outputDir = $this->serviceLocator->expand($input->getOption('output-dir'))) || !is_writable($this->outputDir)) {
            $output->writeln(sprintf('<error>Given --output-dir "%s" does not exists or is not writable.</error>', $this->outputDir));

            return FALSE;
        }

        if (!is_dir($this->langDir = $this->serviceLocator->expand($input->getOption('lang-dir'))) || !is_writable($this->langDir)) {
            $output->writeln(sprintf('<error>Given --output-dir "%s" does not exists or is not writable.</error>', $this->langDir));

            return FALSE;
        }

        return TRUE;
    }



    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->validate($input, $output) !== TRUE) {
            return 1;
        }

        $this->extractor->addExtractor('php', new PhpExtractor());


        $defaultLocale = null;
        foreach($this->translator->getFallbackLocales() as $locale){
            if(is_null($defaultLocale)){
                $defaultLocale = $locale;

                $catalogue = new MessageCatalogue($locale);
                $output->writeln(sprintf('<info>Locale %s</info>', $locale));
                foreach ($this->scanDirs as $dir) {
                    $output->writeln(sprintf('<info>Extracting %s</info>', $dir));
                    $this->extractor->extract($dir, $catalogue);
                }
            }
        }

        $this->writer->writeTranslations($catalogue, $this->outputFormat, [
            'path' => $this->outputDir,
        ]);

        $output->writeln('');
        $output->writeln(sprintf('<info>Catalogue was written to %s</info>', $this->outputDir));

        $output->writeln('');
        $output->writeln(sprintf('<info>Zapisuji do DB</info>'));

        $toTranslate = include dirname(__FILE__) . '/../../../../../temp/lang/messages.' . $defaultLocale . '.php';
        $texts = array('');
        foreach($toTranslate as $translate){
            $texts[] = $translate;
            $trans = $this->translates->where('text', $translate)->fetch();
            if(!$trans){
                $this->translates->insert(array('text' => $translate));
            }
        }
        $this->translates->where('NOT text', $texts)->delete();

        foreach($this->translator->getFallbackLocales() as $locale) {
            $language = $this->languages->where('translate_locale', $locale)->fetch();
            $catalogue = new MessageCatalogue($language['translate_locale']);
            foreach ($this->translates->getAll() as $translate) {
                $translatesLocale = $translate->related('translate_locale')->where('language_id', $language['id'])->fetch();
                if ($translatesLocale) {
                    $catalogue->set($translate['text'], $translatesLocale['translate']);
                } else {
                    $catalogue->set($translate['text'], $translate['text']);
                }
            }
            $this->writer->writeTranslations($catalogue, 'neon', [
                'path' => $this->langDir,
            ]);
        }

        $this->catalogeCompiler->invalidateCache();

        return 0;
    }

}