<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Registry\TSession;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

class Cadastro extends TPage
{
    private $form;
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_user');
        $this->form->setFormTitle('Novo Usuário');

        $email = new TEntry('email');
        $name  = new TEntry('name');

        $this->form->addFields([new TLabel('Email')], [$email]);
        $this->form->addFields([new TLabel('Name')], [$name]);

        $email->addValidation('Email', new TRequiredValidator);
        $name->addValidation('Name', new TRequiredValidator);

        $this->form->addAction('Save', new TAction([$this, 'onSave']), 'fa:save green');

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';

        $col_email = new TDataGridColumn('email', 'Email', 'left');
        $col_name  = new TDataGridColumn('name', 'Name', 'left');

        $this->datagrid->addColumn($col_email);
        $this->datagrid->addColumn($col_name);

        $col_email->setAction(new TAction([$this, 'onReload']), ['order' => 'email']);
        $col_name->setAction(new TAction([$this, 'onReload']), ['order' => 'name']);

        $this->datagrid->createModel();

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));

        $formPanel = TPanelGroup::pack('Cadastro', $this->form);
        $vbox->add($formPanel);

        $panel = TPanelGroup::pack('', $this->datagrid);
        $vbox->add($panel);

        parent::add($vbox);

        $this->onReload();
    }

    public function onSave($param)
    {
        try {
            $this->form->validate();

            $data = $this->form->getData();

            $list = TSession::getValue('cadastro_list') ?? [];

            $list[] = [
                'email' => $data->email,
                'name'  => $data->name
            ];

            TSession::setValue('cadastro_list', $list);

            $this->form->setData(new stdClass);

            $this->onReload();

            new TMessage('info', 'Usuário cadastrado!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function onReload($param = null)
    {
        $this->datagrid->clear();

        $list = TSession::getValue('cadastro_list') ?? [];

        if (!empty($param['order'])) {
            usort($list, function ($a, $b) use ($param) {
                return strcmp($a[$param['order']], $b[$param['order']]);
            });
        }

        foreach ($list as $item) {
            $this->datagrid->addItem((object) $item);
        }
    }
}

