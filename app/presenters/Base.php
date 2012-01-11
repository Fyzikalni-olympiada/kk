<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;



abstract class Base extends Presenter
{


    public function beforeRender()
    {
        parent::beforeRender();
        $this['navigation']->setCurrent($this->getParam('path'));
    }



    protected function createComponentNavigation()
    {
        $navigation = new Navigation;
        $navigation->setRoot('//www');
        $navigation->add('//www');
        $navigation->setOrder('position');
        return $navigation;
    }


}
