<?php

namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;
use dibi;

/**
 * @title Přihláška
 * @model Eywa\CMS\Model\Page
 */

// TODO kontrola na konci znovu (vyclenit do funkce)

// TODO emaily

// TODO thead+tbody

class Application extends Base
{
    public function renderDefault($path)
    {
bdump($_POST);
        $step_names=array(
            1    => "Základní údaje o garantovi",
           "1.5" => "Údaje o škole", 
            2    => "Údaje o řešitelích",
            3    => "Rekapitulace",
            4    => "Hotovo");

        $errorMsg="";
        $okMsg="";
        $refSchoolName=NULL;

        if(isset($_POST['step']))     $step     =$_POST['step'];     else $step     =1;
        if(isset($_POST['refSchool']))$refSchool=$_POST['refSchool'];else $refSchool=NULL;
        if(isset($_POST['refName']))  $refName  =$_POST['refName'];  else $refName  ="";
        if(isset($_POST['refMail']))  $refMail  =$_POST['refMail'];  else $refMail  ="@";
        if(isset($_POST['refPhone'])) $refPhone =$_POST['refPhone']; else $refPhone ="";
        if(isset($_POST['refNum']))   $refNum   =$_POST['refNum'];   else $refNum   ="";
        if(isset($_POST['rekapE']))   $rekap    =$_POST['rekapE'];   else $rekap    =false;

        if(isset($_POST['schName']))  $schName  =$_POST['schName'];  else $schName  ="";
        if(isset($_POST['schAdr']))   $schAdr   =$_POST['schAdr'];   else $schAdr   ="";

        $stdName=array();
        $stdSur =array();
        $stdKat =array();
        $stdMail=array();
        $stdBorn=array();
        $stdClss=array();
        $stdTeac=array();

        for($i=0; $i<$refNum; $i++){
            if(isset($_POST['stdName'][$i]))$stdName[$i]=$_POST['stdName'][$i];else $stdName[$i]="";
            if(isset($_POST['stdSur' ][$i]))$stdSur [$i]=$_POST['stdSur' ][$i];else $stdSur [$i] ="";
            if(isset($_POST['stdKat' ][$i]))$stdKat [$i]=$_POST['stdKat' ][$i];else $stdKat [$i]="";
            if(isset($_POST['stdMail'][$i]))$stdMail[$i]=$_POST['stdMail'][$i];else $stdMail[$i]="@";
            if(isset($_POST['stdBorn'][$i]))$stdBorn[$i]=$_POST['stdBorn'][$i];else $stdBorn[$i]="";
            if(isset($_POST['stdClss'][$i]))$stdClss[$i]=$_POST['stdClss'][$i];else $stdClss[$i]="";
            if(isset($_POST['stdTeac'][$i]))$stdTeac[$i]=$_POST['stdTeac'][$i];else $stdTeac[$i]="";
        }

        if($refSchool=="NULL")$refSchool=NULL;

// ok press
        if(isset($_POST['garantOk'])){
            $errorMsg.=$this->checkReferent($refSchool,$refName,$refMail,$refNum);
            if($errorMsg==""){
                if($rekap){
                    $step=3;
                }
                else{
                    $step=2;
                    $stdName=array_fill(0,$refNum,"");
                    $stdSur =array_fill(0,$refNum,"");
                    $stdKat =array_fill(0,$refNum,"");
                    $stdMail=array_fill(0,$refNum,"@");
                    $stdBorn=array_fill(0,$refNum,"");
                    $stdClss=array_fill(0,$refNum,"");
                    $stdTeac=array_fill(0,$refNum,"");
                }
            }
            else{
//                $errorMsg.="<br /> V případě přetrvávajících potíží nás kontaktujte na emailové adrese 
//                    <a href='mailto:praha@fyzikalniolympiada.cz'>praha@fyzikalniolympiada.cz</a>.";
           }
        }
// add school press
        if(isset($_POST['missschool'])){
            //$okMsg.="Add school pressed<br />";
            $step="1.5";
        }
// cancel school
        if(isset($_POST['schCanc'])){
            $step=1;
        }

// save new school
        if(isset($_POST['schSave'])){
            $errorMsg.=$this->checkSchool($schName,$schAdr);
            if($errorMsg==""){
                if(dibi::query('SELECT * from school WHERE school_show_name=%s;',$schName)->fetchAll()[0]==NULL){
                    $data=array(
                        "kraj_id"          => $this::getKrajId(),
                        "school_show_name" => $schName,
                        "school_choice"    => $schName,
                        "school_address"   => $schAdr);
                    dibi::insert('school',$data)->execute();
                    $okMsg.="Škola byla uložena do databáze.<br />";
                }
                else{
                    $okMsg.="Byla použita škola stejného jména z databáze.<br />";
                }
                $refSchool=dibi::query('SELECT school_id FROM school WHERE school_show_name=%s',$schName)->fetchAll()[0]->school_id;

                $step="1";
            }
        }

// load refSchool from form
        $schools =$this::getSchools();
        foreach($schools as $school)if($refSchool==$school->school_id)
            $refSchool=$school->school_id;
        if($refSchool!=NULL)foreach($schools as $school)if($school->school_id==$refSchool){
            $school->selected=true;
            $refSchoolName=$school->school_choice;
        }


// continue to rekapitulation
        if(isset($_POST['rekap'])){
            $errorMsg.=$this->checkStudents($refNum,$refMail,$stdName,$stdSur,$stdKat,$stdMail,$stdBorn,$stdClss,$stdTeac);
            if($errorMsg==""){
                $step=3;
            }
            else{
                $errorMsg.="<br /> V případě přetrvávajících potíží nás kontaktujte na emailové adrese 
                    <a href='mailto:praha@fyzikalniolympiada.cz'>praha@fyzikalniolympiada.cz</a>.";
            }
        }

// delete button
        for($i=0; $i<$refNum; $i++)if(isset($_POST['stdRm'][$i])){
            $okMsg.="Řádek ".($i+1)." smazán.<br />";
            $refNum--;
            for($j=$i;$j<$refNum; $j++){
                $stdName[$j]=$stdName[$j+1];
                $stdSur [$j]=$stdSur [$j+1];
                $stdKat [$j]=$stdKat [$j+1];
                $stdMail[$j]=$stdMail[$j+1];
                $stdBorn[$j]=$stdBorn[$j+1];
                $stdClss[$j]=$stdClss[$j+1];
                $stdTeac[$j]=$stdTeac[$j+1];
            }
        }
// add button
        if(isset($_POST['stdAdd'])){
            $stdName[$refNum]="";
            $stdSur [$refNum]="";
            $stdKat [$refNum]="";
            $stdMail[$refNum]="@";
            $stdBorn[$refNum]="";
            $stdClss[$refNum]="";
            $stdTeac[$refNum]="";
            $refNum++;
            $okMsg.="Řádek ".$refNum." přidán.<br />";
        }

// edit garant
        if(isset($_POST['edit_garant'])){
            $rekap=true;
            $step=1;
        }
// edit students
        if(isset($_POST['edit_resitele'])){
            $rekap=true;
            $step=2;
        }

// submit
        if(isset($_POST['submit'])){
            dibi::begin();
            $errorMsg.=$this->checkReferent($refSchool,$refName,$refMail,$refNum);
            $errorMsg.=$this->checkStudents($refNum,$refMail,$stdName,$stdSur,$stdKat,$stdMail,$stdBorn,$stdClss,$stdTeac);
            if($errorMsg==""){
// add referent to db
                $data=array(
                    "school_id"      => $refSchool,
                    "referent_name"  => $refName,
                    "referent_phone" => $refPhone,
                    "referent_email" => $refMail,
                    "rocnik"         => 56);
                dibi::insert('referents',$data)->execute();
                $refid=dibi::insertId();
// add studetns to db
                for($i=0; $i<$refNum; $i++){
                    $data=array(
                        "referent_id"    => $refid,
                        "name"           => $stdName[$i],
                        "surname"        => $stdSur[$i],
                        "class"          => $stdClss[$i],
                        "teacher"        => $stdTeac[$i],
                        "email"          => $stdMail[$i],
                        "category"       => $stdKat[$i],
                        "born_date"      => $stdBorn[$i],
                        "solution_count" => 0);
                    dibi::insert('participants',$data)->execute();
                }
// TODO škola
// send emails
                $header='From: praha@fyzikalniolympiada.cz' . "\r\n" .
                        'Reply-To: praha@fyzikalniolympiada.cz' . "\r\n" .
                        'X-Mailer: PHP/' . phpversion();
                $dbReferent=dibi::query('SELECT * from referents 
                                         JOIN school    USING(school_id) 
                                         WHERE referent_id=%s',$refid)->fetchAll()[0];
                $dbStudents=dibi::query('SELECT * from participants 
                                         JOIN referents USING(referent_id) 
                                         JOIN school    USING(school_id) 
                                         WHERE referent_id=%s',$refid)->fetchAll();
// send email to referent
                $telo ="Vážená referentko FO, Vážený referente FO,\n\n".
                       "potvrzujeme registraci níže uvedených studentů ze školy ".$dbReferent->school_show_name.
                       " (".$dbReferent->school_address.") do pražské Fyzikální olympiády:\n";
                foreach($dbStudents as $std){
                    $telo.="    ".$std->name." ".$std->surname.
                           " ze třídy ".$std->class.
                           ", jehož vyučující fyziky je ".$std->teacher.
                           " do kategorie ".$std->category.".\n";
                }
                $telo.="Prosíme Vás o kontrolu a v případě potíží nás, prosím, kontaktujte na níže uvedeném emailu.\n\n".
                       "Děkujeme za trpělivost.\n".
                       "S pozdravem\nza KK FO Praha\nOtokar\npraha@fyzikalniolympiada.cz".
                       "\n\nTento email byl vygenerován automaticky.";

                @mail($dbReferent->email, "Potvrzení o přihlášení studentů do FO", $telo, $header);
                @mail("backup@fyzikalniolympiada.cz", "Potvrzení o přihlášení studentů do FO", $telo, $header);
                @mail("lukas.ledvina@fyzikalniolympiada.cz", "Potvrzení o přihlášení studentů do FO", $telo, $header);
// send emails to students
                foreach($dbStudents as $std){
                    $telo ="Vážená řešitelko FO, Vážený řešiteli FO,\n\n".
                           "potvrzujeme, že jste byl/a registrován/a do pražské Fyzikální olympiády.\n\n".
                           "Váš referent ".$dbReferent->referent_name." o Vás vyplnil následující údaje:\n".
                           "    Kategorie: ".$std->category.",\n".
                           "    vyučující fyziky: ".$std->teacher.",\n".
                           "    třída: ".$std->class.",\n".
                           "    škola: ".$std->school_show_name." (".$std->school_address."),\n".
                           "    datum narození: ".substr($std->born_date,0,10).".\n\n".
                           "Prosíme Vás o kontrolu a v případě nesrovnalostí nás, prosím, kontaktujte na níže uvedeném emailu.\n\n".
                           "Děkujeme za zájem.\n".
                           "S pozdravem\nza KK FO Praha\nOtokar\npraha@fyzikalniolympiada.cz".
                           "\n\nTento email byl vygenerován automaticky.";
                    @mail($std->email, "Potvrzení o přihlášení studenta do FO", $telo, $header);
                    @mail("backup@fyzikalniolympiada.cz", "Potvrzení o přihlášení studenta do FO", $telo, $header);
                    @mail("lukas.ledvina@fyzikalniolympiada.cz", "Potvrzení o přihlášení studenta do FO", $telo, $header);
                }
// TODO lepší zpráva
                $step=1;
                $this->flashMessage('Přihlášení proběhlo úspěšně, byly rozeslány potvrzovací emaily.','Úspěch');
                $this->redirect('this');
            }
            dibi::commit();
        }




// send data to template
        $this->template->step     =$step;
        $this->template->stepname =$step_names[$step];
        $this->template->errorMsg =$errorMsg;
        $this->template->okMsg    =$okMsg;
        $this->template->schools  =$schools;
        $this->template->refName  =$refName;
        $this->template->refMail  =$refMail;
        $this->template->refPhone =$refPhone;
        $this->template->refNum   =$refNum;
        $this->template->refSchool=$refSchool;
        $this->template->schName  =$schName;
        $this->template->schAdr   =$schAdr;
        $this->template->rekap    =$rekap;
        $this->template->refSchoolName=$refSchoolName;
        //$this->template->schools[0]->selected=true;
        //bdump($this->template->schools);
//        if($step>=2){
            $this->template->stdName=$stdName;
            $this->template->stdSur =$stdSur;
            $this->template->stdKat =$stdKat;
            $this->template->stdMail=$stdMail;
            $this->template->stdBorn=$stdBorn;
            $this->template->stdClss=$stdClss;;
            $this->template->stdTeac=$stdTeac;
//        }
    }

    function checkReferent($refSchool,$refName,$refMail,$refNum)
    {
        $errorMsg="";
        if($refSchool==NULL)
            $errorMsg.="Musíte nejprve vyplnit školu.<br />";
        if($refName=="")
            $errorMsg.="Musíte nejprve vyplnit své jméno.<br />";
        if(!filter_var($refMail, FILTER_VALIDATE_EMAIL))
            $errorMsg.="Zadaný email není ve validní formě.<br />";
        if(!filter_var($refNum, FILTER_VALIDATE_INT))
            $errorMsg.="Počet přihlašovaných studentů musí být přirozené číslo.<br />";
       return $errorMsg;
    }

    function checkSchool($schName,$schAdr)
    {
        $errorMsg="";
        if($schName=="")
            $errorMsg.="Zobrazované jméno školy musí být vypněno.<br />";
        if($schAdr=="")
            $errorMsg.="Adresa školy musí být vyplněna.<br />";
        return $errorMsg;
    }

    function checkStudents($refNum,$refMail,$stdName,$stdSur,$stdKat,$stdMail,$stdBorn,$stdClss,$stdTeac)
    {
        $errorMsg="";
        for($i=0; $i<$refNum; $i++){
        if($stdName[$i]=="")
            $errorMsg.="Jméno studenta ".($i+1)." nesmí zůstat prázdné.<br />";
            if($stdSur[$i]=="")
                $errorMsg.="Příjmení studenta ".($i+1)." nesmí zůstat prázdné.<br />";
            if($stdKat[$i]=="")
                $errorMsg.="Kategorie studenta ".($i+1)." nesmí zůstat prázdná.<br />";
            if(!filter_var($stdMail[$i], FILTER_VALIDATE_EMAIL)){
                $errorMsg.="Zadaný email (".($i+1).".) <i>".$stdMail[$i]."</i> nemá validní formu.<br />";
            }
            else{
                if($refMail==$stdMail[$i])
                    $errorMsg.="Email (".($i+1).".) <i>".$stdMail[$i]."</i> se shoduje s Vaším emailem.<br />";
                for($j=0; $j<$i; $j++)if($stdMail[$j]==$stdMail[$i])
                    $errorMsg.="Zadaný email (".($i+1).".) <i>".$stdMail[$i]."</i> je již použit pro studenta ".($j+1).".<br />";
            }
            $emails=dibi::query("SELECT name, surname,email FROM `participants` 
                JOIN referents USING(referent_id) 
                JOIN school USING(school_id) 
                WHERE rocnik=56 AND kraj_id=%s AND email=%s AND category=%s;",
                $this::getKrajId(),$stdMail[$i],$stdKat[$i])->fetchAll();
            if($emails!=NULL)if($emails[0]!=NULL){
                $errorMsg.="Na emailovou adresu <i>".$stdMail[$i]."</i> je tento rok do kategorie ".$stdKat[$i]."
                    již registrován student ".$emails[0]->name." ".$emails[0]->surname.".<br />";
            }
            if(!preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2})/", $stdBorn[$i])){
                $errorMsg.="Datum narození studenta ".($i+1)." není ve formátu RRRR-MM-DD.<br />";
            }
            else{
                $bYea=substr($stdBorn[$i],0,4);
                $bMnt=substr($stdBorn[$i],5,2);
                $bDay=substr($stdBorn[$i],8,2);
                if(!checkdate($bMnt,$bDay,$bYea))
                    $errorMsg.="Datum narození studenta ".($i+1)." <i>".$bDay.". ".$bMnt.". ".$bYea."</i> není v kalendáři.<br />";
                if($bYea<1933+56 || $bYea>1953+56)
                    $errorMsg.="Věk studenta ".($i+1)." není v rozmezí 5-25 let.<br />";
            }
            if($stdClss[$i]=="")
                $errorMsg.="Třídu studenta ".($i+1)." je potřeba vyplnit.<br />";
            if($stdTeac[$i]=="")
                $errorMsg.="Jméno vyučujícího fyziky studenta ".($i+1)." je potřeba vyplnit.<br />";
        }
        return $errorMsg;
    }






    function getKrajId()
    {
        return dibi::query('SELECT kraj_id FROM kraj WHERE webpage=%s',$this::getSubdomain())->fetchAll()[0]->kraj_id;
        return 1;
    }
    function getSchools()
    {
        return dibi::query('SELECT school_id,school_choice, "" AS selected 
            FROM school 
            WHERE kraj_id=1
            ORDER BY school_choice;')->fetchAll();
        return dibi::query('SELECT school_id,school_choice, "no" AS selected FROM school WHERE kraj_id=%d;',$this::getKrajId())->fetchAll();
        //return dibi::query('SELECT school_id,school_choice FROM school WHERE kraj_id=%d',$this::getKrajId())->fetchAll();
    }
}

