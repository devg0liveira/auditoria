<?php

use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Control\TAction;
use Adianti\Widget\Dialog\TQuestion;

class ProdutoList extends TPage
{
    private $datagrid;
    private $loaded;

    public function __construct()
    {
        parent::__construct();

        $this->datagrid = new TDataGrid;

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '10%'));
        $this->datagrid->addColumn(new TDataGridColumn('nome', 'Nome', 'left', '30%'));
        $this->datagrid->addColumn(new TDataGridColumn('preco', 'Preço', 'right', '20%'));
        $this->datagrid->addColumn(new TDataGridColumn('estoque', 'Estoque', 'right', '20%'));
        $this->datagrid->addColumn(new TDataGridColumn('categoria', 'Categoria', 'left', '20%'));
        $this->datagrid->addColumn(new TDataGridColumn('ativo', 'Ativo', 'center', '10%'));

        $action_edit = new TDataGridAction(['ProdutoForm', 'onEdit']);
        $action_edit->setLabel('Editar');
        $action_edit->setImage('fa:edit blue');
        $action_edit->setField('id');

        $action_del = new TDataGridAction([$this, 'onDelete']);
        $action_del->setLabel('Excluir');
        $action_del->setImage('fa:trash red');
        $action_del->setField('id');

        $this->datagrid->addAction($action_edit);
        $this->datagrid->addAction($action_del);

        $this->datagrid->createModel();

        $panel = new TPanelGroup('Lista de Produtos');
        $panel->addHeaderActionLink(
            'Novo Produto',
            new TAction(['ProdutoForm', 'onEdit']),
            'fa:plus green'
        );
        $panel->add($this->datagrid);

        parent::add($panel);
    }

    public function onReload()
    {
        try {
            TTransaction::open('Loja');

            $repository = new TRepository('Produto');
            $criteria   = new TCriteria;
            $produtos   = $repository->load($criteria);

            $this->datagrid->clear();

            if ($produtos) {
                foreach ($produtos as $produto) {
                    $this->datagrid->addItem($produto);
                }
            }

            TTransaction::close();
            $this->loaded = true;
        }
        catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onDelete($param)
    {
        $action = new TAction([$this, 'Delete']);
        $action->setParameters($param);

        new TQuestion('Deseja realmente excluir este produto?', $action);
    }

    public function Delete($param)
    {
        try {
            if (empty($param['id'])) {
                throw new Exception('ID não informado');
            }

            TTransaction::open('Loja');

            $produto = Produto::find($param['id']);
            if ($produto) {
                $produto->delete();
            }

            TTransaction::close();

            new TMessage('info', 'Produto excluído com sucesso');
            $this->onReload();
        }
        catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}
