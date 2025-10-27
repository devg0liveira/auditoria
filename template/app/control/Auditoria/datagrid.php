<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Wrapper\BootstrapDatagridWrapper;

class Datagrid extends TPage
{
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        // ✅ Usa o BootstrapDatagridWrapper para o layout visual moderno
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);

        // Define as colunas
        $col_id     = new TDataGridColumn('id', 'ID', 'center', '10%');
        $col_filial = new TDataGridColumn('filial', 'Filial', 'left', '30%');
        $col_tipo   = new TDataGridColumn('tipo', 'Tipo', 'left', '30%');
        $col_data   = new TDataGridColumn('data_atualizacao', 'Atualizado em', 'center', '30%');

        // Adiciona colunas ao grid
        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_filial);
        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_data);

        // ✅ Cria ações (botões)
        $action_edit = new TDataGridAction(['Etapa1Form', 'onEdit'], ['id' => '{id}']);
        $action_edit->setLabel('Editar');
        $action_edit->setImage('fa:edit blue');

        // ✅ Adiciona a ação diretamente ao DataGrid (não existe addActionColumn)
        $this->datagrid->addAction($action_edit);

        // ✅ Cria o modelo da grid (estrutura visual)
        $this->datagrid->createModel();

        // ✅ Painel
        $panel = new TPanelGroup('Histórico de Avaliações');
        $panel->add($this->datagrid);

        parent::add($panel);
    }

    /**
     * Método chamado automaticamente ao abrir a página
     */
    public function onReload()
    {
        try
        {
            TTransaction::open('auditoria'); // nome do auditoria em databases.ini

            $repository = new TRepository('Historico');
            $criteria   = new TCriteria;
            $criteria->setProperty('order', 'id desc'); // ordenar por id desc

            $registros = $repository->load($criteria);

            $this->datagrid->clear();

            if ($registros)
            {
                foreach ($registros as $item)
                {
                    $this->datagrid->addItem($item);
                }
            }

            TTransaction::close();
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Método padrão que chama onReload()
     */
    public function show()
    {
        $this->onReload();
        parent::show();
    }
}
