<?php
namespace Eywa\CMS\Model;



/**
 * @title Web krajské komise
 */
class Komise extends Link
{

    /**
     * @editable (tab=Vlastnosti, index=10, control=Text, label="Podtitulek", description="Podtitulek se bude zobrazovat v hlavičce na každé stránce.")
     * @var string
     */
	public $subtitle;
    
}
