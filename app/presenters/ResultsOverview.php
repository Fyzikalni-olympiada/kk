<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;



/**
 * @title Přehled výsledků
 * @model Eywa\CMS\Model\Page
 */
class ResultsOverview extends Overview
{
    public function renderDefault($path)
    {
        $this->template->list = $this['overview']->getDocuments();
//        parent::renderDefault($path);
    }
}
