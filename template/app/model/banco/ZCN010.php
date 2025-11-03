<?php
use Adianti\Database\TRecord;

class ZCN010 extends TRecord
{
    const TABLENAME  = 'ZCN010';
    const PRIMARYKEY = 'R_E_C_N_O_';
    const IDPOLICY   = 'max'; // Usa o maior valor + 1

    public function __construct($id = NULL)
    {
        parent::__construct($id);

        parent::addAttribute('ZCN_FILIAL');
        parent::addAttribute('ZCN_DOC');
        parent::addAttribute('ZCN_ETAPA');
        parent::addAttribute('ZCN_SEQ');
        parent::addAttribute('ZCN_NAOCO');
        parent::addAttribute('ZCN_SCORE');
        parent::addAttribute('ZCN_FOTO');
        parent::addAttribute('ZCN_OBS');
        parent::addAttribute('D_E_L_E_T_');
        PARENT::addAttribute('R_E_C_N_O');
        parent::addAttribute('R_E_C_D_E_L_');
        parent::addAttribute('ZCN_ACAO');
        parent::addAttribute('ZCN_RESP');
        parent::addAttribute('ZCN_PRAZO');
        parent::addAttribute('ZCN_STATUS');
        parent::addAttribute('ZCN_DATA_EXEC');
    }
}
