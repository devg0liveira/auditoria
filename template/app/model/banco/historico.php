<?php

use Adianti\Database\TRecord;

class historico extends TRecord{
    const TABLENAME  = 'historico';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial'; // ou 'max' se o banco não for auto incremento

    public function __construct($id = NULL)
    {
        parent::__construct($id);
        parent::addAttribute('filial');
        parent::addAttribute('tipo');
        parent::addAttribute('data_atualizacao');
        parent::addAttribute('status');
    }
}