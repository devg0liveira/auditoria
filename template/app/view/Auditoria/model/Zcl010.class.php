<?php

use Adianti\Database\TRecord;

/**
 * Tte010 Active Record
 * @author  <your-name-here>
 */
class Zcl010 extends TRecord // ETAPAS DO CHECKLIST PADRÃO
{
    const TABLENAME = 'ZCL010';
    const PRIMARYKEY= 'R_E_C_N_O_';
    const IDPOLICY =  'max'; // {max, serial}
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('ZCL_FILIAL'); // C - 10 - CODIGO DA FILIAL
        parent::addAttribute('ZCL_TIPO'  ); // C -  3 - TIPO DO CHECKLIST PADRAO
        parent::addAttribute('ZCL_SEQ'   ); // C -  3 - SEQUENCIAL DA ETAPA
        parent::addAttribute('ZCL_ETAPA' ); // C -  6 - CODIGO DA ETAPA GENERICA
        parent::addAttribute('ZCL_SCORE' ); // N -  1 - DIGITO PARA O SCORE 
        parent::addAttribute('ZCL_USUGIR'); // C - 30 - USUARIO QUE ADICIONOU
        parent::addAttribute('ZCL_DATA'  ); // D -  8 - DATA DE INCLUSAO
        parent::addAttribute('ZCL_HORA'  ); // C -  5 - HORA DE INCLUSAO
        parent::addAttribute('D_E_L_E_T_');
        parent::addAttribute('R_E_C_D_E_L_');
    }

}
