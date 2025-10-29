<?php

use Adianti\Database\TRecord;

class ZCJ010 extends TRecord
{
    const TABLENAME = 'ZCJ010';
    const PRIMARYKEY = 'R_E_C_N_O_';

    public function get_itens()
    {
        return ZCL010::where('ZCL_TIPO', '=', $this->ZCJ_TIPO)
                     ->where('ZCL_ETAPA', '=', $this->ZCJ_ETAPA)
                     ->orderBy('ZCL_SEQ')
                     ->load();
    }
}