<?php

use Adianti\Database\TRecord;

/**
 * Ttg010 Active Record
 * @author  <your-name-here>
 */
class Zcn010 extends TRecord // ETAPAS DA INSPECAO DE AUDITORIA
{
    const TABLENAME = 'ZCN010';
    const PRIMARYKEY= 'R_E_C_N_O_';
    const IDPOLICY =  'max'; // {max, serial}
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('ZCN_FILIAL');  // C - 10 - CODIGO DA FILIAL
        parent::addAttribute('ZCN_DOC'   );  // C -  6 - NUMERO DO DOCUMENTO DE AUDITORIA
        parent::addAttribute('ZCN_ETAPA' );  // C -  6 - CODIGO DA ETAPA
        parent::addAttribute('ZCN_SEQ'   );  // C -  3 - SEQUENCIAL DO ITEM
        parent::addAttribute('ZCN_NAOCO' );  // C -  1 - NAO CONFORMIDADE S/N
        parent::addAttribute('ZCN_SCORE' );  // N -  1 - SCORE DA ETAPA
        parent::addAttribute('ZCN_FOTO'  );  // M - 10 - STRING DA FOTO
        parent::addAttribute('ZCN_OBS'   );  // M - 10 - OBSERVACAO DO ITEM DA AUDITORIA
        parent::addAttribute('D_E_L_E_T_');
        parent::addAttribute('R_E_C_D_E_L_');
    }

}
