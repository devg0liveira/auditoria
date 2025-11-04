<?php

/*
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

        $action_new = new TDataGridAction(['CheckListForm', 'onStart']); // ou onClear
        $action_new->setLabel('Novo');
        $action_new->setImage('fa:plus-circle green');

        // Adiciona as ações ao datagrid (sem duplicar!)
        $this->datagrid->addAction($action_new);
        $this->datagrid->addAction($action_edit);

        // Remova esta linha duplicada:
        // $this->datagrid->addAction($action_edit);

        // ✅ Adiciona a ação diretamente ao DataGrid (não existe addActionColumn)
        $this->datagrid->addAction($action_edit);

        // ✅ Cria o modelo da grid (estrutura visual)
        $this->datagrid->createModel();

        // ✅ Painel
        $panel = new TPanelGroup('Histórico de Avaliações');
        $panel->add($this->datagrid);


        parent::add($panel);
    }

    
     * Método chamado automaticamente ao abrir a página
     */
/*  public function onReload()
    {
        try {
            TTransaction::open('auditoria'); // nome do auditoria em databases.ini

            $repository = new TRepository('Historico');
            $criteria   = new TCriteria;
            $criteria->setProperty('order', 'id desc'); // ordenar por id desc

            $registros = $repository->load($criteria);

            $this->datagrid->clear();

            if ($registros) {
                foreach ($registros as $item) {
                    $this->datagrid->addItem($item);
                }
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Método padrão que chama onReload()
     
    public function show()
    {
        $this->onReload();
        parent::show();
    }
}
*/


// app/control/HistoricoList.php



use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Registry\TSession;
use Adianti\Database\TFilter;
use Adianti\Wrapper\BootstrapDatagridWrapper;

class HistoricoList extends TPage
{
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->disableDefaultClick();

        // === COLUNAS ===
        $col_tipo     = new TDataGridColumn('tipo_descricao', 'Tipo', 'left', '30%');
        $col_data     = new TDataGridColumn('data_hora', 'Data/Hora', 'center', '20%');
        $col_usuario  = new TDataGridColumn('ZCL_USUARIO', 'Usuário', 'left', '20%');
        $col_score    = new TDataGridColumn('score', 'Score %', 'center', '15%');
        $col_respostas = new TDataGridColumn('total_respostas', 'Itens', 'center', '10%');

        // Transformadores
        $col_data->setTransformer([$this, 'formatarDataHora']);
        $col_score->setTransformer(fn($v) => number_format($v, 1) . '%');

        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_usuario);
        $this->datagrid->addColumn($col_respostas);
        $this->datagrid->addColumn($col_score);

        // === AÇÃO VER ===
        $action_view = new TDataGridAction([$this, 'onView'], [
            'tipo' => '{ZCL_TIPO}',
            'data' => '{ZCL_DATA}',
            'hora' => '{ZCL_HORA}',
            'usuario' => '{ZCL_USUARIO}'
        ]);
        $action_view->setLabel('Ver');
        $action_view->setImage('fa:eye blue');
        $this->datagrid->addAction($action_view);

        $this->datagrid->createModel();

        // === PAINEL ===
        $panel = TPanelGroup::pack('Histórico de Auditorias Finalizadas', $this->datagrid);
        $panel->addHeaderActionLink(
            'Nova Auditoria',
            new \Adianti\Control\TAction(['inicioAuditoriaModal', 'onOpenCurtain']),
            'fa:plus-circle green'
        );

        parent::add($panel);
    }

    /**
     * Carrega auditorias agrupadas por tipo + data/hora
     */
    public function onReload($param = null)
    {
        try {
            TTransaction::open('auditoria');

            // Consulta agrupada por tipo + data + hora + usuário
            $conn = TTransaction::get();
            $sql = "
                SELECT 
                    ZCL_TIPO,
                    ZCL_DATA,
                    ZCL_HORA,
                    ZCL_USUARIO,
                    COUNT(*) AS total_respostas,
                    SUM(CASE WHEN ZCL_RESPOSTA = 'C' THEN 1 ELSE 0 END) AS conformes
                FROM ZCL010
                WHERE D_E_L_E_T_ <> '*'
                GROUP BY ZCL_TIPO, ZCL_DATA, ZCL_HORA, ZCL_USUARIO
                ORDER BY ZCL_DATA DESC, ZCL_HORA DESC
            ";

            $result = $conn->query($sql);
            $this->datagrid->clear();

            foreach ($result as $row) {
                $tipo = $row['ZCL_TIPO'];
                $total = $row['total_respostas'];
                $conformes = $row['conformes'];

                // Busca nome do tipo
                $tipoObj = ZCK010::where('ZC_TIPO', '=', $tipo)
                                 ->where('D_E_L_E_T_', '<>', '*')
                                 ->first();
                $row['tipo_descricao'] = $tipoObj ? trim($tipoObj->ZC_DESCRI) : 'N/D';
                $row['data_hora'] = $row['ZCL_DATA'] . $row['ZCL_HORA'];
                $row['score'] = $total > 0 ? ($conformes / $total) * 100 : 0;

                $this->datagrid->addItem((object)$row);
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar histórico: ' . $e->getMessage());
            if (TTransaction::get()) TTransaction::rollback();
        }
    }

    /**
     * Formata data + hora
     */
    public function formatarDataHora($value)
    {
        if (strlen($value) == 14) {
            $data = substr($value, 0, 8);
            $hora = substr($value, 8, 6);
            return $this->formatarData($data) . ' ' . $this->formatarHora($hora);
        }
        return $value;
    }

    public function formatarData($data)
    {
        return strlen($data) == 8 ? 
            substr($data, 6, 2) . '/' . substr($data, 4, 2) . '/' . substr($data, 0, 4) : 
            $data;
    }

    public function formatarHora($hora)
    {
        return strlen($hora) == 6 ? 
            substr($hora, 0, 2) . ':' . substr($hora, 2, 2) . ':' . substr($hora, 4, 2) : 
            $hora;
    }

    /**
     * Visualiza auditoria
     */
    public function onView($param)
    {
        try {
            $tipo = $param['tipo'] ?? null;
            $data = $param['data'] ?? null;
            $hora = $param['hora'] ?? null;
            $usuario = $param['usuario'] ?? null;

            if (!$tipo || !$data || !$hora) {
                throw new Exception('Parâmetros inválidos.');
            }

            TSession::setValue('view_auditoria', [
                'tipo' => $tipo,
                'data' => $data,
                'hora' => $hora,
                'usuario' => $usuario
            ]);
            TSession::setValue('view_mode', true);

            $win = TWindow::create('Visualizar Auditoria', 0.9, 0.9);
            $win->removePadding();

            $form = new checkListForm();
            $form->onStart(['tipo' => $tipo]); // só precisa do tipo

            $win->add($form);
            $win->setIsWrapped(true);
            $win->show();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        $this->onReload();
        parent::show();
    }
}