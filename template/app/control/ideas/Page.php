<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;

class Page extends TPage
{
    private $form;
    private $datagrid;

    public function __construct()
    {
        parent::__construct();


        $this->form = new BootstrapFormBuilder('form_page');
        $this->form->setFormTitle('Cadastro de usuários');

        $id = new TEntry('id');
        $id->setEditable(false);

        $name  = new TEntry('name');
        $email = new TEntry('email');

        $this->form->addFields([new TLabel('ID')],    [$id]);
        $this->form->addFields([new TLabel('Nome')],  [$name]);
        $this->form->addFields([new TLabel('Email')], [$email]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:check green');

        $this->datagrid = new TDataGrid;

        $this->datagrid->addColumn(new TDataGridColumn('id',    'ID',    'left'));
        $this->datagrid->addColumn(new TDataGridColumn('nome',  'Nome',  'left'));
        $this->datagrid->addColumn(new TDataGridColumn('email', 'Email', 'left'));

        $this->datagrid->createModel();

        $panel = new TPanelGroup('Lista de usuários');
        $panel->add($this->datagrid);

        parent::add($this->form);
        parent::add($panel);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('Usuarios');

            $usuario = new Usuario;
            $usuario->nome       = $param['name'];
            $usuario->email      = $param['email'];
            $usuario->criado_em  = date('Ymd H:i');
            $usuario->store();

            TTransaction::close();

            $this->onReload();
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }
    }
    public function onReload($param = null)
    {
        try {
            TTransaction::open('Usuarios');

            $repository = new TRepository('Usuario');
            $usuarios   = $repository->load();

            $this->datagrid->clear();

            if ($usuarios) {
                foreach ($usuarios as $usuario) {
                    $this->datagrid->addItem($usuario);
                }
            }

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            throw $e;
        }
    }
}
