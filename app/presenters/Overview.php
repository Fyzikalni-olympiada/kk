<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;



/**
 * @title Přehled podstránek
 */
class Overview extends Base
{

    public function renderDefault($path = '')
    {
        $this->template->list = $this->repository->getPages($this->getParam('path'), $this->orderBy(), NULL, $this->filter());
    }



    protected function orderBy()
    {
        return array('ctime', 'desc');
    }



    protected function filter()
    {
        return NULL;
    }

}