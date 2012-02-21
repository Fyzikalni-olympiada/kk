<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;



abstract class Base extends Presenter
{


    public function beforeRender()
    {
        parent::beforeRender();
        $this['navigation']->setCurrent($this->getParam('path'));
        $this->setLayout('layout2Col');
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
        $path = $this->getParam('path');
        $navigation->add(substr($path, 0, strpos($path, '/', 6) ?: strlen($path))); //skip  //www/
        $navigation->setOrder('position');
        return $navigation;
    }

}
