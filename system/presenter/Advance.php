<?php
namespace Eywa\CMS\System\Presenters;

use Nette\Forms\Form;
use dibi;

//TODO auto rocnik

/**
 * @menu (index=14, title=Schválení postupu, icon=catalog)
 */
class Advance extends Secured{
    private $kraje=array();

    public function __construct(){
        $arr=dibi::query("SELECT kraj_name,kraj_id FROM kraj WHERE 1")
            ->fetchAll();
        foreach($arr as $kr)
            $this->kraje[$kr["kraj_id"]]=$kr["kraj_name"];
        $this->kraje[null]="";
    }

    function renderDefault(){
        $okMsg="";
        $errMsg="";
        $values=array();

        if(!isset($_GET["kr_select"]))$_GET["kr_select"]=0;
        $kr_select=$_GET["kr_select"];
        if($kr_select==null)$kr_select=null;
        if(!$this::isAllowed($kr_select)){
            $this->template->error="Neoprávněný přístup.";
            return;
        }
        if(!isset($_POST["kat_select"]))$_POST["kat_select"]="";
        $kategorie=$_POST["kat_select"];

        $kr_select=1;//Praha auto
        $from_kolo=1;
        $to_kolo=3;
        $rocnik=58;

        if(isset($_POST["saveBT"])){
            for($i=0; $i<$_POST["STOT"];$i++)if(isset($_POST["ADV".$i]))if($_POST["ADV".$i]==1){
                $val=array(
                    "participant_id" => $_POST["PID".$i],
                    "round"          => $to_kolo
                );
                dibi::insert('advance',$val)->execute();
/*
                for($j=1; $j<=4; $j++){
                    $val=array(
                        "participant_id" => $_POST["PID".$i],
                        "prob_round"     => $to_kolo,
                        "problem_id"     => $j
                    );
                    dibi::insert('submit',$val)->execute();
                }
 */
                $values[]=dibi::query("SELECT CONCAT(name,' ',surname) AS fullname FROM advance
                                           JOIN participants USING(participant_id)
                                           WHERE round=%s AND participant_id=%s;",$to_kolo,$_POST["PID".$i])->fetchAll()[0];
            }
            $okMsg.="Postup uložen.";
        }

        $query="SELECT participant_id, concat(surname,' ',name) AS fullname, toR, NULL AS advR";
        for($i=1;$i<=7;$i++)
              $query.=", U".$i.".points \"U".$i."\" ";
        $query.=", SUM(sub.points) `sum`";
        $query.="
            FROM participants
            JOIN referents       USING(referent_id)
            JOIN school          USING(school_id)
            JOIN advance AS advF USING(participant_id)
            JOIN submit  AS sub  USING(participant_id)
            LEFT JOIN (SELECT participant_id ,round  AS toR
                           FROM advance
                           WHERE round!=".$from_kolo."
                      ) AS advT
                      USING(participant_id)";
        for($i=1;$i<=7;$i++)
            $query.="LEFT JOIN (SELECT participant_id,sub".$i.".points
                           FROM submit AS sub".$i."
                           WHERE prob_round=".$from_kolo." AND problem_id=".$i.") AS U".$i."
                     USING(participant_id)";
        $query.="WHERE kraj_id=1
              AND rocnik=".$rocnik."
              AND category='".chr(64+$kategorie)."'
              AND advF.round=".$from_kolo."
              AND ISNULL(advT.toR)
            GROUP BY participant_id
            ORDER BY SUM(sub.points) DESC;";

        $data=dibi::query($query)->fetchAll();
        foreach($data as $row){
            $pass=0;
            for($i=1; $i<=7; $i++) if($row["U".$i]>=5)$pass++;
            if($pass>=5) $row->advR=true;
        }
// send to template
        $this->template->heading="Administrační pultík: ".$this->kraje[$kr_select];
        $this->template->data=$data;
        $this->template->kat_select=$kategorie;
        $this->template->values=$values;

        $this->template->okMsg=$okMsg;
        $this->template->errorMsg=$errMsg;
    }

    function createComponentKrajSelect($name){
        $form = new Form;
        $form->setMethod('GET');
        $form->setAction("/system/points/");
        $form->addSelect("kr_select","Kraje:",$this->kraje)
             ->skipFirst('Vyberte kraj');
        $form->addSubmit('send', 'Vybrat');
        return $form;
    }
    function isAllowed($kr_id){
        //TODO ověření práva přístupu
        return true;
	if($kr_id<1)return true;return false;
    }
}

