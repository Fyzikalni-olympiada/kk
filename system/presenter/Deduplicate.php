<?php
namespace Eywa\CMS\System\Presenters;

use Nette\Forms\Form;
use dibi;

/**
 * @menu (index=25, title=Deduplikace, icon=catalog)
 */

class Deduplicate extends Secured{
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
        if(!isset($_POST["tab_select"]))$_POST["tab_select"]="";
        $table=$_POST["tab_select"];
        if(!isset($_POST["ord_select"]))$_POST["ord_select"]="";
        $order=$_POST["ord_select"];
        if(!isset($_POST["whereclause"]))$_POST["whereclause"]="1";
        $whereclause=$_POST["whereclause"];

        $kr_select=1;//Praha auto


// Deduplicate
        if( $table != "" && $order != "") {
            $data=dibi::query("SELECT * FROM ".$table." WHERE 1 ORDER BY ".$order)->fetchAll();
            $keys = array_keys((array)($data[0]));
            $data=dibi::query("SELECT * FROM ".$table." WHERE 1 ORDER BY $order,$keys[0]")->fetchAll();
        }
// find merging indexes (check box)
        $idx=$idy=0;
        foreach(     $data as $rowx ) {
            $idx=$rowx[$keys[0]];
            if(isset($_POST['ADV'.$idx])){
                foreach( $data as $rowy ) {
                    $idy=$rowy[$keys[0]];
                    if(isset($_POST['ADV'.$idy])){
                        if( $idx != $idy )
                            break 2;
                    }
                }
            }
        }
        if($idx>$idy) {
            $tmp=$idx;
            $idx=$idy;
            $idy=$tmp;
        }
        // automatics
        $chnull = 1;
        $testonly = 0;
        if(isset($_POST["adeduplBTnulltest"]) || isset($_POST["adeduplBTnull"]))
            $chnull = 0;
        if(isset($_POST["adeduplBTtest"]) || isset($_POST["adeduplBTnulltest"]))
            $testonly = 1;
        if(isset($_POST["adeduplBTtest"]) || isset($_POST["adeduplBTnulltest"]) ||
           isset($_POST["adeduplBT"    ]) || isset($_POST["adeduplBTnull"    ])){
            foreach(     $data as $rowx ) {
                $idx=$rowx[$keys[0]];
                foreach( $data as $rowy ) {
                    $idy=$rowy[$keys[0]];
                    if( $idx >= $idy )
                        continue;
                    $diff = 0;
                    for($i=1;$i<count($keys); $i++){
                        if( $keys[$i] == "datetime" )
                            continue;
                        if(strval($rowx[$keys[$i]]) != strval($rowy[$keys[$i]]))
                            $diff++;
                        if( strval($rowx[$keys[$i]]) == "" && $chnull) {
                            $diff++;
                        }
                    }
                    if($diff==0) {
                        if( $testonly==1 )
                            $okMsg.="DEDUPL $idx->$idy<br />";
                        else
                            $okMsg .= $this::dedupl($table, $keys, $idx, $idy);
                    }
                }
            }
           }
        // checkboxes
        if($idx!=$idy) {
            // prepis odkazu
            if(isset($_POST["deduplBT"])){
                $okMsg .= $this::dedupl($table, $keys, $idx, $idy);
            }
            // mergování
            if(isset($_POST["mergeTH"])){
                $tmp=$idx;
                $idx=$idy;
                $idy=$tmp;
            }
            if(isset($_POST["mergeTL"]) || isset($_POST["mergeTH"]) ){
                $okMsg .= $this::merge($table, $keys, $idx, $idy);
            }
        }

// Load data
        if( $table != "" && $order != "") {
            $data=dibi::query("SELECT * FROM ".$table." WHERE 1 ORDER BY ".$order)->fetchAll();
            $keys = array_keys((array)($data[0]));
            $data=dibi::query("SELECT * FROM ".$table." WHERE $whereclause ORDER BY $order,$keys[0]")->fetchAll();
        }

// send to template
        $this->template->heading="Administrační pultík: ".$this->kraje[$kr_select];
        $this->template->data=$data;
        $this->template->keys=$keys;
        $this->template->tab_select=$table;
        $this->template->ord_select=$order;
        $this->template->whereclause=$whereclause;

        $this->template->okMsg=$okMsg;
        $this->template->errorMsg=$errMsg;
    }

    function dedupl($table, $keys, $idx, $idy) {
        if($idx==0 || $idy==0)
            return;
        $okMsg="";
        if($table=="referents") $rewr=array("registration");
        if($table=="persons")   $rewr=array("registration");

        foreach( $rewr as $rewrtab) {
            $query="
                UPDATE $rewrtab
                SET $keys[0]=$idx
                WHERE $keys[0] = $idy";
            dibi::query($query);
            $okMsg.="Přepsáno v $rewrtab: $idy->$idx<br />";
        }
        $query="
            DELETE FROM $table
            WHERE $keys[0]=$idy";
        dibi::query($query);
        $okMsg.="Smazáno z $table: $idy<br />";
        return $okMsg;
    }


    function merge($table, $keys, $idx, $idy) {
        $okMsg="";
        $query=
            "UPDATE $table t2,
            (
                SELECT *
                FROM $table
                WHERE $keys[0]=$idy
            ) t1
            SET ";
        //t2.f1 = t1.f1,t2.f2 = t1.f2
        $query.="t2.$keys[1]=t1.$keys[1]";
        for($i=2; $i<count($keys); $i++)
            $query.=",t2.$keys[$i]=t1.$keys[$i] ";
        $query.="WHERE t2.$keys[0] = $idx";
        dibi::query($query);
        $okMsg.="Mergováno: $idy->$idx";
        return $okMsg;
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

