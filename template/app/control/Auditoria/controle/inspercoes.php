<?php
// app/control/Auditoria/InspecoesList.php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Widget\Container\THBox;

class inspercoes extends TPage
{
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        // Cria o datagrid com wrapper Bootstrap
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';

        // === DEFINIÇÃO DAS COLUNAS ===
        $col_id       = new TDataGridColumn('R_E_C_N_O_', 'ID', 'center', '8%');
        $col_tipo     = new TDataGridColumn('ZCK_TIPO', 'Tipo', 'center', '12%');
        $col_descri   = new TDataGridColumn('ZCK_DESCRI', 'Descrição', 'left', '50%');
        $col_resp     = new TDataGridColumn('ZCK_RESP', 'Resp.', 'center', '15%');
        $col_status   = new TDataGridColumn('status_formatado', 'Status', 'center', '15%');

        // Adiciona as colunas ao grid
        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_descri);
        $this->datagrid->addColumn($col_resp);
        $this->datagrid->addColumn($col_status);

        


    }
}

 /*
    
Almoxarifado

Entradas de notas fiscais 

segurança do trabalho deposito de ferramentas operacionais de rotas 

controle de combustivel interno e externo 

ordem e serviço

vistoria de veiculos 

manutenção 

boletim de medição 

departamento pessoal

folha de pagamento 

férias 

estagiário / jovem aprendiz 
*/