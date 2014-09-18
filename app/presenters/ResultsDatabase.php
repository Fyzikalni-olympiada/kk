<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;
use dibi;

/**
 * @title Výsledková listina DB
 * @model Eywa\CMS\Model\Page
 */
class ResultsDatabase extends Base
{
    public function renderDefault($path)
    {
// nacist data z URL
        list($heading,$rocnik,$kolo,$kategorie)=
            $this->getURLData($this->getParam('path'));
        //$heading.=" r ".$rocnik." ko ".$kolo." ka ".$kategorie;
// page title
        $this->template->heading=$heading;
// comment
        if($this->template->document->html == null){
            if($kolo==1) $this->template->document->html=
                "<p>Pro postup do dalšího kola ". 
                "bylo třeba dosáhnout hodnocení alespoň 5&nbsp;bodů ".
                "v&nbsp;alespoň pěti úlohách a&nbsp;odevzdat ".
                "seriózně experimentální úlohu.</p>";
            if($kolo==3) $this->template->document->html=
                "<p>K&nbsp;úspěchu v&nbsp;krajském kole je třeba mít ".
                "alespoň 14&nbsp;bodů a&nbsp;dvě úlohy hodnocené ".
                "alespoň 5&nbsp;body.";
        }
// TODO několik sekcí s tabulkami pro OK E mozna ne
        $this->template->add("section","");
        //$this->template->section= new stdClass();
        $titleline=$this->getTitleLine($rocnik,$kolo,$kategorie);

        $this->template->section["postup"]->name="Postupující do krajského kola";
        $this->template->section["postup"]->titleline=$titleline;
        if($kategorie==5 && $kolo==2){
            $this->template->section["usp"]->name="Další úspěšní řešitelé";
            $this->template->section["neusp"]->name="Další řešitelé";
            $this->template->section["usp"]->titleline=$titleline;
            $this->template->section["neusp"]->titleline=$titleline;
       }
// TODO renderovat data (load z databáze)
// TODO getKrajId()
        $query="SELECT NULL poradi, 
              concat(surname,' ',name) jmeno,class,teacher,
              school_show_name";
        for($i=1;$i<=7;$i++)
              $query.=", U".$i.".points \"U".$i."\" ";
        $query.=", SUM(sub.points) `sum`, NULL `mod`,
                 NULL status,adv,born_date";
        $query.="
            FROM participants
            JOIN referents     USING(referent_id) 
            JOIN school        USING(school_id)
            JOIN advance       USING(participant_id)
            JOIN submit AS sub USING(participant_id)
            LEFT JOIN (SELECT DISTINCT participant_id, 
                   '<strong>ano</strong>' adv 
               FROM advance 
               WHERE round>".$kolo.") AS adv 
            USING(participant_id)";
        for($i=1;$i<=7;$i++)
            $query.="LEFT JOIN (SELECT participant_id,sub".$i.".points 
                           FROM submit AS sub".$i." 
                           WHERE round=".$kolo." AND problem_id=".$i.") AS U".$i." 
                     USING(participant_id)";
        $query.="WHERE kraj_id=1 
              AND rocnik=".$rocnik." 
              AND category='".chr(64+$kategorie)."'
              AND advance.round=".$kolo."
            GROUP BY participant_id
            ORDER BY SUM(sub.points);";
        //die($query);

        $data=dibi::query($query)->fetchAll();
// mod points calculate
// TODO resort data + fill status
        $table="";
        foreach($data as $row){
            unset($row->born_date);
            if($rocnik<54) unset($row->mod);
            if($kolo!=1)   unset($row->adv);
            if($kolo!=1)for($i=5;$i<=7;$i++) unset($row["U".$i]);
            $table.="<tr>";
            foreach($row as $col){
                $table.="<td>".$col."</td>";
            }
            $table.="</tr>\n";
        }
        $this->template->section["postup"]->table=$table;
    }

    function getTitleLine($rocnik,$kolo,$kategorie){
        $titleline="";
        foreach(array("Pořadí","Jméno","Třída","Učitel","Škola") as $item)
            $titleline.="<td>".$item."</td>";
        for($i=1;$i<=($kolo==1?7:4);$i++)
            $titleline.="<td>Ú".$i."</td>";
        $titleline.="<td>Celkem</td>";
        if($rocnik>=54 && $kolo>1)
            $titleline.="<td>Mod. body</td>";
        $titleline.="<td></td>";// úsp/neúsp
        if($kolo==1 || ($kolo==2 && $kategorie==5))
            $titleline.="<td>Postup</td>";
        return $titleline;
    }

    function getURLData($path){
        $heading="";

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
