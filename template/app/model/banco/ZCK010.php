<?php
use Adianti\Database\TRecord;

class ZCK010 extends TRecord
{
    const TABLENAME  = 'ZCK010';
    const PRIMARYKEY = 'R_E_C_N_O_';
    const IDPOLICY   = 'max';

    public function __construct($id = NULL)
    {
        parent::__construct($id);

        parent::addAttribute('ZCK_FILIAL');
        parent::addAttribute('ZCK_TIPO');
        parent::addAttribute('ZCK_DESCRI');
        parent::addAttribute('ZCK_USUGIR');
        parent::addAttribute('ZCK_DATA');
        parent::addAttribute('ZCK_HORA');
        parent::addAttribute('D_E_L_E_T_');
        parent::addAttribute('R_E_C_D_E_L_');
    }
}
