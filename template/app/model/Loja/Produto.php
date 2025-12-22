<?php

use Adianti\Database\TRecord;

class Produto extends TRecord
{
    const TABLENAME  = 'produto';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = null)
    {
        parent::__construct($id);

        parent::addAttribute('id');
        parent::addAttribute('nome');
        parent::addAttribute('descricao');
        parent::addAttribute('preco');
        parent::addAttribute('estoque');
        parent::addAttribute('categoria');
        parent::addAttribute('ativo');
    }
}
