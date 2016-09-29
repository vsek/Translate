<?php

namespace App\Translate\Extractor;

use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Nette\Utils\Finder;
use Latte\Parser;

/**
 * Description of PhpExtractor
 *
 * @author vsek
 */
class PhpExtractor extends \Nette\Object implements ExtractorInterface{
    /**
    * @var string
    */
    private $prefix;

    /**
     *
     * @var array
     */
    private $keywords = array('translate', 'trans');

    /**
    * {@inheritDoc}
    */
    public function extract($directory, MessageCatalogue $catalogue)
    {
           foreach (Finder::findFiles('*.php')->from($directory) as $file) {
                   $this->extractFile($file, $catalogue);
           }
    }



    /**
    * Extracts translation messages from a file to the catalogue.
    *
    * @param string           $file The path to look into
    * @param MessageCatalogue $catalogue The catalogue
    */
    public function extractFile($file, MessageCatalogue $catalogue)
    {
        $pInfo = pathinfo($file);
        $tokens = token_get_all(file_get_contents($file));
        $data = array();
        $next = false;
        foreach ($tokens as $c)
        {
            if(is_array($c)) {
                if ($c[0] != T_STRING && $c[0] != T_CONSTANT_ENCAPSED_STRING) continue;
                if ($c[0] == T_STRING && in_array($c[1], $this->keywords)) {
                    $next = true;
                    continue;
                }

                if ($c[0] == T_CONSTANT_ENCAPSED_STRING && $next == true) {
                    $data[substr($c[1], 1, -1)][] = $pInfo['basename'] . ':' . $c[2];
                    $next = false;
                }
            } else {
                if ($c == ')') $next = false;
            }
        }
        foreach($data as $d => $value){
            $catalogue->set(($this->prefix ? $this->prefix . '.' : '') . $d, $d);
        }
    }



    /**
    * {@inheritDoc}
    */
    public function setPrefix($prefix)
    {
           $this->prefix = $prefix;
    }
}
