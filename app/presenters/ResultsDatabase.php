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
    public $KATEGORY=array("1"=>"A","2"=>"B","3"=>"C","4"=>"D","5"=>"E","6"=>"F","7"=>"G");
    public function renderDefault($path)
    {
// nacist data z URL
        list($heading,$rocnik,$kolo,$kategorie)=
            $this->getURLData($this->getParam('path'));
// page title
        $this->template->heading=$heading;
// comment
        $stdtitle="";// text pod nadpisem
        if($kolo==1) $stdtitle=
            "<p>Pro postup do dalšího kola ".
            "bylo třeba dosáhnout hodnocení alespoň 5&nbsp;bodů ".
            "v&nbsp;alespoň pěti úlohách a&nbsp;odevzdat ".
            "seriózně experimentální úlohu.</p>";
        if($kolo==3) $stdtitle=
            "<p>K&nbsp;úspěchu v&nbsp;krajském kole je třeba mít ".
            "alespoň 14&nbsp;bodů a&nbsp;dvě úlohy hodnocené ".
            "alespoň 5&nbsp;body.";
        $qr=dibi::query("
            SELECT publish_type_id,title_line FROM publish_res
            WHERE kraj_id=%i AND rocnik=%i
            AND round=%i AND category=%s;",
            /*kraj_id*/1,$rocnik,$kolo,$this->KATEGORY[$kategorie])->fetch();
        $publish=$qr["publish_type_id"];
        $title_line=$qr["title_line"];

        if($title_line!=null) $stdtitle=$title_line;
        if($publish==null){ // nema se publikovat
            $html=$this->template->document->html;
            if($html==null || $html=="")
                $stdtitle="<p>Výsledková listina se připravuje.</p>";
            else
                $stdtitle="";
        }
        else{
            if($publish === 1){ // databáze
            }
            if($publish === 2){ // html
            }
            if($publish === 3){ // excel
                $this->setLayout('layout');
                $xlsData = $this->repository->getStorage()->getConnection()->send('GET', $this->document->resources[0]);
                if(is_string($xlsData)) // soubor existuje!!
                    $this->template->data = new \Spreadsheet_Excel_Reader($xlsData, TRUE, 'utf8');
                else
                    $this->template->emptyXls=true;
            }
        }
// html
//  * zpráva null -> std+html
//  * jinak -> zpráva+html (input)
// databaze
//  * zprava null -> std
//  * zprava -> zprava
//  + databazova tabulka
// Excel
//  * zprava null -> std
//  * zprava -> zprava
//  + excel input

// null
//  * html==null -> Výsledková listina se připravuje.
//  * html!=null -> html
//
// std:
//  * vyřešit účastníka a řešitele... -> v html (není potřeba do té doby než se bude migrovat
//                                       do databáze zpětně
//  * vyřešit modifikované body

        $this->template->html = $stdtitle;
//$this->template->document->html;
//        $this->template->document->html = $stdtitle.$this->template->document->html;
        $this->template->publish=$publish;
// TODO několik sekcí s tabulkami pro OK E mozna ne
        $this->template->add("section","");
        //$this->template->section= new stdClass();
        $titleline=$this->getTitleLine($rocnik,$kolo,$kategorie);

        $this->template->section["postup"] = new \stdClass();
        $this->template->section["postup"]->name = "Postupující do krajského kola";
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
              concat(name,' ',surname) jmeno,class,teacher,
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
            JOIN (SELECT * FROM submit WHERE prob_round=".$kolo.") AS sub USING(participant_id)
            LEFT JOIN (SELECT DISTINCT participant_id,
                   '<strong>ano</strong>' adv
               FROM advance
               WHERE round>".$kolo.") AS adv
            USING(participant_id)";
        for($i=1;$i<=7;$i++)
            $query.="LEFT JOIN (SELECT participant_id,sub".$i.".points
                           FROM submit AS sub".$i."
                           WHERE prob_round=".$kolo." AND problem_id=".$i.") AS U".$i."
                     USING(participant_id)";
        $query.="WHERE kraj_id=1
              AND rocnik=".$rocnik."
              AND category='".chr(64+$kategorie)."'
              AND advance.round=".$kolo."
            GROUP BY participant_id
            ORDER BY SUM(sub.points) DESC, born_date ASC;";
        //die($query);

        $data=dibi::query($query)->fetchAll();
// mod points calculate
        foreach($data as $row){
            $row->mod==0;
        }
        for($i=1; $i<=($kolo==1?7:4); $i++){
            $sum=0;$cnt=0;
            foreach($data as $row){
                $sum+=$row["U".$i];
                $cnt++;;
            }
            foreach($data as $row){
                $row->mod+=$row["U".$i]*(10-$sum/$cnt);
            }
        }
// TODO fill status
       if($kolo>1)foreach($data as $row){
           $okUl=0;
           for($i=1;$i<=4;$i++)if($row["U".$i]>=5)$okUl++;
           if($okUl>=2 && $row->sum>=14)
               $row->status="uspěšný řešitel";
       }
// TODO resort data

        usort($data,'Eywa\CMS\Presenters\ResultsDatabase::finalSort');
// poradi
       $poradi=1;
       foreach($data as $row){
           $row->poradi=$poradi++.".";
       }
// prepare data
        $table="";
        foreach($data as $row){
            unset($row->born_date);
            if($rocnik<54) unset($row->mod);
            if($kolo!=1)   unset($row->adv);
            if($kolo!=1)for($i=5;$i<=7;$i++) unset($row["U".$i]);
            $row->mod=number_format( $row->mod, 2,',','');
            for($i=1;$i<=($kolo==1?7:4);$i++)
                $row["U".$i]=str_replace('.',',',$row["U".$i]);
            $row->sum=str_replace('.',',',$row->sum);
            $table.="<tr style=\"padding:1em 2px;\">";
            foreach($row as $col){
                $table.="<td style=\"padding:.5em 2px;\">".$col."</td>";
            }
            $table.="</tr>\n";
        }
        $this->template->section["postup"]->table=$table;
    }
    public static function finalSort($a, $b)
    {
        if( $a['status']=='' && $b['status']!='') return  1;
        if( $a['status']!='' && $b['status']=='') return -1;
        if( $a['sum'] <  $b['sum']) return  1;
        if( $a['sum'] >  $b['sum']) return -1;
        if( $a['mod'] <  $b['mod']) return  1;
        if( $a['mod'] >  $b['mod']) return -1;
        return 0;
    }

    function getTitleLine($rocnik,$kolo,$kategorie){
        $titleline="";
        foreach(array("","Jméno","Třída","Vyučující fyziky","Škola") as $item)
            $titleline.="<th style=\"padding:1em 2px;\">".$item."</th>";
        for($i=1;$i<=($kolo==1?7:4);$i++)
            $titleline.="<th style=\"width:2em;padding:2px;\">Ú".$i."</th>";
        $titleline.="<th style=\"padding:2px\">Celkem</th>";
        if($rocnik>=54)
            $titleline.="<th style=\"padding:2px\">Mod. body</th>";
        $titleline.="<th></th>";// úsp/neúsp
        if($kolo==1 || ($kolo==2 && $kategorie==5))
            $titleline.="<th style=\"padding:2px\">Postup</th>";
        return $titleline;
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
