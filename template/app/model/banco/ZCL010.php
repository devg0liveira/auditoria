<?php
use Adianti\Database\TRecord;

class ZCL010 extends TRecord
{
    const TABLENAME  = 'ZCL010';
    const PRIMARYKEY = 'R_E_C_N_O_';
    const IDPOLICY   = 'max'; // Usa o maior valor + 1

    public function __construct($id = NULL)
    {
        parent::__construct($id);

        parent::addAttribute('ZCL_FILIAL');
        parent::addAttribute('ZCL_TIPO');
        parent::addAttribute('ZCL_SEQ');
        parent::addAttribute('ZCL_ETAPA');
        parent::addAttribute('ZCL_SCORE');
        parent::addAttribute('ZCL_USUGIR');
        parent::addAttribute('ZCL_DATA');
        parent::addAttribute('ZCL_HORA');
        parent::addAttribute('D_E_L_E_T_');
        PARENT::addAttribute('R_E_C_N_O_');
        parent::addAttribute('R_E_C_D_E_L_');
    }
}
