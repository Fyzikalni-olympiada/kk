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
        $navigation->add('//www');
        $navigation->setOrder('position');
        return $navigation;
    }

    
    
    protected function createComponentMenu()
    {
        $navigation = new Navigation;
        $navigation->add(substr($this->getParam('path'),0,strpos($this->getParam('path'), '/', 6) ?: strlen($this->getParam('path')))); //skip  //www/
        $navigation->setOrder('position');
        return $navigation;
    }

}
