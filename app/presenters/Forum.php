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

        if(isset($_GET['reply'])){
            $error="Reakce";
               $this->template->titleline="Re: ".
                 dibi::query('SELECT * FROM forum_praha WHERE id=%i',$_GET['reply'])->fetchAll()[0]->title;
        }
        else{
          $_GET['reply']=0;
        }
        if(isset($_GET['vnoreni'])){
            $_GET['vnoreni']++;
        }
        else{
            $_GET['vnoreni']=0;
        }


        if(isset($_POST['ok'])){
            if($_POST['kontrola']==="2"){
                $arr = array(
                  'name'  => $_POST['name'],
                  'email' => $_POST['email'],
                  'title' => $_POST['titleline'],
                  'text'  => $_POST['text'],
                  'date'  => date("Y-m-d"),
                  'time'  => date("H:i:s"),
                  'vnoreni'     => $_GET['vnoreni'],
                  'reakcena_id' => $_GET['reply']
                );
                dibi::query('INSERT INTO forum_praha', $arr);
            }
            else{
                $error="Chybná kontrola";
            }
        }
        $this->template->error = $error;

        parent::renderDefault($path);
        $posts=Array();

	

        $posts=$this::addPost($posts,0);
        foreach($posts as $item){
            $item->lmargin=30*$item->vnoreni;
            $item->datetime=substr($item->date,0,10)." ".substr($item->time,11,8);
        }

        $this->template->posts = $posts;
    }
    private function addPost($posts, $par_id)
    {
        if($par_id==0)$results=dibi::query('SELECT * FROM forum_praha WHERE reakcena_id=%i ORDER BY id DESC',$par_id);
        else          $results=dibi::query('SELECT * FROM forum_praha WHERE reakcena_id=%i ORDER BY id',$par_id);
        $post=$results->fetchAll();
        foreach($post as $item){
            $posts[] = $item;
            $posts=$this::addPost($posts,$item->id);
        }
       return $posts;
    }
}
