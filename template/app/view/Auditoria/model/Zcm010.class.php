<?php

use Adianti\Database\TRecord;

/**
 * Ttf010 Active Record
 * @author  <your-name-here>
 */
class Zcm010 extends TRecord // INSPEÇÕES DE AUDITORIA
{
    const TABLENAME = 'ZCM010';
    const PRIMARYKEY= 'R_E_C_N_O_';
    const IDPOLICY =  'max'; // {max, serial}
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('ZCM_FILIAL');
        parent::addAttribute('ZCM_DOC'   ); // C -  6 - NUMERO DO DOCUMENTO
        parent::addAttribute('ZCM_TIPO'  ); // C -  3 - CODIGO DO TIPO
        parent::addAttribute('ZCM_DATA'  ); // D -  8 - DATA DE LANCAMENTO
        parent::addAttribute('ZCM_HORA'  ); // C -  5 - HORA DE LANCAMENTO
        parent::addAttribute('ZCM_OBS'   ); // M - 10 - OBSERVAÇÃO DA INSPEÇÃO
        parent::addAttribute('ZCM_USUGIR'); // C - 30 - USUARIO QUE REGISTROU A INSPEÇÃO
        parent::addAttribute('D_E_L_E_T_');
        parent::addAttribute('R_E_C_D_E_L_');
    }

}
