<?php

namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;
use dibi;

/**
 * @title Přihláška
 * @model Eywa\CMS\Model\Page
 */

// TODO emaily

// TODO thead+tbody

class Application extends Base
{
    public function renderDefault($path)
    {
bdump($_POST);
        $step_names=array(
            1    => "Základní údaje o referentovi",
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
        if(isset($_POST['oldNum']))   $oldNum   =$_POST['oldNum'];   else $oldNum   =0;
        if(isset($_POST['rekapE']))   $rekap    =$_POST['rekapE'];   else $rekap    =false;

        if(isset($_POST['schName']))  $schName  =$_POST['schName'];  else $schName  ="";
        if(isset($_POST['schAdr']))   $schAdr   =$_POST['schAdr'];   else $schAdr   ="";

        $stdId  =array();
        $stdName=array();
        $stdSur =array();
        $stdKat =array();
        $stdMail=array();
        $stdBorn=array();
        $stdClss=array();
        $stdTeac=array();

        for($i=0; $i<$refNum; $i++){
            if(isset($_POST['stdId'  ][$i]))$stdId  [$i]=$_POST['stdId'  ][$i];else $stdId  [$i]="";
            if(isset($_POST['stdName'][$i]))$stdName[$i]=$_POST['stdName'][$i];else $stdName[$i]="";
            if(isset($_POST['stdSur' ][$i]))$stdSur [$i]=$_POST['stdSur' ][$i];else $stdSur [$i] ="";
            if(isset($_POST['stdKat' ][$i]))$stdKat [$i]=$_POST['stdKat' ][$i];else $stdKat [$i]="";
            if(isset($_POST['stdMail'][$i]))$stdMail[$i]=$_POST['stdMail'][$i];else $stdMail[$i]="@";
            if(isset($_POST['stdBorn'][$i]))$stdBorn[$i]=$_POST['stdBorn'][$i];else $stdBorn[$i]="";
            if(isset($_POST['stdClss'][$i]))$stdClss[$i]=$_POST['stdClss'][$i];else $stdClss[$i]="";
            if(isset($_POST['stdTeac'][$i]))$stdTeac[$i]=$_POST['stdTeac'][$i];else $stdTeac[$i]="";
        }

        if($refSchool=="NULL")$refSchool=NULL;
// old students
        $oldStd=dibi::query('SELECT participant_id, name, surname, class, teacher
                FROM participants
                JOIN referents USING(referent_id)
                JOIN school USING(school_id)
                WHERE school_id=%s AND rocnik>%s AND NOT ISNULL(born_date)
                ORDER BY surname
                ;',$refSchool,58-4)->fetchAll();
                //ORDER BY rocnik DESC
        $oldNum=count($oldStd);
// garant ok press
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
        }

// delete button
        for($i=0; $i<$refNum; $i++)if(isset($_POST['stdRm'][$i])){
            $okMsg.="Řádek ".($i+1)." smazán.<br />";
            $refNum--;
            for($j=$i;$j<$refNum; $j++){
                $stdId  [$j]=$stdId  [$j+1];
                $stdName[$j]=$stdName[$j+1];
                $stdSur [$j]=$stdSur [$j+1];
                $stdKat [$j]=$stdKat [$j+1];
                $stdMail[$j]=$stdMail[$j+1];
                $stdBorn[$j]=$stdBorn[$j+1];
                $stdClss[$j]=$stdClss[$j+1];
                $stdTeac[$j]=$stdTeac[$j+1];
            }
        }
// old add button
        for($i=0; $i<$oldNum; $i++)if(isset($_POST['oldAdd'][$i])){
            $student=dibi::query('SELECT participant_id, name, surname, class, teacher
                                  FROM participants
                                  WHERE participant_id=%s;',$_POST['oldId'][$i])->fetchAll()[0];
            $stdId  [$refNum]=$_POST['oldId'][$i];
            $stdName[$refNum]=$student->name;
            $stdSur [$refNum]=$student->surname;
            $stdKat [$refNum]="";
            $stdMail[$refNum]="vyplní se z databáze";
            $stdBorn[$refNum]="vyplní se z databáze";
            $stdClss[$refNum]=$student->class; // TODO
            $stdTeac[$refNum]=$student->teacher;
            $refNum++;
            $okMsg.="Student ".$student->name." ".$student->surname." přidán.";
            for($k=0; $k<$refNum; $k++) {
                if( $stdId  [$k]!="" ) continue;
                if( $stdName[$k]!="" ) continue;
                if( $stdSur [$k]!="" ) continue;
                if( $stdKat [$k]!="" ) continue;
                if( $stdMail[$k]!="@") continue;
                if( $stdBorn[$k]!="" ) continue;
                if( $stdClss[$k]!="" ) continue;
                if( $stdTeac[$k]!="" ) continue;
                $refNum--;
                for($j=$k;$j<$refNum; $j++){
                    $stdId  [$j]=$stdId  [$j+1];
                    $stdName[$j]=$stdName[$j+1];
                    $stdSur [$j]=$stdSur [$j+1];
                    $stdKat [$j]=$stdKat [$j+1];
                    $stdMail[$j]=$stdMail[$j+1];
                    $stdBorn[$j]=$stdBorn[$j+1];
                    $stdClss[$j]=$stdClss[$j+1];
                    $stdTeac[$j]=$stdTeac[$j+1];
                }
                break;
            }
        }

// add button
        if(isset($_POST['stdAdd'])){
            $stdId  [$refNum]="";
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
            for($i=0; $i<$refNum; $i++)if($stdId[$i]!=""){
                $student=dibi::query('SELECT email, born_date
                                      FROM participants
                                      WHERE participant_id=%s;',$stdId[$i])->fetchAll()[0];
                $stdMail[$i]=$student->email;
                $stdBorn[$i]=$student->born_date;
            }

            $errorMsg.=$this->checkReferent($refSchool,$refName,$refMail,$refNum);
            $errorMsg.=$this->checkStudents($refNum,$refMail,$stdName,$stdSur,$stdKat,$stdMail,$stdBorn,$stdClss,$stdTeac);
            if($errorMsg==""){
// add referent to db
                $data=array(
                    "school_id"      => $refSchool,
                    "referent_name"  => $refName,
                    "referent_phone" => $refPhone,
                    "referent_email" => $refMail);
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
                        "solution_count" => 0,
                        "rocnik"         => 58);
                    dibi::insert('participants',$data)->execute();
                    $stdid=dibi::insertId();

                    $data=array(
                        "participant_id" => $stdid,
                        "round"          => "1"
                    );
                    dibi::insert('advance',$data)->execute();
                }
// TODO škola
// send emails
                $header='From: praha@fyzikalniolympiada.cz' . "\r\n" .
                        'Reply-To: praha@fyzikalniolympiada.cz' . "\r\n" .
                        'Content-Type: text/plain; charset=utf-8'. "\r\n" .
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
                $telo.="Prosíme Vás o kontrolu a v případě potíží nás, prosím, kontaktujte na níže uvedeném emailu.\n".
                       "Studentům byl též doručen potvrzovací email na jejich emailovou adresu.\n\n".
                       "Děkujeme za trpělivost.\n".
                       "S pozdravem\nza KK FO Praha\nVěrný server Otokar\npraha@fyzikalniolympiada.cz".
                       "\n\nTento email byl vygenerován automaticky.";

                @mail($dbReferent->referent_email, "Potvrzení o přihlášení studentů do FO", $telo, $header);
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
                           "S pozdravem\nza KK FO Praha\nVěrný server Otokar\npraha@fyzikalniolympiada.cz".
                           "\n\nTento email byl vygenerován automaticky.";
                    @mail($std->email, "Potvrzení o přihlášení studenta do FO", $telo, $header);
                    @mail("backup@fyzikalniolympiada.cz", "Potvrzení o přihlášení studenta do FO", $telo, $header);
                    @mail("lukas.ledvina@fyzikalniolympiada.cz", "Potvrzení o přihlášení studenta do FO", $telo, $header);//next param
                }
// TODO lepší zpráva
                $step=1;
                dibi::commit();
                $this->flashMessage('Přihlášení proběhlo úspěšně, byly rozeslány potvrzovací emaily.','Úspěch');
                $this->redirect('this');
            }
            dibi::commit();
        }




// send data to template
        if( $errorMsg != "" )
            $errorMsg.="<br /> V případě přetrvávajících potíží nás kontaktujte na emailové adrese
                        <a href='mailto:praha@fyzikalniolympiada.cz'>praha@fyzikalniolympiada.cz</a>.";
        $this->template->step     =$step;
        $this->template->stepname =$step_names[$step];
        $this->template->errorMsg =$errorMsg;
        $this->template->okMsg    =$okMsg;
        $this->template->schools  =$schools;
        $this->template->refName  =$refName;
        $this->template->refMail  =$refMail;
        $this->template->refPhone =$refPhone;
        $this->template->refNum   =$refNum;
        $this->template->oldNum   =$oldNum;
        $this->template->refSchool=$refSchool;
        $this->template->schName  =$schName;
        $this->template->schAdr   =$schAdr;
        $this->template->rekap    =$rekap;
        $this->template->refSchoolName=$refSchoolName;
        //$this->template->schools[0]->selected=true;
        //bdump($this->template->schools);
//        if($step>=2){
            $this->template->stdId  =$stdId;
            $this->template->stdName=$stdName;
            $this->template->stdSur =$stdSur;
            $this->template->stdKat =$stdKat;
            $this->template->stdMail=$stdMail;
            $this->template->stdBorn=$stdBorn;
            $this->template->stdClss=$stdClss;;
            $this->template->stdTeac=$stdTeac;
            $this->template->oldStd =$oldStd;
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
            if(strcmp($stdMail[$i],"vyplní se z databáze")!=0){
                if(!filter_var($stdMail[$i], FILTER_VALIDATE_EMAIL)){
                    $errorMsg.="Zadaný email (".($i+1).".) <i>".$stdMail[$i]."</i> nemá validní formu.<br />";
                }
                else{
                    if($refMail==$stdMail[$i])
                        $errorMsg.="Email (".($i+1).".) <i>".$stdMail[$i]."</i> se shoduje s Vaším emailem.<br />";
                    for($j=0; $j<$i; $j++)if($stdMail[$j]==$stdMail[$i])if($stdName[$i] != $stdName[$j] || $stdSur[$i] != $stdSur[$j])
                        $errorMsg.="Zadaný email (".($i+1).".) <i>".$stdMail[$i]."</i> je již použit pro studenta ".($j+1).".<br />";
                }
                $emails=dibi::query("SELECT name, surname,email FROM `participants`
                    JOIN referents USING(referent_id)
                    JOIN school USING(school_id)
                    WHERE rocnik=58 AND kraj_id=%s AND email=%s AND category=%s;",
                    $this::getKrajId(),$stdMail[$i],$stdKat[$i])->fetchAll();
                if($emails!=NULL)if($emails[0]!=NULL){
                    $errorMsg.="Na emailovou adresu <i>".$stdMail[$i]."</i> je tento rok do kategorie ".$stdKat[$i]."
                        již registrován student ".$emails[0]->name." ".$emails[0]->surname.".<br />";
                }
            }
            if(strcmp($stdMail[$i],"vyplní se z databáze")!=0){
                if(!preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2})/", $stdBorn[$i])){
                    $errorMsg.="Datum narození studenta ".($i+1)." není ve formátu RRRR-MM-DD.<br />";
                }
                else{
                    $bYea=substr($stdBorn[$i],0,4);
                    $bMnt=substr($stdBorn[$i],5,2);
                    $bDay=substr($stdBorn[$i],8,2);
                    if(!checkdate($bMnt,$bDay,$bYea))
                        $errorMsg.="Datum narození studenta ".($i+1)." <i>".$bDay.". ".$bMnt.". ".$bYea."</i> není v kalendáři.<br />";
                    if($bYea<1933+58 || $bYea>1953+58)
                        $errorMsg.="Věk studenta ".($i+1)." není v rozmezí 5-25 let.<br />";
                }
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

