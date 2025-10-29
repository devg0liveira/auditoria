<?php

use Adianti\Database\TRecord;
// app/model/ZCK010.php
class ZCK010 extends TRecord
{
    const TABLENAME = 'ZCK010';
    const PRIMARYKEY = 'R_E_C_N_O_';
    const IDPOLICY = 'serial';

    public function get_etapas()
    {
        return ZCJ010::where('ZCJ_TIPO', '=', $this->ZCK_TIPO)->load();
    }
}