<?php
use Adianti\Database\TRecord;

class ZCM010 extends TRecord
{
    const TABLENAME  = 'ZCM010';
    const PRIMARYKEY = 'R_E_C_N_O_';
    const IDPOLICY   = 'max'; // Usa o maior valor + 1

    public function __construct($id = NULL)
    {
        parent::__construct($id);

        parent::addAttribute('ZCM_FILIAL');
        parent::addAttribute('ZCM_DOC');
        parent::addAttribute('ZCM_TIPO');
        parent::addAttribute('ZCM_DATA');
        parent::addAttribute('ZCM_HORA');
        parent::addAttribute('ZCM_OBS');
        parent::addAttribute('ZCM_USUGIR');
        parent::addAttribute('D_E_L_E_T_');
        PARENT::addAttribute('R_E_C_N_O');
        parent::addAttribute('R_E_C_D_E_L_');
    }
}
