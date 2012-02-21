<?php
namespace Eywa\CMS\Presenters;



/**
 * @title Výsledková listina
 * @model Eywa\CMS\Model\Page
 */
class Results extends Base
{


    public function renderDefault()
    {
        $xlsData = $this->repository->getStorage()->getConnection()->send('GET', $this->document->resources[0]);
        $this->template->data = new \Spreadsheet_Excel_Reader($xlsData, TRUE, 'utf8');
        
    }

}
