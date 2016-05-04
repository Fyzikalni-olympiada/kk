<?php
namespace Eywa\CMS\System\Presenters;

use Nette\Forms\Form;
use dibi;

//TODO auto rocnik

/**
 * @menu (index=12, title=Příjem řešení, icon=catalog)
 */
class Submit extends Secured{
    private $kraje=array();

    public function __construct(){
        $arr=dibi::query("SELECT kraj_name,kraj_id FROM kraj WHERE 1")
            ->fetchAll();
        foreach($arr as $kr)
            $this->kraje[$kr["kraj_id"]]=$kr["kraj_name"];
        $this->kraje[null]="";
    }

    function renderDefault(){
        bdump($_POST);
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
        if(!isset($_POST["round_select"]))$_POST["round_select"]="0";
        $round_select=$_POST["round_select"];

        $kr_select=1;//Praha auto
        $rocnik=57;

bdump($round_select);

        if(isset($_POST["saveBT"])){
            for($i=0; $i<$_POST["STOT"];$i++)for($j=1;$j<=7;$j++)if($_POST['submit'.$i][$j]=="1"){
                $val=array(
                    "participant_id" => $_POST["PID".$i],
                    "prob_round"     => $round_select,
                    "problem_id"     => $j,
                    "submit_add"     => NULL
                );
                dibi::insert('submit',$val)->execute();
            }
            $okMsg.="Odevzdání uloženo.";
        }
        $students=dibi::query('SELECT participant_id, CONCAT(name, " ", surname) AS fullname
                           FROM participants
                           JOIN referents USING(referent_id)
                           JOIN advance USING(participant_id)
                           WHERE rocnik=%s AND round=%s
                           AND participant_id NOT IN (
                               SELECT participant_id FROM submit WHERE prob_round=%s)
                           ORDER BY surname
                           ',$rocnik,$round_select,$round_select)->fetchAll();
        $query="SELECT concat(name,' ',surname) AS fullname";
        for($i=1;$i<=7;$i++)
              $query.=', U'.$i.'.submit "U'.$i.'" ';
        $query.="
            FROM participants
            JOIN referents USING(referent_id)
            JOIN school USING(school_id)
            JOIN advance USING(participant_id)";
        for($i=1;$i<=7;$i++)
            $query.="LEFT JOIN (SELECT participant_id,'X' AS submit
                           FROM submit AS sub".$i."
                           WHERE prob_round=".$round_select." AND problem_id=".$i.") AS U".$i."
                     USING(participant_id)";
        $query.="WHERE kraj_id=1
              AND rocnik=".$rocnik."
              AND category='".chr(64+$kategorie)."'
              AND advance.round=".$round_select." AND (";
        for($i=1;$i<=7;$i++)
            $query.="NOT ISNULL(U$i.submit) OR ";
        $query.=" 0)";
        $query.=" ORDER BY surname;";
        $submitted=dibi::query($query)->fetchAll();
//        $submitted=NULL;
// send to template
        $this->template->heading="Administrační pultík: ".$this->kraje[$kr_select];
        $this->template->students=$students;
        $this->template->kat_select=$kategorie;
        $this->template->round_select=$round_select;
        $this->template->submitted=$submitted;

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

