<?php

namespace App\Grid;

use App\AdminModule\Form;
use Nette\Utils\Strings;

/**
 * Description of GridTranslate
 *
 * @author vsek
 */
class GridTranslate extends Grid{
    
    /**
     * @persistent
     */
    public $filter = array();
    
    public function render(){
        
        if(isset($this->filter['text']) && $this->filter['text']){
            $translatesLocale = $this->getPresenter()->translatesLocale->where('LOWER(translate) REGEXP ?', Strings::lower($this->filter['text']))->where('language_id', $this->getPresenter()->webLanguage);
            $translateId = array();
            foreach($translatesLocale as $tl){
                $translateId[] = $tl['translate_id'];
            }
            if(!empty($translateId)){
                $this->model->where('LOWER(text) REGEXP ? OR id IN ?', array(Strings::lower($this->filter['text']), $translateId));
            }else{
                $this->model->where('LOWER(text) REGEXP ?', Strings::lower($this->filter['text']));
            }
        }
        
        parent::render();
    }
    
    public function submitFormFilter(Form $form){
        $this->filter = (array)$form->getValues();
        $this->getPresenter()->redirect('this');
    }
    
    protected function createComponentFormFilter($name){
        $form = new Form($this, $name);
        
        $form->addText('text', $this->getPresenter()->translator->translate('translate.text'));
        $form->addSubmit('send', $this->getPresenter()->translator->translate('admin.form.filtrate'));
        $form->onSuccess[] = [$this, 'submitFormFilter'];
        
        $form->setDefaults($this->filter);
        
        return $form;
    }
}
