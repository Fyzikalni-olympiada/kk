<?php

// TODO 
//   * email notifikace
//   * možnost odhlášky notifikace (+sloupec tabulky)

namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;
use dibi;

/**
 * @title Forum
 * @model Eywa\CMS\Model\Page
 */

class Forum extends Overview
{
    private $posts;

    public function renderDefault($path)
    {
        $this->template->titleline = "";
        $errorMsg="";
        $okMsg="";
        $vnoreni=0;
        if(!isset($_GET['page']))   $_GET['page']=1;
        if(!isset($_POST['reply'])) $_POST['reply']=0;

        if(isset($_GET['reply'])){
            $reply=$_GET['reply'];
            $okMsg="Reakce";
            $this->template->titleline="Re: ".
              dibi::query('SELECT * FROM forum_kraj WHERE id=%i',$reply)->fetch()['title'];
        }
        else{
            $reply=$_POST['reply'];
        }
        if($reply!=0)
            $vnoreni=dibi::query('SELECT * FROM forum_kraj WHERE id=%i',$reply)->fetch()['vnoreni']+1;
        if(isset($_POST['ok'])){
            if(!($_POST['kontrola']==="2"))
                $errorMsg.="Jedna plus jedna není ".$_POST['kontrola']."!<br />";
            if (empty($_POST["titleline"]))
                $errorMsg.= "Chybí nadpis novinky!<br />";
            if ( $err = $this::spam($_POST['text'], $_POST['name'], $_POST['titleline'], $_POST['email']) )
                $errorMsg.= $err;

            if ($errorMsg == "") {
                $zaloha_text = $_POST['text'];

                $_POST['name'] = $this::removeTag($_POST['name']);
                $_POST['titleline'] = $this::removeTag($_POST['titleline']);
                $_POST['text'] = $this::removeTag($_POST['text']);

                if($_POST['name']==="") 
                    $_POST['name']="<i>anonym</i>";
                if ((empty($_POST["email"])) || !preg_match("/^[[:graph:]]+@[[:graph:]]+(\.[[:graph:]]{2,})$/", $_POST["email"]))
                    $_POST["email"] = null;

                $arr = array(
                  'name'  => $_POST['name'],
                  'email' => $_POST['email'],
                  'title' => $_POST['titleline'],
                  'text'  => $_POST['text'],
                  'vnoreni'     => $vnoreni,
                  'reakcena_id' => $reply,
                  'who'         => "student",
                  'kraj_id'     => $this::getKrajId()
                );
                dibi::query('INSERT INTO forum_kraj', $arr);
                $okMsg.="Prispevek pridan.<br />";
 
 /* email */
//                $telo = 'Na ' . SERVER_NAME . ' je novy diskusni prispevek od ' . $_POST['name'] . ' (' . $_POST['email'] . "):\n\n" . $_POST['title'] . "\n\n" . $zaloha_text . "\n\nhttp://" . SERVER_NAME . rtrim(ROOT_WWW, '/') . odkaz2('content/forum.php', array('forum_id' => $forum_last_id, 'sort' => 'vlakno', 'news_id' => $GLOBALS['news_id']), 0);

/*                $GLOBALS['mysql']->query('
                    SELECT email
                    FROM ' . TABLE_USERS . '
                    WHERE send_forum="1"
                ');
                while ($row = $GLOBALS['mysql']->fetch_array()) {
                    @mail($row['email'], 'Novy diskusni prispevek na ' . SERVER_NAME, $telo, MAIL_HEADERS);
                }
*/

                /** Reakce na jiny prispevek, tak pošleme email */
/*                if (!empty($reakcena_id)) {
                    $GLOBALS['mysql']->query("
                        SELECT name, email, title, datum_cas
                        FROM " . VIEW_FORUM . "
                        WHERE id=" . db::escape_string($reakcena_id)
                    );
                    $row = $GLOBALS['mysql']->fetch_array();
                    if (!is_null($row['email'])) {
                        $datum = explode('-',$row["date"]);
                        $telo = "Dobry den " . $row["name"] . ".\n\nV diskusnim foru na " . SERVER_NAME . " byla pridana nova reakce na Vas pripevek \"" . $row["title"] . "\" z " . $row['datum_cas'] . "\n\nhttp://" . SERVER_NAME . ROOT_WWW . odkaz2('content/forum.php', array('forum_id' => $forum_last_id, 'sort' => 'vlakno', 'news_id' => $GLOBALS['news_id']), 0);
                        @mail($row['email'], "Reakce na vas prispevek ve foru " . SERVER_NAME, $telo, MAIL_HEADERS);
                    }
                }*/

            }
        }

        $posts=Array();
        $posts=$this::addPost($posts,0);
        foreach($posts as $item){
            $item->lmargin=30*$item->vnoreni;
            $item->datetime=$item->datetime;
        }

        $this->template->frst_page = 1;
        $this->template->prev_page = $_GET['page']-1;
        $this->template->next_page = $_GET['page']+1;
        $this->template->last_page = $this::getTotalPage($posts);

        if($this->template->prev_page < $this->template->frst_page)  $this->template->prev_page= $this->template->frst_page;
        if($this->template->next_page > $this->template->last_page)  $this->template->prev_page= $this->template->last_page;
        $this->template->errorMsg = $errorMsg;
        $this->template->okMsg    = $okMsg;
        $this->template->reply=$reply;
        $this->template->posts    = $this::getPage($_GET['page'],$posts);
    }

    private function addPost($posts, $par_id)
    {
        if($par_id==0)$results=dibi::query('SELECT * FROM forum_kraj WHERE reakcena_id=%i AND kraj_id=%i ORDER BY id DESC',$par_id,$this::getKrajId());
        else          $results=dibi::query('SELECT * FROM forum_kraj WHERE reakcena_id=%i AND kraj_id=%i ORDER BY id',$par_id,$this::getKrajId());
        $post=$results->fetchAll();
        foreach($post as $item){
            $posts[] = $item;
            $posts=$this::addPost($posts,$item->id);
        }
       return $posts;
    }

    function getPage($pageNo,$posts)
    {
        $PPP=15; // posts per page
        $pageAkt=1;
        $postOnPage=0;
        $ret=Array();
        foreach($posts as $post){
            $postOnPage++;
            if($postOnPage>$PPP && $post->vnoreni==0){
                $postOnPage=0;
                $pageAkt++;
            }
            if($pageNo==$pageAkt)
                $ret[]=$post;
        }
        return $ret;
    }

    function getTotalPage($posts)
    {
        $PPP=15; // posts per page
        $pageAkt=1;
        $postOnPage=0;
        $ret=Array();
        foreach($posts as $post){
            $postOnPage++;
            if($postOnPage>$PPP && $post->vnoreni==0){
                $postOnPage=0;
                $pageAkt++;
            }
        }
        return $pageAkt;
    }

    function getKrajId()
    {
        return dibi::query('SELECT kraj_id FROM kraj WHERE webpage=%s',$this::getSubdomain())->fetchAll()[0]->kraj_id;
        return 1;
    }

    function spam($text, $name, $titleline, $mail)
    {
        $r = "";
        $text = $text . " " .  $name . " " . $titleline;
        $t_spam = $text . " " .  $name;
        $text = strtolower($text);
        $text = str_replace("ě", "e", $text);
        $text = str_replace("š", "s", $text);
        $text = str_replace("č", "c", $text);
        $text = str_replace("ř", "r", $text);
        $text = str_replace("ž", "z", $text);
        $text = str_replace("ý", "y", $text);
        $text = str_replace("á", "a", $text);
        $text = str_replace("í", "i", $text);
        $text = str_replace("é", "e", $text);
        $text = str_replace("ú", "u", $text);
        $text = str_replace("ů", "u", $text);
        $text = str_replace("ť", "t", $text);
        $text = str_replace("ň", "n", $text);
        $text = str_replace("ď", "d", $text);
//        if (empty($name)) {
//            $r .= "Uveďte prosím Vaše jméno!<br />";
//        }
        /** Email nám nevadí, když není vyplněný. */
        if (!empty($mail) &&!EReg("^[[:graph:]]+@[[:graph:]]+(\.[[:graph:]]{2,})$", $mail)) {
            $r .= "Váš e-mail má neplatný formát!<br />";
        }
        if (strlen($text) < 3) {
            $r .= "Váš text je příliš krátký!<br />";
        }
        if (strlen($text) > 5000) {
            $r .= "Váš text je příliš dlouhý!<br />";
        }
        if (stristr($text, "kurv") || strstr($text, "prdel") || strstr($text, "pica") || strstr($text, "pice") || strstr($text, "pici") || strstr($text, "hajzl") || strstr($text, "debil") || strstr($text, "kokot") || strstr($text, "curak") || strstr($text, "kreten") || strstr($text, "srack") || strstr($text, "srat") || strstr($text, "serte") || strstr($text, "serou") || strstr($text, "srany") || strstr($text, "srani") || strstr($text, "srane") || strstr($text, "kund") || strstr($text, "curac") || strstr($text, "jebat") || strstr($text, "jeban") || strstr($text, "kurev") || strstr($text, "sukat") || strstr($text, "hovn") || strstr($text, "mrd") || strstr($text, "pazdrat") || strstr($text, "sragor") || strstr($text, "prdol") || strstr($text, "chuj") || strstr($text, "klatic") || strstr($text, "honimir") || strstr($text, "hulibrk") || strstr($text, "chcat") || strstr($text, "chcan") || strstr($text, "sulin")) {
            $r .= "Nepoužívejte prosím v textu sprostá slova!<br />";
        }
//        return "Pokus";
        return $r;
    }

    function vlnka($data)
    {
        $data = str_replace(' - ', "&nbsp;&ndash; ", $data);
        $data = str_replace('--' , "&mdash; ", $data);
        $data = str_replace(' k ', " k&nbsp;", $data);
        $data = str_replace(' s ', " s&nbsp;", $data);
        $data = str_replace(' v ', " v&nbsp;", $data);
        $data = str_replace(' u ', " u&nbsp;", $data);
        $data = str_replace(' o ', " o&nbsp;", $data);
        $data = str_replace(' a ', " a&nbsp;", $data);
        $data = str_replace(' i ', " i&nbsp;", $data);
        $data = str_replace(' K ', " K&nbsp;", $data);
        $data = str_replace(' S ', " S&nbsp;", $data);
        $data = str_replace(' V ', " V&nbsp;", $data);
        $data = str_replace(' U ', " U&nbsp;", $data);
        $data = str_replace(' O ', " O&nbsp;", $data);
        $data = str_replace(' A ', " A&nbsp;", $data);
        $data = str_replace(' I ', " I&nbsp;", $data);
        return $data;
    }

    function odstranProblemy($data)
    {
        if ( ini_get('magic_quotes_gpc') ) {
        	if (is_array($data)) {
        		$data = array_map('stripslashes',$data);
        	} else {
            	$data = stripslashes($data);
        	}
        }
        if (is_array($data)) {
        	return array_map('trim',$data);
        } else {
        	return trim($data);
        }
    }

    function removeTag($data)
    {
        $regex  = '@</?\w+((\s+\w+(\s*=\s*';
        $regex .= '(?:".*?"|\'.*?\'|[^\'">\s]+))?)+';
        $regex .= '\s*|\s*)/?>@i';
        $data = preg_replace($regex, '', $data);
        $data = preg_replace( "`((http)+(s)?:(//)|(www\.))((\w|\.|\-|_)+)(/)?(\S+)?`i", "<a href=\"http\\3://\\5\\6\\8\\9\" title=\"\\0\">\\5\\6\\8\\9</a>", $data);
        $data = ucfirst($this::vlnka($data));
        $data = str_replace("\r\n","<br />",$data);
        $data = $this::odstranProblemy($data);
        return $data;
    }
}

