<?php
use Adianti\Database\TRecord;

class ZCJ010 extends TRecord
{
    const TABLENAME  = 'ZCJ010';
    const PRIMARYKEY = 'R_E_C_N_O_';
    const IDPOLICY   = 'max'; 

    public function __construct($id = NULL)
    {
        parent::__construct($id);

        parent::addAttribute('ZCJ_FILIAL');
        parent::addAttribute('ZCJ_ETAPA');
        parent::addAttribute('ZCJ_DESCRI');
        parent::addAttribute('ZCJ_USUGIR');
        parent::addAttribute('ZCJ_DATA');
        parent::addAttribute('ZCJ_HORA');
        parent::addAttribute('D_E_L_E_T_');
        parent::addAttribute('R_E_C_D_E_L_');
    }
}
