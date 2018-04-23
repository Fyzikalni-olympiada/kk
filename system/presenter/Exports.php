<?php
namespace Eywa\CMS\System\Presenters;

use Nette\Forms\Form;
use dibi;
use Nette\Environment;

/**
 * @menu (index=26, title=Exporty, icon=catalog)
 */
class Exports extends Secured{
    private $kraje=array();

    public function __construct(){
        $arr=dibi::query("SELECT kraj_name,kraj_id FROM kraj WHERE 1")
            ->fetchAll();
        foreach($arr as $kr)
            $this->kraje[$kr["kraj_id"]]=$kr["kraj_name"];
        $this->kraje[null]="";
    }

    function renderDefault(){
//        bdump( $_POST );
        $rocnik=Environment::getVariable('rocnik');
        $okMsg="";
        $errMsg="";
        $inputFileds = array();

        $exports = dibi::query("SELECT * FROM exports WHERE 1;")->fetchAll();

        if(!isset($_GET["kr_select"]))$_GET["kr_select"]=0;
        $kr_select=$_GET["kr_select"];
        if($kr_select==null)$kr_select=null;
        if(!$this::isAllowed($kr_select)){
            $this->template->error="Neoprávněný přístup.";
            return;
        }

        if(!isset($_POST["exp_select"]))$_POST["exp_select"]=-1;
        //$exp_select=$exports[$_POST["exp_select"]]->export_id;
        $exp_select=$_POST["exp_select"];

        $kr_select=1;//Praha auto

        if(isset($_POST["selectBT"])){
            $okMsg.="Export " . $exports[$exp_select]->comment  . " vybrán.<br />";
        }
        if( $exp_select >= 0 ){
            $sql = $exports[$exp_select]->sql_query;
            //$sql = $exports["export_id"==$exp_select]->sql_query;
            // find fields
            preg_match_all("/(?<=X)[^X]+(?=X)/", $sql, $matches);
            bdump( $sql );

            $matches = $matches[0];
            $inputFileds = array();
            for ( $i=0; $i < count($matches); $i+=2 )
                array_push( $inputFileds, $matches[$i] );
            $inputFileds = array_unique( $inputFileds, SORT_STRING );
            sort( $inputFileds );
            bdump( $inputFileds );
        }
        if(isset($_POST["runBT"]) || isset($_POST["checkBT"])){
            $okMsg.="Export " . $exports[$exp_select]->comment  . " spusten.<br />";
            foreach( $inputFileds as $match ){
                $_POST["param_".$match] =
                    preg_replace('/[\W]/', '',$_POST["param_".$match]);
                $okMsg.="   Param: " . $match . " = ". $_POST["param_".$match] ."<br />";
                $this->template->params[$match] = $_POST["param_".$match];
                $sql = preg_replace("/X".$match."X/",
                        '"'.$_POST["param_".$match].'"',$sql);
            }
            if(isset($_POST["runBT"])) {
                $data = dibi::query( $sql )->fetchAll();
                //$out = fopen('/web/doc/test.csv', 'w');
                $out = fopen('/tmp/test.csv', 'w');
                //bdump( $data );
                //fputcsv( $out, $data );
                fclose($out);
                $keys = array_keys((array)($data[0]));
                $this->template->data=$data;
                $this->template->keys=$keys;
            }
        }
        // prefill
        foreach( $inputFileds as $match ){
            if( $match === "rocnik_FO" && $this->template->params[$match] == "" )
                $this->template->params[$match] = $rocnik;
            if( $match === "kraj_id" && $this->template->params[$match] == "" )
                $this->template->params[$match] = $kr_select;
            if( $match === "postup_do_kola" && $this->template->params[$match] == "" )
                $this->template->params[$match] = 3;
        }
// send to template
        $this->template->heading="Administrační pultík: ".$this->kraje[$kr_select];
        $this->template->exports=$exports;
        $this->template->exp_select=$exp_select;
        $this->template->sql=$sql;
        $this->template->inputFileds=$inputFileds;

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

