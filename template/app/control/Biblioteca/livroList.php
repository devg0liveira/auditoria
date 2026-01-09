<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Wrapper\BootstrapDatagridWrapper;

class livroList extends TPage
{
    protected $datagrid;
    public function __construct()
    {
        parent::__construct();

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';

        $column_id = new TDataGridColumn('id', 'ID', 'center', '10%');
        $column_titulo = new TDataGridColumn('titulo', 'Título', 'left', '30%');
        $column_autor = new TDataGridColumn('autor', 'Autor', 'left', '30%');
        $column_isbn = new TDataGridColumn('isbn', 'ISBN', 'left', '20%');

        $this->datagrid->addColumn($column_id);
        $this->datagrid->addColumn($column_titulo);
        $this->datagrid->addColumn($column_autor);
        $this->datagrid->addColumn($column_isbn);

        $action_edit = new TDataGridAction(['livroForm', 'onEdit'], ['id' => '{id}']);
        $this->datagrid->addAction($action_edit, 'Editar', 'fa:edit blue');
        $action_delete = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);
        $this->datagrid->addAction($action_delete, 'Excluir', 'fa:trash red');
        

        $this->datagrid->createModel();

        parent::add($this->datagrid);
    }

    public function onReload($param)
    {
        try {
            TTransaction::open('biblioteca');

            $repository = new TRepository('Livro');
            $criteria = new TCriteria;

            $livros = $repository->load($criteria);

            $this->datagrid->clear();

            if ($livros) {
                foreach ($livros as $livro) {
                    $this->datagrid->addItem($livro);
                }
            }

            TTransaction::close();
        } catch (Exception $e) {
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

            TTransaction::open('biblioteca');

            $livro = Livro::find($param['id']);
            if ($livro) {
                $livro->delete();
            }

            TTransaction::close();

            new TMessage('info', 'Livro excluído com sucesso');
            $this->onReload($param);
        }
        catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        $this->onReload($param = null);
        parent::show();
    }
}
