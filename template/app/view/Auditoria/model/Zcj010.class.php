<?php

use Adianti\Database\TRecord;

/**
 * Tpa010 Active Record
 * @author  <your-name-here>
 */
class Zcj010 extends TRecord // ETAPAS GENERICAS PARA INSPEÇÃO DA AUDITORIA
{
    const TABLENAME = 'ZCJ010';
    const PRIMARYKEY= 'R_E_C_N_O_';
    const IDPOLICY =  'max'; // {max, serial}
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('ZCJ_FILIAL'); // C - 10 - CODIGO DA FILIAL
        parent::addAttribute('ZCJ_ETAPA' ); // C -  6 - CODIGO DA ETAPA
        parent::addAttribute('ZCJ_DESCRI'); // M - 10 - DESCRICAO DA ETAPA
        parent::addAttribute('ZCJ_USUGIR'); // C - 30 - USUARIO QUE ADICIONOU
        parent::addAttribute('ZCJ_DATA'  ); // C -  8 - DATA DE INSERÇÃO
        parent::addAttribute('ZCJ_HORA'  ); // C -  5 - HORA DE INSERÇÃO
        parent::addAttribute('D_E_L_E_T_'); 
        parent::addAttribute('R_E_C_D_E_L_');
    }

    public function checkNULL()
    {
        $this->ZCJ_FILIAL   = ($this->ZCJ_FILIAL   == NULL ? str_pad(' ',1)   : $this->ZCJ_FILIAL  );
        $this->ZCJ_ETAPA    = ($this->ZCJ_ETAPA    == NULL ? str_pad(' ',1)   : $this->ZCJ_ETAPA   );
        $this->ZCJ_DESCRI   = ($this->ZCJ_DESCRI   == NULL ? str_pad(' ',1)   : $this->ZCJ_DESCRI  );
        $this->ZCJ_USUGIR   = ($this->ZCJ_USUGIR   == NULL ? str_pad(' ',1)   : $this->ZCJ_USUGIR  );
        $this->ZCJ_DATA     = ($this->ZCJ_DATA     == NULL ? str_pad(' ',1)   : $this->ZCJ_DATA    );
        $this->ZCJ_HORA     = ($this->ZCJ_HORA     == NULL ? str_pad(' ',1)   : $this->ZCJ_HORA    );

        $this->D_E_L_E_T_   = ($this->D_E_L_E_T_   == NULL ? ' '              : $this->D_E_L_E_T_  );
        $this->R_E_C_D_E_L_ = ($this->R_E_C_D_E_L_ == NULL ? 0                : $this->R_E_C_D_E_L_);
    }        
}
