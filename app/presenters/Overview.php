<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;



/**
 * @title Přehled podstránek
 * @model Eywa\CMS\Model\Page
 */
class Overview extends Base
{

    public function renderDefault($path)
    {
        $this['overview']->setClass('\Eywa\CMS\Model\Presentable');
        $this->template->list = $this['overview']->getDocuments();
    }

}
