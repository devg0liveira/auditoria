<?php

use Adianti\Database\TRecord;

class livro extends TRecord
{
    const TABLENAME = 'livro';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        $this->addAttribute('titulo');
        $this->addAttribute('autor');
        $this->addAttribute('isbn');
        $this->addAttribute('sinopse');
        $this->addAttribute('capa_url');
        $this->addAttribute('tags');
        $this->addAttribute('status');
        $this->addAttribute('quantidade');
    }
}