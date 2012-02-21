<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;



/**
 * @title Domácí stránka
 * @model Eywa\CMS\Model\Page
 */
class Homepage extends Base
{
    
    public function renderDefault()
    {
        $this['overview']->setPath('//www/novinky');
        $this->template->news = $this['overview']->getDocuments();
        
    }
}
