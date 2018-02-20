<?php

namespace App\AdminModule\Presenters;

use App\AdminModule\Form;
use App\Grid\Column\Translate;
use App\Grid\GridTranslate;
use App\Grid\Column\Column;
use App\Grid\Menu\Update;
use Symfony\Component\Translation\Writer\TranslationWriter;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Description of TranslatePresenter
 *
 * @author vsek
 */
class TranslatePresenterM extends BasePresenterM{
    
    /** @var \App\Model\Module\TranslateLocale @inject */
    public $translatesLocale;
    
    /** @var \App\Model\Module\Translate @inject */
    public $model;
    
    /**
     *
     * @var \Nette\Database\Table\ActiveRow
     */
    protected $row = null;
    
    /**
     * @inject
     * @var TranslationWriter
     */
    public $writer;
    
    /**
     * @inject
     * @var \Kdyby\Translation\CatalogueCompiler
     */
    public $catalogeCompiler;
    
    public function actionNew(){
        $this->template->setFile(dirname(__FILE__) . '/../templates/Translate/new.latte');
    }
    
    public function actionDefault(){
        $this->template->setFile(dirname(__FILE__) . '/../templates/Translate/default.latte');
    }
    
    public function submitFormTranslate(Form $form){
        $values = $form->getValues();
        
        //existuje preklad ?
        $translatesLocale = $this->row->related('translate_locale')->where('language_id', $this->webLanguage)->fetch();
        if($translatesLocale){
            if($values['translate'] != ''){
                $translatesLocale->update(array('translate' => $values['translate']));
            }else{
                $translatesLocale->delete();
            }
        }else{
            $this->row->related('translate_locale')->insert(array(
                'translate' => $values['translate'],
                'language_id' => $this->webLanguage,
            ));
        }
        
        $language = $this->languages->get($this->webLanguage);
        $catalogue = new MessageCatalogue($language['translate_locale']);
        foreach($this->model->getAll() as $translate){
            $translatesLocale = $translate->related('translate_locale')->where('language_id', $this->webLanguage)->fetch();
            if($translatesLocale){
                $catalogue->set($translate['text'], $translatesLocale['translate']);
            }else{
                $catalogue->set($translate['text'], $translate['text']);
            }
        }
        $this->writer->write($catalogue, 'neon', [
            'path' => $this->context->parameters['appDir'] . '/lang/',
        ]);
        
        $this->catalogeCompiler->invalidateCache();
        
        $this->flashMessage($this->translator->trans('translate.translated'));
        $this->redirect('this');
    }
    
    protected function createComponentFormTranslate($name){
        $form = new Form($this, $name);
        
        $form->addTextArea('translate', $this->translator->trans('translate.grid.translate'));
        $form->addSubmit('send', $this->translator->trans('translate.menu.translate'));
        
        $form->onSuccess[] = [$this, 'submitFormTranslate'];
        
        $translatesLocale = $this->row->related('translate_locale')->where('language_id', $this->webLanguage)->fetch();
        if($translatesLocale){
            $form->setDefaults(array(
                'translate' => $translatesLocale['translate'],
            ));
        }
        
        return $form;
    }

    public function actionTranslate($id){
        $this->exist($id);
        $this->template->translate = $this->row;
        $this->template->setFile(dirname(__FILE__) . '/../templates/Translate/translate.latte');
    }
    
    protected function exist($id){
        $this->row = $this->model->get($id);
        if(!$this->row){
            $this->flashMessage($this->translator->translate('admin.text.notitemNotExist'), 'error');
            $this->redirect('default');
        }
    }
    
    protected function createComponentGrid($name){
        $grid = new GridTranslate($this, $name);

        $grid->setModel($this->model->getAll());
        $grid->addColumn(new Column('text', $this->translator->translate('translate.text')));
        $grid->addColumn(new Translate('translate', $this->translator->translate('translate.grid.translate'), $this->webLanguage));
        $grid->addColumn(new Column('id', $this->translator->translate('admin.grid.id')));
        
        $grid->addMenu(new Update('translate', $this->translator->translate('translate.menu.translate')));
        
        $grid->setTemplateDir(dirname(__FILE__) . '/../templates/Translate');
        $grid->setTemplateFile('grid.latte');
        
        return $grid;
    }
}