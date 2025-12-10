<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

class expExcel extends TPage
{
    private $form;
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_exp_excel');
        $this->form->setFormTitle('Cadastro Simples');

        $name     = new TEntry('name');
        $telefone = new TEntry('telefone');
        $age      = new TEntry('age');
        $adresse  = new TEntry('adresse');

        $name->setSize('100%');
        $telefone->setSize('100%');
        $age->setSize('100%');
        $adresse->setSize('100%');

        $this->form->addFields([new TLabel('Nome:')], [$name]);
        $this->form->addFields([new TLabel('Telefone:')], [$telefone]);
        $this->form->addFields([new TLabel('Idade:')], [$age]);
        $this->form->addFields([new TLabel('Endereço:')], [$adresse]);

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';
        
        $btn_salvar = $this->form->addAction('Salvar', new TAction([$this, 'onReload']), 'fa:save green');

        $btn_exportar = $this->form->addAction('Exportar para Excel', new TAction(['ExpExcelExport', 'onExportExcel'], ['register_state' => 'false']), 'fa:file-excel-o green');
        


        $col_name     = new TDataGridColumn('name',     'Nome',     'left');
        $col_telefone = new TDataGridColumn('telefone', 'Telefone', 'left');
        $col_age      = new TDataGridColumn('age',      'Idade',    'center');
        $col_adresse  = new TDataGridColumn('adresse',  'Endereço', 'left');

        $col_name->setAction(new TAction([$this, 'onReload']), ['order' => 'name']);
        $col_telefone->setAction(new TAction([$this, 'onReload']), ['order' => 'telefone']);
        $col_age->setAction(new TAction([$this, 'onReload']), ['order' => 'age']);
        $col_adresse->setAction(new TAction([$this, 'onReload']), ['order' => 'adresse']);

        $this->datagrid->addColumn($col_name);
        $this->datagrid->addColumn($col_telefone);
        $this->datagrid->addColumn($col_age);
        $this->datagrid->addColumn($col_adresse);

        $this->datagrid->createModel();

        $this->form->addContent([$this->datagrid]);

        $this->onReload();

        parent::add($this->form);
    }

    public function onReload($param = null)
    {
        $data = $this->form->getData();

        $this->datagrid->clear();

        if (!empty($data->name) || !empty($data->telefone) || !empty($data->age) || !empty($data->adresse)) {
            $item = new stdClass;
            $item->name     = $data->name     ?: '(vazio)';
            $item->telefone = $data->telefone ?: '(vazio)';
            $item->age      = $data->age      ?: '(vazio)';
            $item->adresse  = $data->adresse  ?: '(vazio)';

            $this->datagrid->addItem($item);
        }

        $this->form->setData($data);
    }
}