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

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
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
use Adianti\Registry\TSession;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TFilter;
use Adianti\Widget\Form\TEntry;

class HistoricoList extends TPage
{
    protected $datagrid;

    /**
     * Construtor
     */
    public function __construct()
    {
        parent::__construct();

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid());

        // === COLUNAS ===
        $col_id = new TDataGridColumn('R_E_C_N_O_', 'ID', 'center', '8%');
        $col_doc = new TDataGridColumn('ZCM_DOC', 'Nº Auditoria', 'center', '12%');
        $col_filial = new TDataGridColumn('ZCM_FILIAL', 'Filial', 'center', '10%');
        $col_tipo = new TDataGridColumn('ZCK_DESCRI', 'Tipo', 'left', '25%');
        $col_data = new TDataGridColumn('ZCM_DATA', 'Data', 'center', '15%');
        $col_hora = new TDataGridColumn('ZCM_HORA', 'Hora', 'center', '10%');
        $col_user = new TDataGridColumn('ZCM_USUGIR', 'Usuário', 'left', '15%');
        $col_score = new TDataGridColumn('score_total', 'Score %', 'center', '10%');

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_doc);
        $this->datagrid->addColumn($col_filial);
        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_hora);
        $this->datagrid->addColumn($col_user);
        $this->datagrid->addColumn($col_score);

        // === AÇÕES ===
        $action_view = new TDataGridAction([__CLASS__, 'onViewStatic'], ['key' => '{R_E_C_N_O_}']);
        $action_view->setLabel('Ver');
        $action_view->setImage('fa:eye blue');
        $this->datagrid->addAction($action_view);

        $this->datagrid->createModel();

        // === PAINEL ===
        $panel = TPanelGroup::pack('Histórico de Auditorias', $this->datagrid);

        
        /*$input_search = new TEntry('input_search');
        $input_search->placeholder = 'Buscar';
        $input_search->setSize('100%');
        $this->datagrid->enableSearch(
            $input_search,
            'R_E_C_N_O_, ZCM_DOC, ZCM_FILIAL, ZCK_DESCRI, ZCM_DATA, ZCM_HORA, ZCM_USUGIR'
        );
        $panel->addHeaderWidget($input_search);
        */

        // Botão Nova auditoria
        $panel->addHeaderActionLink(
            'Nova auditoria',
            new TAction(['inicioAuditoriaModal', 'onOpenCurtain']),
            'fa:plus-circle green'
        );
        parent::add($panel);
    }

    /**
     * Carrega dados da lista
     */
    public function onReload()
    {
        try {
            TTransaction::open('auditoria');

            $repository = new TRepository('ZCM010');
            $criteria = new TCriteria();

            $criteria->add(new TFilter('D_E_L_E_T_', '<>', '*'));
            $criteria->setProperty('order', 'ZCM_DATA DESC, ZCM_HORA DESC');

            $auditorias = $repository->load($criteria);
            $this->datagrid->clear();

            if ($auditorias) {
                foreach ($auditorias as $auditoria) {
                    // Busca a descrição do tipo de auditoria
                    $tipo = ZCK010::where('ZCK_TIPO', '=', $auditoria->ZCM_TIPO)
                        ->where('D_E_L_E_T_', '<>', '*')
                        ->first();

                    $auditoria->ZCK_DESCRI = $tipo ? $tipo->ZCK_DESCRI : 'N/A';
                    $auditoria->ZCM_DATA = $this->formatarData($auditoria->ZCM_DATA);
                    $auditoria->score_total = number_format(
                        $this->calcularScoreTotal($auditoria->ZCM_FILIAL, $auditoria->ZCM_DOC),
                        1
                    ) . '%';

                    $this->datagrid->addItem($auditoria);
                }
            }

            TTransaction::close();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Calcula o score total de uma auditoria
     */
    private function calcularScoreTotal($filial, $doc)
    {
        try {
            TTransaction::open('auditoria');

            // Soma dos scores das respostas
            $respostas = ZCN010::where('ZCN_FILIAL', '=', $filial)
                ->where('ZCN_DOC', '=', $doc)
                ->where('D_E_L_E_T_', '<>', '*')
                ->load();

            $total_score = 0;
            if ($respostas) {
                foreach ($respostas as $r) {
                    if ($r->ZCN_NAOCO !== 'N') {
                        $total_score += (float) $r->ZCN_SCORE;
                    }
                }
            }

            // Soma dos pesos totais
            $total_peso = ZCL010::where('ZCL_FILIAL', '=', $filial)
                ->where('D_E_L_E_T_', '<>', '*')
                ->sumBy('ZCL_SCORE');

            TTransaction::close();

            return $total_peso > 0 ? ($total_score / $total_peso) * 100 : 0;

        } catch (Exception $e) {
            TTransaction::rollback();
            return 0;
        }
    }

    /**
     * Formata data de AAAAMMDD para DD/MM/AAAA
     */
    private function formatarData($data)
    {
        if (strlen($data) == 8) {
            return substr($data, 6, 2) . '/' . substr($data, 4, 2) . '/' . substr($data, 0, 4);
        }
        return $data;
    }

    /**
     * Visualiza uma auditoria em modal
     */
    public static function onViewStatic($param)
    {
        try {
            $key = $param['key'] ?? null;
            
            if (!$key) {
                throw new Exception('ID da auditoria não informado.');
            }

            TTransaction::open('auditoria');
            $auditoria = ZCM010::find($key);

            if (!$auditoria || $auditoria->D_E_L_E_T_ === '*') {
                TTransaction::close();
                throw new Exception('Auditoria não encontrada ou excluída.');
            }
            
            TTransaction::close();

            // Abre modal de visualização
            $page = TWindow::create('Visualizar Auditoria', 0.8, 0.8);
            $page->removePadding();

            TSession::setValue('auditoria_key', $key);
            TSession::setValue('view_mode', true);

            $embed = new CheckListForm();
            $embed->onEdit($param);

            $page->add($embed);
            $page->setIsWrapped(true);
            $page->show();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Exibe a página e carrega os dados
     */
    public function show()
    {
        $this->onReload();
        parent::show();
    }
}