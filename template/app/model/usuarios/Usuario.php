<?php

use Adianti\Database\TRecord;

class Usuario extends TRecord
{
    const TABLENAME  = 'usuarios';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = null)
    {
        parent::__construct($id);

        parent::addAttribute('nome');
        parent::addAttribute('email');
        parent::addAttribute('criado_em');
    }
}
