<?php
namespace Eywa\CMS\System\Presenters;

use Nette\Forms\Form;
use dibi;
use Nette\Environment;

/**
 * @menu (index=15, title=Neopravená řešení, icon=catalog)
 */
class Ncorr extends Secured{
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
        $kr_select=1;//Praha auto

        $students=dibi::query(
            'SELECT *
             FROM `v_neopravene`
             WHERE `kraj_id`=%i
             ORDER BY rocnik,prob_round,category,problem_id,school_show_name',$kr_select)->fetchAll();
// send to template
        $this->template->heading="Administrační pultík: ".$this->kraje[$kr_select];
        $this->template->students=$students;

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

