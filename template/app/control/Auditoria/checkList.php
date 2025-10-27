<?php

use Adianti\Control\TPage;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;

class checkList extends TPage{
    public function __construct(){
        parent::__construct();

        $this->form = new BootstrapFormBuilder;
        $this->form->setFormTitle('Auditoria');

        



        parent::add($this->form);
    }
}