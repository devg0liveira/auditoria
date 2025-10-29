<?php

use Adianti\Database\TRecord;
// app/model/ZCM010.php (Cabeçalho)
class ZCM010 extends TRecord
{
    const TABLENAME = 'ZCM010';
    const PRIMARYKEY = 'R_E_C_N_O_';

    public function get_respostas()
    {
        return ZCN010::where('ZCN_DOC', '=', $this->ZCM_DOC)->load();
    }
}