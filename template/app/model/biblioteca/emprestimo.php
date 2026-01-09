<?php

use Adianti\Database\TRecord;

class emprestimo extends TRecord{
    const TABLENAME = 'emprestimo';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        $this->addAttribute('livro_id');
        $this->addAttribute('usuario_id');
        $this->addAttribute('data_emprestimo');
        $this->addAttribute('data_prevista');
        $this->addAttribute('data_devolucao');
        $this->addAttribute('status');
    }
}