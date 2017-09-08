<?php


namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;
use dibi;
use Nette\Environment;

use \Nette\Application\UI;
use \Nette\Security;

/**
 * @title Registrace
 * @model Eywa\CMS\Model\Page
 */

// TODO emaily

// TODO thead+tbody

// Zalogování se -> buď zná heslo, nebo znovuzaslání
//               -> registrace nové osoby (učitel/student)
// Case student:
//     * Uvidí, zdali už není od učitele registrován
//        -> Je-li -> úprava údajů -> notifikace učiteli
//                 -> upload řešení
//                 -> podívat se na své řešení
//        -> Není-li možnost registrace -> notifikace učiteli
// Case učitel:
//     * Uvidí seznam svých registrovaných studentů (školní pouze po autorizaci komisí)
//     * Může registrovat další studenty
//     * Může uploadovat/prohlížet řešení
//
// Při změně registrace se posílá email
//   * kdo změnu udělal
//   * komise
//   * referent
//   * student


// nahoře lišta s tím, co se děje

//class Registration extends Eywa\CMS\Presenters\Base
class Registration extends Base
{
    private $authenticator = null;

    private $errorMsg = "";
    private $okMsg    = "";
    private $warnMsg  = "";

    private $logEmail = "";
    private $logPass  = "";
    private $role = null; // 1 - referent, 2 - student

    private $status=0;
    // 0   -- Přihlášení
    // 10  -- registrace, zapomenuté heslo
    // 11  -- nová registrace registrace
    // 121 -- registrace referenta
    // 122 -- registrace studenta TODO
    // 131,2  -- registrace školy
    // 20  -- dashboard


    private $refName;
    private $refMail;
    private $refPhone;
    private $refSchool;
    private $refSchoolName;

    private $schName;
    private $schFullName;
    private $schAdr;
    private $schEmail;

    private $schools;

    private $newPass  = "";
    private $newPass2 = "";

    private $stdName = array();
    private $stdSur  = array();
    private $stdKat  = array();
    private $stdMail = array();
    private $stdBorn = array();
    private $stdClss = array();
    private $stdTeac = array();


    public function renderDefault($path)
    {
        bdump($_POST);

        $this->validateInput();
        $this->setAuthentificator();

        if( $this->errorMsg !== "" ) {
            $this->errorMsg .= "Chyba před vyhodnocením tlačítek.<br />";
            $this->sendToTemplate();
            return;
        }

        $this->buttonAction();
        $this->sendToTemplate();
    }

    function validateInput()
    {
        if( isset($_POST['status']) && filter_var($_POST['status'], FILTER_VALIDATE_INT) )
            $this->status = intval( $_POST['status'] );
        if( isset($_POST['logEmail']) )
            $this->logEmail = $_POST['logEmail'];
        if( isset($_POST['logPass']) )
            $this->logPass = $_POST['logPass'];
        if( isset($_POST['roleRAD']) ) {
            if( $_POST['roleRAD'] === "referent" )
                $this->role = 1;
            if( $_POST['roleRAD'] === "student" )
                $this->role = 2;
        }
        if( isset($_POST['refName']) )
            $this->refName = $_POST['refName'];
        if( isset($_POST['refMail']) )
            $this->refMail = $_POST['refMail'];
        if( isset($_POST['refPhone']) )
            $this->refPhone = $_POST['refPhone'];
        if( isset($_POST['refSchool']) )
            $this->refSchool = $_POST['refSchool'];
        $this->setSchool();

        $this->newPass = $_POST['newPass'];
        $this->newPass2 = $_POST['newPass2'];

        if( isset($_POST['schName']) )
            $this->schName = $_POST['schName'];
        if( isset($_POST['schFullName']) )
            $this->schFullName = $_POST['schFullName'];
        if( isset($_POST['schAdr']) )
            $this->schAdr = $_POST['schAdr'];
        if( isset($_POST['schEmail']) )
            $this->schEmail = $_POST['schEmail'];

    }

    function buttonAction()
    {
        if(isset($_POST['loginBT']))
            $this->loginBTPress($this->logEmail, $this->logPass);
        if(isset($_POST['logoutBT']))
            $this->logoutBTPress();
        if(isset($_POST['newPassBT']))
            $this->newPassBTPress();
        if(isset($_POST['regContBT']))
            $this->regContBTPress();
        if(isset($_POST['regCont2BT']))
            $this->regCont2BTPress();
        if(isset($_POST['regCont31BT']))
            $this->regCont31BTPress();
        if(isset($_POST['missSchoolBT']))
            $this->missSchoolBTPress();
        if(isset($_POST['schSaveBT']))
            $this->schSaveBTPress();
        if(isset($_POST['schCancBT']))
            $this->schCancBTPress();
    }

    function sendToTemplate()
    {
        $this->template->status        = $this->status;
        $this->template->errorMsg      = $this->errorMsg;
        $this->template->warnMsg       = $this->warnMsg;
        $this->template->okMsg         = $this->okMsg;
        $this->template->logEmail      = $this->logEmail;

        $this->template->refName       = $this->refName;
        $this->template->refMail       = $this->refMail;
        $this->template->refPhone      = $this->refPhone;
        $this->template->refSchool     = $this->refSchool;
        $this->template->schName       = $this->schName;
        $this->template->schFullName   = $this->schFullName;
        $this->template->schAdr        = $this->schAdr;
        $this->template->schEmail      = $this->schEmail;
        $this->template->user = "Nepřihlášen";
        if( $this->getUser()->isLoggedIn() ) {
            $this->template->user = $this->getUser()->getIdentity()->getName();
        }
        $this->template->schools  = $this->schools;

    }

    function setAuthentificator()
    {
        $this->authenticator = new MySQLAuthenticator();
        $this->getUser()->setAuthenticationHandler( $this->authenticator );
    }

    function logoutBTPress()
    {
        $this->getUser()->logout();
        if( !$this->getUser()->isLoggedIn() )
            $this->okMsg.="Odhlášení bylo úspěšné.<br />";
    }

    function loginBTPress($logEmail, $logPass)
    {
        if( !filter_var($logEmail, FILTER_VALIDATE_EMAIL) )
            $this->errorMsg.="Email \"" . $logEmail . "\" nemá validní formu.<br />";
        if( $logPass == "" )
            $this->errorMsg.="Heslo nemůže zůstat prázdné.<br />";

        if( $this->errorMsg !== "" )
            return;

        try {
            $this->getUser()->login($logEmail, $logPass);
        } catch(\Nette\Security\AuthenticationException $e){
            $this->errorMsg .= "Zadali jste chybný email nebo heslo. (".$e->getMessage().")<br />";
            return;
        }

        if( $this->getUser()->isLoggedIn() ) {
            $this->okMsg.="Přihlášení bylo úspěšné.<br />";
            $this->status = 20;
            // TODO set role
        }
        else {
            $this->errorMsg .= "Zadali jste chybný email nebo heslo.<br />";
        }
    }

    function newPassBTPress()
    {
        if( $this->getUser()->isLoggedIn() )
            $this->warnMsg .= "Již jste přihlášen.<br />";
        else
            $this->status = 10;
    }

    function regContBTPress()
    {
        //if( $this->role === null )
        //    return;
        $newpass = $this->authenticator->setPass($this->logEmail);

        if( $newpass == null) {
        // nová registrace
            $this->okMsg .= "Uživatel nenalezen, pokračujeme k registraci.<br />";
            $this->status = 11;
        }
        else {
        // pokud je již registrován -- poslat mail s novým heslem TODO
            $this->okMsg .= "Nové heslo je $newpass <br />";
            $this->okMsg .= "Nové heslo bylo zasláno na email.<br />";
            $this->status = 0;
        }
    }

    function regCont2BTPress()
    {
        if( $this->role === null ) {
            $this->errorMsg .= "Nejprve si vyberte roli referenta či studenta <br />";
            return;
        }
        if( $this->role === 1 ) { // referent
            $this->status = 121;
        }
        if( $this->role === 2 ) { // student
            $this->status = 122;
        }
    }

    function regCont31BTPress()
    {
        $this->errorMsg .= $this->checkReferent($this->refSchool,$this->refName,$this->refMail);
        if( $this->errorMsg != "" )
            return;
        // TODO
        // setup/generate password
        // write to dtb
        // send email
        // login
        // redirect to dashboard
    }

    function missSchoolBTPress()
    {
        $this->status = 131;
    }

    function schSaveBTPress()
    {
        $this->errorMsg .= $this->checkSchool($this->schName, $this->schFullName, $this->schAdr, $this->schEmail);
        if($this->errorMsg == "" ) {
            if(dibi::query('SELECT * from school WHERE school_show_name=%s;',$this->schName)->fetchAll()[0]==null){
                $data=array(
                    "kraj_id"          => $this::getKrajId(),
                    "school_show_name" => $this->schName,
                    "school_choice"    => $this->schName,
                    "school_address"   => $this->schAdr,
                    "school_full_name" => $this->schFullName,
                    "school_email"     => $this->schEmail,
                );
                dibi::insert('school',$data)->execute();
                $this->okMsg .= "Škola byla uložena do databáze.<br />";
            }
            else{
                $this->okMsg .= "Byla použita škola stejného jména z databáze.<br />";
            }
            $this->refSchool = dibi::query(
                'SELECT school_id
                 FROM school
                 WHERE school_show_name=%s',$this->schName)->fetchAll()[0]->school_id;
        }
        $this->schCancBTPress();
        $this->setSchool();
    }

    function schCancBTPress()
    {
        if( $this->status == 131 )
            $this->status = 121;
        if( $this->status == 132 )
            $this->status = 122;
    }

    function checkSchool($schName, $schFullName, $schAdr, $schEmail)
    {
        $errorMsg="";
        if($schName=="")
            $errorMsg.="Zobrazované jméno školy musí být vypněno.<br />";
        if($schFullName=="")
            $errorMsg.="Úplný název školy musí být vyplněn.<br />";
        if($schAdr=="")
            $errorMsg.="Adresa školy musí být vyplněna.<br />";
        if($schEmail=="")
            $errorMsg.="Kontaktní email školy musí být vyplněn.<br />";
        if( !filter_var($schEmail, FILTER_VALIDATE_EMAIL) )
            $errorMsg.="Kontaktní email " . $schEmail . " nemá validní formu.<br />";
        return $errorMsg;
    }

    function checkReferent($refSchool,$refName,$refMail)
    {
        $errorMsg="";
        if($refSchool == "NULL")
            $errorMsg.="Musíte nejprve vyplnit svou školu.<br />";
        if($refName=="")
            $errorMsg.="Musíte nejprve vyplnit své jméno.<br />";
        if(!filter_var($refMail, FILTER_VALIDATE_EMAIL))
            $errorMsg.="Zadaný email není ve validní formě.<br />";
        if( $this->newPass !== $this->newPass2 )
            $errorMsg.="Zadaná hesla se neshodují.<br />";
        if( $this->newPass == "" && $this->newPass2 == "" )
            $this->warnMsg .= "Heslo nebylo zadáno, bude vygenerováno automaticky.<br />";

        return $errorMsg;
    }

    function setSchool()
    {
        $this->schools =$this::getSchools();
        foreach($this->schools as $school)
            if($this->refSchool==$school->school_id)
                $this->refSchool=$school->school_id;
//        if($this->refSchool!="null")
            foreach($this->schools as $school) {
                if($school->school_id==$this->refSchool){
                    $school->selected=true;
                    $this->refSchoolName=$school->school_choice;
                }
                else
                    $school->selected=false;
            }
    }

    function getSchools()
    {
        return dibi::query('SELECT school_id,school_choice, "" AS selected
            FROM school
            WHERE kraj_id=1
            ORDER BY school_choice;')->fetchAll();
        return dibi::query('SELECT school_id,school_choice, "no" AS selected FROM school WHERE kraj_id=%d;',$this::getKrajId())->fetchAll();
    }

    function getKrajId()
    {
        return dibi::query('SELECT kraj_id FROM kraj WHERE webpage=%s',$this::getSubdomain())->fetchAll()[0]->kraj_id;
        return 1;
    }

}




















//
//        $step_names=array(
//            1    => "Výběr školy",
//           "1.5" => "Přidání školy",
//            2    => "Výběr referenta",
//           "2.5" => "Základní údaje o referentovi",
//            3    => "Údaje o řešitelích",
//            4    => "Rekapitulace",
//            5    => "Hotovo");
//
//        $errorMsg="";
//        $okMsg="";
//        $refSchoolName=NULL;
//        $rocnik=Environment::getVariable('rocnik');
//
//        if(isset($_POST['step']))     $step     =$_POST['step'];     else $step     =1;
//        if(isset($_POST['refSchool']))$refSchool=$_POST['refSchool'];else $refSchool=NULL;
//        if(isset($_POST['refName']))  $refName  =$_POST['refName'];  else $refName  ="";
//        if(isset($_POST['refMail']))  $refMail  =$_POST['refMail'];  else $refMail  ="@";
//        if(isset($_POST['refPhone'])) $refPhone =$_POST['refPhone']; else $refPhone ="";
//        if(isset($_POST['refNum']))   $refNum   =$_POST['refNum'];   else $refNum   ="";
//        if(isset($_POST['oldNum']))   $oldNum   =$_POST['oldNum'];   else $oldNum   =0;
//        if(isset($_POST['rekapE']))   $rekap    =$_POST['rekapE'];   else $rekap    =false;
//
//        if(isset($_POST['schName']))  $schName  =$_POST['schName'];  else $schName  ="";
//        if(isset($_POST['schAdr']))   $schAdr   =$_POST['schAdr'];   else $schAdr   ="";
//        if(isset($_POST['schFull']))  $schFull  =$_POST['schFull'];  else $schFull  ="";
//        if(isset($_POST['schMail']))  $schMail  =$_POST['schMail'];  else $schMail  ="";
//
//        $stdId  =array();
//        $stdName=array();
//        $stdSur =array();
//        $stdKat =array();
//        $stdMail=array();
//        $stdBorn=array();
//        $stdClss=array();
//        $stdTeac=array();
//
//        for($i=0; $i<$refNum; $i++){
//            if(isset($_POST['stdId'  ][$i]))$stdId  [$i]=$_POST['stdId'  ][$i];else $stdId  [$i]="";
//            if(isset($_POST['stdName'][$i]))$stdName[$i]=$_POST['stdName'][$i];else $stdName[$i]="";
//
//
//            if(isset($_POST['stdSur' ][$i]))$stdSur [$i]=$_POST['stdSur' ][$i];else $stdSur [$i] ="";
//            if(isset($_POST['stdKat' ][$i]))$stdKat [$i]=$_POST['stdKat' ][$i];else $stdKat [$i]="";
//            if(isset($_POST['stdMail'][$i]))$stdMail[$i]=$_POST['stdMail'][$i];else $stdMail[$i]="@";
//            if(isset($_POST['stdBorn'][$i]))$stdBorn[$i]=$_POST['stdBorn'][$i];else $stdBorn[$i]="";
//            if(isset($_POST['stdClss'][$i]))$stdClss[$i]=$_POST['stdClss'][$i];else $stdClss[$i]="";
//            if(isset($_POST['stdTeac'][$i]))$stdTeac[$i]=$_POST['stdTeac'][$i];else $stdTeac[$i]="";
//        }
//
//        if($refSchool=="NULL")$refSchool=NULL;
//// old students
//        $oldStd=dibi::query('SELECT participant_id, name, surname, class, teacher
//                FROM participants
//                JOIN referents USING(referent_id)
//                JOIN school USING(school_id)
//                WHERE school_id=%s AND rocnik>%i AND NOT ISNULL(born_date)
//                ORDER BY surname
//                ;',$refSchool,$rocnik-4)->fetchAll();
//                //ORDER BY rocnik DESC
//        $oldNum=count($oldStd);
//
//// load refSchool from form ???TODO???
//        $schools =$this::getSchools();
////        foreach($schools as $school)if($refSchool==$school->school_id)
////            $refSchool=$school->school_id;
//        if($refSchool!=NULL)foreach($schools as $school)if($school->school_id==$refSchool){
//            $school->selected=true;
//            $refSchoolName=$school->school_choice;
//        }
//
//
///* *****************************************************************************
// * Actions
// * *****************************************************************************/
//        if( $this->errorMsg == "" ) {
//            $this->stdName=array_fill(0,$refNum,"");
//            $this->stdSur =array_fill(0,$refNum,"");
//            $this->stdKat =array_fill(0,$refNum,"");
//            $this->stdMail=array_fill(0,$refNum,"@");
//            $this->stdBorn=array_fill(0,$refNum,"");
//            $this->stdClss=array_fill(0,$refNum,"");
//            $this->stdTeac=array_fill(0,$refNum,"");
//        }


//// continue to rekapitulation
//        if(isset($_POST['rekap'])){
//            $errorMsg.=$this->checkStudents($refNum,$refMail,$stdName,$stdSur,$stdKat,$stdMail,$stdBorn,$stdClss,$stdTeac);
//            if($errorMsg==""){
//                $step=3;
//            }
//        }
//
//// delete button
//        for($i=0; $i<$refNum; $i++)if(isset($_POST['stdRm'][$i])){
//            $okMsg.="Řádek ".($i+1)." smazán.<br />";
//            $refNum--;
//            for($j=$i;$j<$refNum; $j++){
//                $stdId  [$j]=$stdId  [$j+1];
//                $stdName[$j]=$stdName[$j+1];
//                $stdSur [$j]=$stdSur [$j+1];
//                $stdKat [$j]=$stdKat [$j+1];
//                $stdMail[$j]=$stdMail[$j+1];
//                $stdBorn[$j]=$stdBorn[$j+1];
//                $stdClss[$j]=$stdClss[$j+1];
//                $stdTeac[$j]=$stdTeac[$j+1];
//            }
//        }
//// old add button
//        for($i=0; $i<$oldNum; $i++)if(isset($_POST['oldAdd'][$i])){
//            $student=dibi::query('SELECT participant_id, name, surname, class, teacher
//                                  FROM participants
//                                  WHERE participant_id=%s;',$_POST['oldId'][$i])->fetchAll()[0];
//            $stdId  [$refNum]=$_POST['oldId'][$i];
//            $stdName[$refNum]=$student->name;
//            $stdSur [$refNum]=$student->surname;
//            $stdKat [$refNum]="";
//            $stdMail[$refNum]="vyplní se z databáze";
//            $stdBorn[$refNum]="vyplní se z databáze";
//            $stdClss[$refNum]=$student->class; // TODO
//            $stdTeac[$refNum]=$student->teacher;
//            $refNum++;
//            $okMsg.="Student ".$student->name." ".$student->surname." přidán.";
//            for($k=0; $k<$refNum; $k++) {
//                if( $stdId  [$k]!="" ) continue;
//                if( $stdName[$k]!="" ) continue;
//                if( $stdSur [$k]!="" ) continue;
//                if( $stdKat [$k]!="" ) continue;
//                if( $stdMail[$k]!="@") continue;
//                if( $stdBorn[$k]!="" ) continue;
//                if( $stdClss[$k]!="" ) continue;
//                if( $stdTeac[$k]!="" ) continue;
//                $refNum--;
//                for($j=$k;$j<$refNum; $j++){
//                    $stdId  [$j]=$stdId  [$j+1];
//                    $stdName[$j]=$stdName[$j+1];
//                    $stdSur [$j]=$stdSur [$j+1];
//                    $stdKat [$j]=$stdKat [$j+1];
//                    $stdMail[$j]=$stdMail[$j+1];
//                    $stdBorn[$j]=$stdBorn[$j+1];
//                    $stdClss[$j]=$stdClss[$j+1];
//                    $stdTeac[$j]=$stdTeac[$j+1];
//                }
//                break;
//            }
//        }
//
//// add button
//        if(isset($_POST['stdAdd'])){
//            $stdId  [$refNum]="";
//            $stdName[$refNum]="";
//            $stdSur [$refNum]="";
//            $stdKat [$refNum]="";
//            $stdMail[$refNum]="@";
//            $stdBorn[$refNum]="";
//            $stdClss[$refNum]="";
//            $stdTeac[$refNum]="";
//            $refNum++;
//            $okMsg.="Řádek ".$refNum." přidán.<br />";
//        }
//
//// edit garant
//        if(isset($_POST['edit_garant'])){
//            $rekap=true;
//            $step=1;
//        }
//// edit students
//        if(isset($_POST['edit_resitele'])){
//            $rekap=true;
//            $step=2;
//        }
//
//// submit
//        if(isset($_POST['submit'])){
//            dibi::begin();
//            for($i=0; $i<$refNum; $i++)if($stdId[$i]!=""){
//                $student=dibi::query('SELECT email, born_date
//                                      FROM participants
//                                      WHERE participant_id=%s;',$stdId[$i])->fetchAll()[0];
//                $stdMail[$i]=$student->email;
//                $stdBorn[$i]=$student->born_date;
//            }
//
//            $errorMsg.=$this->checkReferent($refSchool,$refName,$refMail,$refNum);
//            $errorMsg.=$this->checkStudents($refNum,$refMail,$stdName,$stdSur,$stdKat,$stdMail,$stdBorn,$stdClss,$stdTeac);
//            if($errorMsg==""){
//// add referent to db
//                $data=array(
//                    "school_id"      => $refSchool,
//                    "referent_name"  => $refName,
//                    "referent_phone" => $refPhone,
//                    "referent_email" => $refMail);
//                dibi::insert('referents',$data)->execute();
//                $refid=dibi::insertId();
//// add studetns to db
//                for($i=0; $i<$refNum; $i++){
//                    $data=array(
//                        "referent_id"    => $refid,
//                        "name"           => $stdName[$i],
//                        "surname"        => $stdSur[$i],
//                        "class"          => $stdClss[$i],
//                        "teacher"        => $stdTeac[$i],
//                        "email"          => $stdMail[$i],
//                        "category"       => $stdKat[$i],
//                        "born_date"      => $stdBorn[$i],
//                        "solution_count" => 0,
//                        "rocnik"         => $rocnik);
//                    dibi::insert('participants',$data)->execute();
//                    $stdid=dibi::insertId();
//
//                    $data=array(
//                        "participant_id" => $stdid,
//                        "round"          => "1"
//                    );
//                    dibi::insert('advance',$data)->execute();
//                }
//// TODO škola
//// send emails
//                $header='From: praha@fyzikalniolympiada.cz' . "\r\n" .
//                        'Reply-To: praha@fyzikalniolympiada.cz' . "\r\n" .
//                        'Content-Type: text/plain; charset=utf-8'. "\r\n" .
//                        'X-Mailer: PHP/' . phpversion();
//                $dbReferent=dibi::query('SELECT * from referents
//                                         JOIN school    USING(school_id)
//                                         WHERE referent_id=%s',$refid)->fetchAll()[0];
//                $dbStudents=dibi::query('SELECT * from participants
//                                         JOIN referents USING(referent_id)
//                                         JOIN school    USING(school_id)
//                                         WHERE referent_id=%s',$refid)->fetchAll();
//// send email to referent
//                $telo ="Vážená referentko FO, Vážený referente FO,\n\n".
//                       "potvrzujeme registraci níže uvedených studentů ze školy ".$dbReferent->school_show_name.
//                       " (".$dbReferent->school_address.") do pražské Fyzikální olympiády:\n";
//                foreach($dbStudents as $std){
//                    $telo.="    ".$std->name." ".$std->surname.
//                           " ze třídy ".$std->class.
//                           ", jehož vyučující fyziky je ".$std->teacher.
//                           " do kategorie ".$std->category.".\n";
//                }
//                $telo.="Prosíme Vás o kontrolu a v případě potíží nás, prosím, kontaktujte na níže uvedeném emailu.\n".
//                       "Studentům byl též doručen potvrzovací email na jejich emailovou adresu.\n\n".
//                       "Děkujeme za trpělivost.\n".
//                       "S pozdravem\nza KK FO Praha\nVěrný server Otokar\npraha@fyzikalniolympiada.cz".
//                       "\n\nTento email byl vygenerován automaticky.";
//
//                @mail($dbReferent->referent_email, "Potvrzení o přihlášení studentů do FO", $telo, $header);
//                @mail("backup@fyzikalniolympiada.cz", "Potvrzení o přihlášení studentů do FO", $telo, $header);
//                @mail("lukas.ledvina@fyzikalniolympiada.cz", "Potvrzení o přihlášení studentů do FO", $telo, $header);
//// send emails to students
//                foreach($dbStudents as $std){
//                    $telo ="Vážená řešitelko FO, Vážený řešiteli FO,\n\n".
//                           "potvrzujeme, že jste byl/a registrován/a do pražské Fyzikální olympiády.\n\n".
//                           "Váš referent ".$dbReferent->referent_name." o Vás vyplnil následující údaje:\n".
//                           "    Kategorie: ".$std->category.",\n".
//                           "    vyučující fyziky: ".$std->teacher.",\n".
//                           "    třída: ".$std->class.",\n".
//                           "    škola: ".$std->school_show_name." (".$std->school_address."),\n".
//                           "    datum narození: ".substr($std->born_date,0,10).".\n\n".
//                           "Prosíme Vás o kontrolu a v případě nesrovnalostí nás, prosím, kontaktujte na níže uvedeném emailu.\n\n".
//                           "Děkujeme za zájem.\n".
//                           "S pozdravem\nza KK FO Praha\nVěrný server Otokar\npraha@fyzikalniolympiada.cz".
//                           "\n\nTento email byl vygenerován automaticky.";
//                    @mail($std->email, "Potvrzení o přihlášení studenta do FO", $telo, $header);
//                    @mail("backup@fyzikalniolympiada.cz", "Potvrzení o přihlášení studenta do FO", $telo, $header);
//                    @mail("lukas.ledvina@fyzikalniolympiada.cz", "Potvrzení o přihlášení studenta do FO", $telo, $header);//next param
//                }
//// TODO lepší zpráva
//                $step=1;
//                dibi::commit();
//                $this->flashMessage('Přihlášení proběhlo úspěšně, byly rozeslány potvrzovací emaily.','Úspěch');
//                $this->redirect('this');
//            }
//            dibi::commit();
//        }
//
//
//
//
//// send data to template
//        if( $errorMsg != "" )
//            $errorMsg.="<br /> V případě přetrvávajících potíží nás kontaktujte na emailové adrese
//                        <a href='mailto:praha@fyzikalniolympiada.cz'>praha@fyzikalniolympiada.cz</a>.";
//        $this->template->step     =$step;
//        $this->template->stepname =$step_names[$step];
//        $this->template->errorMsg =$errorMsg;
//        $this->template->okMsg    =$okMsg;
//        $this->template->schools  =$schools;
//        $this->template->refName  =$refName;
//        $this->template->refMail  =$refMail;
//        $this->template->refPhone =$refPhone;
//        $this->template->refNum   =$refNum;
//        $this->template->oldNum   =$oldNum;
//        $this->template->refSchool=$refSchool;
//        $this->template->schName  =$schName;
//        $this->template->schAdr   =$schAdr;
//        $this->template->schFull  =$schFull;
//        $this->template->schMail  =$schMail;
//
//        $this->template->rekap    =$rekap;
//        $this->template->refSchoolName=$refSchoolName;
//        //$this->template->schools[0]->selected=true;
//        //bdump($this->template->schools);
////        if($step>=2){
//            $this->template->stdId  =$stdId;
//            $this->template->stdName=$stdName;
//            $this->template->stdSur =$stdSur;
//            $this->template->stdKat =$stdKat;
//            $this->template->stdMail=$stdMail;
//            $this->template->stdBorn=$stdBorn;
//            $this->template->stdClss=$stdClss;;
//            $this->template->stdTeac=$stdTeac;
//            $this->template->oldStd =$oldStd;
////        }
//    }
//
//
//    function checkSchool($refSchool)
//    {
//        $errorMsg="";
//        if($refSchool==NULL)
//            $errorMsg.="Musíte nejprve vyplnit školu.<br />";
//        return $errorMsg;
//    }
//
//    function checkAddSchool($schName,$schAdr,$schFull,$schMail)
//    {
//        $errorMsg="";
//        if($schName=="")
//            $errorMsg.="Zobrazované jméno školy musí být vypněno.<br />";
//        if($schAdr=="")
//            $errorMsg.="Adresa školy musí být vyplněna.<br />";
//        if($schFull=="")
//            $errorMsg.="Úplný název školy musí být vyplněn.<br />";
//        if(!filter_var($schMail, FILTER_VALIDATE_EMAIL))
//            $errorMsg.="Zadaný email není ve validní formě.<br />";
//
//        return $errorMsg;
//    }
//
//    function checkStudents($refNum,$refMail,$stdName,$stdSur,$stdKat,$stdMail,$stdBorn,$stdClss,$stdTeac)
//    {
//        $rocnik=Environment::getVariable('rocnik');
//        $errorMsg="";
//        for($i=0; $i<$refNum; $i++){
//        if($stdName[$i]=="")
//            $errorMsg.="Jméno studenta ".($i+1)." nesmí zůstat prázdné.<br />";
//            if($stdSur[$i]=="")
//                $errorMsg.="Příjmení studenta ".($i+1)." nesmí zůstat prázdné.<br />";
//            if($stdKat[$i]=="")
//                $errorMsg.="Kategorie studenta ".($i+1)." nesmí zůstat prázdná.<br />";
//            if(strcmp($stdMail[$i],"vyplní se z databáze")!=0){
//                if(!filter_var($stdMail[$i], FILTER_VALIDATE_EMAIL)){
//                    $errorMsg.="Zadaný email (".($i+1).".) <i>".$stdMail[$i]."</i> nemá validní formu.<br />";
//                }
//                else{
//                    if($refMail==$stdMail[$i])
//                        $errorMsg.="Email (".($i+1).".) <i>".$stdMail[$i]."</i> se shoduje s Vaším emailem.<br />";
//                    for($j=0; $j<$i; $j++)if($stdMail[$j]==$stdMail[$i])if($stdName[$i] != $stdName[$j] || $stdSur[$i] != $stdSur[$j])
//                        $errorMsg.="Zadaný email (".($i+1).".) <i>".$stdMail[$i]."</i> je již použit pro studenta ".($j+1).".<br />";
//                }
//                $emails=dibi::query("SELECT name, surname,email FROM `participants`
//                    JOIN referents USING(referent_id)
//                    JOIN school USING(school_id)
//                    WHERE rocnik=%i AND kraj_id=%s AND email=%s AND category=%s;",
//                    $rocnik,$this::getKrajId(),$stdMail[$i],$stdKat[$i])->fetchAll();
//                if($emails!=NULL)if($emails[0]!=NULL){
//                    $errorMsg.="Na emailovou adresu <i>".$stdMail[$i]."</i> je tento rok do kategorie ".$stdKat[$i]."
//                        již registrován student ".$emails[0]->name." ".$emails[0]->surname.".<br />";
//                }
//            }
//            if(strcmp($stdMail[$i],"vyplní se z databáze")!=0){
//                if(!preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2})/", $stdBorn[$i])){
//                    $errorMsg.="Datum narození studenta ".($i+1)." není ve formátu RRRR-MM-DD.<br />";
//                }
//                else{
//                    $bYea=substr($stdBorn[$i],0,4);
//                    $bMnt=substr($stdBorn[$i],5,2);
//                    $bDay=substr($stdBorn[$i],8,2);
//                    if(!checkdate($bMnt,$bDay,$bYea))
//                        $errorMsg.="Datum narození studenta ".($i+1)." <i>".$bDay.". ".$bMnt.". ".$bYea."</i> není v kalendáři.<br />";
//                    if($bYea<1933+$rocnik || $bYea>1953+$rocnik)
//                        $errorMsg.="Věk studenta ".($i+1)." není v rozmezí 5-25 let.<br />";
//                }
//            }
//            if($stdClss[$i]=="")
//                $errorMsg.="Třídu studenta ".($i+1)." je potřeba vyplnit.<br />";
//            if($stdTeac[$i]=="")
//                $errorMsg.="Jméno vyučujícího fyziky studenta ".($i+1)." je potřeba vyplnit.<br />";
//        }
//        return $errorMsg;
//    }
//
//
//
//
//
//
//    function getKrajId()
//    {
//        return dibi::query('SELECT kraj_id FROM kraj WHERE webpage=%s',$this::getSubdomain())->fetchAll()[0]->kraj_id;
//        return 1;
//    }
//    function getSchools()
//    {
//        return dibi::query('SELECT school_id,school_choice, "" AS selected
//            FROM school
//            WHERE kraj_id=1
//            ORDER BY school_choice;')->fetchAll();
//        return dibi::query('SELECT school_id,school_choice, "no" AS selected FROM school WHERE kraj_id=%d;',$this::getKrajId())->fetchAll();
//        //return dibi::query('SELECT school_id,school_choice FROM school WHERE kraj_id=%d',$this::getKrajId())->fetchAll();
//    }
//}
//
