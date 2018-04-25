<?php
namespace Eywa\CMS\System\Presenters;

use Nette\Forms\Form;
use dibi;
use Nette\Environment;

/**
 * @menu (index=11, title=Bodování, icon=catalog)
 */
class Points extends Secured{
    private $kraje=array();

    public function __construct(){
        $arr=dibi::query("SELECT kraj_name,kraj_id FROM kraj WHERE 1")
            ->fetchAll();
        foreach($arr as $kr)
            $this->kraje[$kr["kraj_id"]]=$kr["kraj_name"];
        $this->kraje[null]="";
    }

    function renderDefault(){
        $rocnik=Environment::getVariable('rocnik');
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

        if(!isset($_POST["pr_select"]))$_POST["pr_select"]=0;
        $pr_select=$_POST["pr_select"];

        if(!isset($_POST["kat_select"]))$_POST["kat_select"]="";
        $kat_select=$_POST["kat_select"];

        if(!isset($_POST["round_select"]))$_POST["round_select"]="";
        $round_select=$_POST["round_select"];

        $kr_select=1;//Praha auto

        if(isset($_POST["resetBT"])){
            $pr_select=0;
            $okMsg.="Nic nebylo uděláno.";
        }
        if(isset($_POST["saveBT"])){
            for($i=0; $i<$_POST["STOT"];$i++){
                if($_POST["SDATAOLD".$i]!=$_POST["SDATA".$i]){
                    //$okMsg.=$i." ";
                    $_POST["SDATA".$i]=str_replace(",",".",$_POST["SDATA".$i]);
                    if($_POST["SDATA".$i]<0 || $_POST["SDATA".$i]>10) continue;
                    if($_POST["SDATA".$i]=="")
                        dibi::query("UPDATE submit SET points=NULL,user=%s WHERE submit_id=%s;",$this->template->user->name,$_POST["SID".$i]);
                    else
                        dibi::query("UPDATE submit SET points=%s,user=%s WHERE submit_id=%s;",$_POST["SDATA".$i],$this->template->user->name,$_POST["SID".$i]);
                    $values[]=dibi::query("SELECT CONCAT(name,' ',surname) AS fullname,problem_id,points FROM submit
                                           JOIN participants USING(participant_id)
                                           WHERE submit_id=%s;",$_POST["SID".$i])->fetchAll()[0];
                }
            }
            $okMsg.="Body uloženy.";
        }

// bodovani
        $data=dibi::query("SELECT submit_id AS s_id,CONCAT(name,' ',surname) AS fullname, points, school_show_name, class
                              FROM submit
                              JOIN (SELECT participant_id FROM advance WHERE round=%s) AS adv USING(participant_id)
                              JOIN participants USING(participant_id)
                              JOIN referents USING(referent_id)
                              JOIN school USING(school_id)
                              wHERE category=%s AND problem_id=%s AND prob_round=%s AND rocnik=%i
                              ORDER BY surname",
                              $round_select,$kat_select,$pr_select,$round_select,$rocnik)->fetchAll();

// send to template
        $this->template->heading="Administrační pultík: ".$this->kraje[$kr_select];
        $this->template->data=$data;
        $this->template->pr_select=$pr_select;
        $this->template->kat_select=$kat_select;
        $this->template->round_select=$round_select;
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

