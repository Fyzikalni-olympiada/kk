<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;



/**
 * @title Výsledková listina DB
 * @model Eywa\CMS\Model\Page
 */
class ResultsDatabase extends Base
{
    public function renderDefault($path)
    {
// TODO z URL zjistit parametry
        $path=$this->getParam('path');
        $heading="";
        if (stristr($path,"krajske")) $heading.="Krajské kolo ";
        if (stristr($path,"obvodni")) $heading.="Obvodní kolo ";
        if (stristr($path,"domaci"))  $heading.="Domácí kolo ";

        if (stristr($path,"kategorie-a"))  $heading.="kategorie A, ";
        if (stristr($path,"kategorie-b"))  $heading.="kategorie B, ";
        if (stristr($path,"kategorie-c"))  $heading.="kategorie C, ";
        if (stristr($path,"kategorie-d"))  $heading.="kategorie D, ";
        if (stristr($path,"kategorie-e"))  $heading.="kategorie E, ";

        for($i=10;$i<100;$i++) if (stristr($path,$i."-rocnik")) $heading.=$i.". ročník";

        $this->template->heading=$heading;
//"Krajské kolo kategorie A, 55. ročník";

// TODO vymyslet titulek
        $this->template->titleline="<td>pořadí<td>jméno<td>třída<td>učitel<td>škola<td>1<td>2<td>3<td>4<td>celkem<td><td>";
// TODO renderovat data (load z databáze)
    }
}
