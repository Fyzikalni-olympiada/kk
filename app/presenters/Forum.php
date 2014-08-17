<?php
namespace Eywa\CMS\Presenters;

use Eywa\CMS\Navigation;
use dibi;

/**
 * @title Forum
 * @model Eywa\CMS\Model\Page
 */
//class ResultsOverviewDatabase extends Eywa\CMS\Presenters\Overview
class Forum extends Overview
{
//    private $database;
    private $posts;

    public function renderDefault($path)
    {
        $this->template->titleline = "";
        $error="";
        $vnoreni=0;
        if(!isset($_GET['page'])) $_GET['page']=1;

        if(isset($_GET['reply'])){
            $_POST['reply']=$_GET['reply'];
//            $error="Reakce";
			    $this->template->titleline="Re: ".
              dibi::query('SELECT * FROM forum_kraj WHERE id=%i',$_POST['reply'])->fetchAll()[0]->title;
            $vnoreni=dibi::query('SELECT * FROM forum_kraj WHERE id=%i',$_POST['reply'])->fetchAll()[0]->vnoreni+1;
        }
        else{
          $_POST['reply']=0;
        }
        if(isset($_POST['ok'])){
            if($_POST['kontrola']==="2"){
                if($_POST['name']==="") $_POST['name']="<i>anonym</i>";
                $arr = array(
                  'name'  => $_POST['name'],
                  'email' => $_POST['email'],
                  'title' => $_POST['titleline'],
                  'text'  => $_POST['text'],
                  'vnoreni'     => $vnoreni,
                  'reakcena_id' => $_POST['reply'],
                  'who'         => "student",
                  'kraj_id'     => $this::getKrajId()
                );
                dibi::query('INSERT INTO forum_kraj', $arr);
            }
            else{
                $error="Chybná kontrola.";
            }
            $this->redirect('this');
        }
        parent::renderDefault($path);

        $posts=Array();
        $posts=$this::addPost($posts,0);
        foreach($posts as $item){
            $item->lmargin=30*$item->vnoreni;
            $item->datetime=$item->datetime;
        }

        $this->template->first_page = 1;
        $this->template->prev_page = $_GET['page']-1;
        $this->template->next_page = $_GET['page']+1;
        $this->template->last_page = $this::getTotalPage($posts);

        if($this->template->prev_page < $this->template->first_page)  $this->template->prev_page= $this->template->first_page;
        if($this->template->next_page > $this->template->last_page)  $this->template->prev_page= $this->template->last_page;
        $this->template->error = $error;
        $this->template->posts = $this::getPage($_GET['page'],$posts);
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
}
