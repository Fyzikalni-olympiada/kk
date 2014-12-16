<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;



/**
 * @title Aktuality
 * @model Eywa\CMS\Model\Page
 */
class News extends Overview
{
    public function renderDefault($path)
    {
        $this['overview']->setPath($this->getSubdomain() . '/aktualni-rocnik/novinky-dir');
        parent::renderDefault($path);
    }
}
