<?php

use Adianti\Database\TRecord;

/**
 * Ttd010 Active Record
 * @author  <your-name-here>
 */
class Zck010 extends TRecord // CHECKLIST PADRÃO DE AUDITORIA
{
    const TABLENAME = 'ZCK010';
    const PRIMARYKEY= 'R_E_C_N_O_';
    const IDPOLICY =  'max'; // {max, serial}
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('ZCK_FILIAL'); // C - 10 - CODIGO DA FILIAL
        parent::addAttribute('ZCK_TIPO'  ); // C -  3 - TIPO DE AUDITORIA 
        parent::addAttribute('ZCK_DESCRI'); // C - 70 - DESCRICAO DA AUDITORIA
        parent::addAttribute('ZCK_USUGIR'); // C - 30 - USUARIO QUE ADICIONOU
        parent::addAttribute('ZCK_DATA'  ); // D -  8 - DATA DE INCLUSAO
        parent::addAttribute('ZCK_HORA'  ); // C -  5 - HORA DE INCLUSAO
        parent::addAttribute('D_E_L_E_T_');
        parent::addAttribute('R_E_C_D_E_L_');
    }

}
