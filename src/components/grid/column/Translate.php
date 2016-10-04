<?php

namespace App\Grid\Column;

/**
 * Description of Translate
 *
 * @author vsek
 */
class Translate extends Column{
    
    private $languageId;
    
    public function __construct($column, $name, $languageId) {
        parent::__construct($column, $name);
        $this->languageId = $languageId;
    }
    
    public function output(\Nette\Database\Table\ActiveRow $query) {
        $translateLocale = $query->related('translate_locale')->where('language_id', $this->languageId)->fetch();
        if($translateLocale){
            return parent::output($translateLocale);
        }else{
            return '';
        }
    }
}
