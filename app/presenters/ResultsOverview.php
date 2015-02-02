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
        list($heading,$rocnik,$kolo,$kategorie)=
            $this->getURLData($this->getParam('path'));
         
        if(!$this->template->heading) {
            $this->template->heading="Výsledkové listiny ".($rocnik>0?$rocnik.". ročníku":"");
        }
        parent::renderDefault($path);
    }

    function getURLData($path){
        $heading="";
        $kolo=0;
        $kategorie=0;
        $rocnik=0;

	$arr=array(
            "domaci"  => "Domácí kolo",
            "obvodni" => "Obvodní kolo ",
            "krajske" => "Krajské kolo");
        $i=-1;
        foreach($arr as $urlname => $expname){
            $i++;
            if (stristr($path,$urlname)){ 
                $heading.=$expname." ";
//                $kolo=array_search($urlname,$arr)+1;
                $kolo=$i+1;
            }
        }

        $arr=array("a","b","c","d","e","f","g");
        foreach($arr as $kat){
            if (stristr($path,"kategorie-".$kat)){
                $heading.="kategorie&nbsp;".strtoupper($kat).", ";
                $kategorie=array_search($kat,$arr)+1;
            }
        }

        for($i=10;$i<100;$i++){
            if(stristr($path,$i."-rocnik")){ 
                $heading.=$i.".&nbsp;ročník";
                $rocnik=$i;
            }
        }
        return array($heading,$rocnik,$kolo,$kategorie);
    }

}
