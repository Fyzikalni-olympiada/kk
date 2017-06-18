<?php
namespace Eywa\CMS\System\Presenters;

use Nette\Forms\Form;
use dibi;

/**
 * @menu (index=16, title=Zveřejnění výsledkovky, icon=catalog)
 */
class Publish extends Secured{
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

        if(!isset($_GET["kr_select"]))$_GET["kr_select"]=0;
        $kr_select=$_GET["kr_select"];
        if($kr_select==null)$kr_select=null;
        if(!$this::isAllowed($kr_select)){
            $this->template->error="Neoprávněný přístup.";
            return;
        }

        if(!isset($_POST["kat_select"]))$_POST["kat_select"]="";
        $kat_select=$_POST["kat_select"];

        if(!isset($_POST["round_select"]))$_POST["round_select"]="";
        $round_select=$_POST["round_select"];

        $kr_select=1;//Praha auto

        if(isset($_POST["publishBT"])){
            $arr = [
                'kraj_id' => $kr_select,
                'rocnik'  => 58,
                'round'   => $round_select,
                'category'=> $kat_select,
                'publish_type_id' => 1
                ];
            try {
                dibi::query("INSERT INTO publish_res",$arr);
                $okMsg.="Výsledkovky zveřejněny.";
            }
            catch(Exception $e) {
                $errorMsg.="Nastala chyba ";
                $errorMsg.=$e->getMessage();
            }
        }
        $data=dibi::query('
            SELECT NULL poradi, concat(name,\' \',surname) jmeno,class,teacher, school_show_name,
                U1.points "U1" , U2.points "U2" , U3.points "U3" , U4.points "U4" , U5.points "U5" , U6.points "U6" , U7.points "U7" ,
                    SUM(sub.points) `sum`, NULL `mod`, NULL status,adv,born_date
                    FROM participants
                    JOIN referents USING(referent_id)
                    JOIN school USING(school_id)
                    JOIN advance USING(participant_id)
                    JOIN (SELECT * FROM submit WHERE prob_round=%i) AS sub USING(participant_id)
                    LEFT JOIN (SELECT DISTINCT participant_id, \'ano\' adv FROM advance WHERE round>1) AS adv USING(participant_id)
                    LEFT JOIN (SELECT participant_id,sub1.points FROM submit AS sub1 WHERE prob_round=%i AND problem_id=1) AS U1 USING(participant_id)
                    LEFT JOIN (SELECT participant_id,sub2.points FROM submit AS sub2 WHERE prob_round=%i AND problem_id=2) AS U2 USING(participant_id)
                    LEFT JOIN (SELECT participant_id,sub3.points FROM submit AS sub3 WHERE prob_round=%i AND problem_id=3) AS U3 USING(participant_id)
                    LEFT JOIN (SELECT participant_id,sub4.points FROM submit AS sub4 WHERE prob_round=%i AND problem_id=4) AS U4 USING(participant_id)
                    LEFT JOIN (SELECT participant_id,sub5.points FROM submit AS sub5 WHERE prob_round=%i AND problem_id=5) AS U5 USING(participant_id)
                    LEFT JOIN (SELECT participant_id,sub6.points FROM submit AS sub6 WHERE prob_round=%i AND problem_id=6) AS U6 USING(participant_id)
                    LEFT JOIN (SELECT participant_id,sub7.points FROM submit AS sub7 WHERE prob_round=%i AND problem_id=7) AS U7 USING(participant_id)
                    WHERE kraj_id=%i AND rocnik=%i AND category=%s AND advance.round=%i
                    GROUP BY participant_id
                    ORDER BY SUM(sub.points) DESC, surname DESC;',
                        $round_select,$round_select,$round_select,$round_select,
                        $round_select,$round_select,$round_select,$round_select,
                        $kr_select,58,$kat_select,$round_select)->fetchAll();
        foreach ($data as $student ) {
            if( $round_select == 1 ) {
                $usp = 0;
                if( $student['U1'] >= 5 ) $usp++;
                if( $student['U2'] >= 5 ) $usp++;
                if( $student['U3'] >= 5 ) $usp++;
                if( $student['U4'] >= 5 ) $usp++;
                if( $student['U5'] >= 5 ) $usp++;
                if( $student['U6'] >= 5 ) $usp++;
                if( $student['U7'] >= 5 ) $usp++;
                if( !($student['U6'] > 0) ) $usp=0;
                if( $usp>=5)
                    $student['status'] = 'úspěšný';
            }
            if( $round_select == 3 ) {
                $usp = 0;
                if( $student['U1'] >= 5 ) $usp++;
                if( $student['U2'] >= 5 ) $usp++;
                if( $student['U3'] >= 5 ) $usp++;
                if( $student['U4'] >= 5 ) $usp++;
                if( $usp>=2 && $student['sum']>=14 )
                    $student['status'] = 'úspěšný';
            }
        }

// send to template
        $this->template->heading="Administrační pultík: ".$this->kraje[$kr_select];
        $this->template->data=$data;
        $this->template->kat_select=$kat_select;
        $this->template->round_select=$round_select;

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

