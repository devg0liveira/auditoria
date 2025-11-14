<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Wrapper\BootstrapDatagridWrapper;

class HistoricoList extends TPage
{
    private $datagrid;
    private $pageNavigation;


    public function __construct()
    {
        parent::__construct();

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->disableDefaultClick();
        $this->datagrid->style = 'width: 100%';


        $col_doc      = new TDataGridColumn('zcm_doc', 'Documento', 'center', '10%');
        $col_filial   = new TDataGridColumn('zcm_filial', 'Filial', 'left', '12%');
        $col_tipo     = new TDataGridColumn('zcm_tipo', 'Tipo', 'left', '18%');
        $col_data     = new TDataGridColumn('zcm_data', 'Data', 'center', '10%');
        $col_hora     = new TDataGridColumn('zcm_hora', 'Hora', 'center', '8%');
        $col_usuario  = new TDataGridColumn('zcm_usuario', 'Usuário', 'left', '12%');
        $col_score    = new TDataGridColumn('score', 'Score', 'center', '10%');
        $col_obs      = new TDataGridColumn('zcm_obs', 'Observações', 'left', '20%');

        $col_data->setTransformer([$this, 'formatarData']);
        $col_hora->setTransformer([$this, 'formatarHora']);

        $this->datagrid->addColumn($col_doc);
        $this->datagrid->addColumn($col_filial);
        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_hora);
        $this->datagrid->addColumn($col_usuario);
        $this->datagrid->addColumn($col_score);
        $this->datagrid->addColumn($col_obs);

        $action_view = new TDataGridAction([$this, 'onView'], ['zcm_doc' => '{zcm_doc}']);
        $action_view->setLabel('Ver');
        $action_view->setImage('fa:eye blue');
        $this->datagrid->addAction($action_view);

        $action_iniciativa = new TDataGridAction(['IniciativaForm', 'onLoad'], ['doc' => '{zcm_doc}']);
        $action_iniciativa->setLabel('Iniciativa');
        $action_iniciativa->setImage('fa:lightbulb yellow');
        $this->datagrid->addAction($action_iniciativa);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        $panel = new TPanelGroup('Histórico de Auditorias Finalizadas');
        $panel->add($this->datagrid)->style = 'overflow-x:auto';
        $panel->addFooter($this->pageNavigation);

        $panel->addHeaderActionLink(
            'Nova Auditoria',
            new TAction(['inicioAuditoriaModal', 'onLoad']),
            'fa:plus-circle green'
        );

        parent::add($panel);
    }

   public function onReload($param = null)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $limit = 20;                                          
        $page  = max(1, (int)($param['page'] ?? 1));
        $offset = ($page - 1) * $limit;

            $sql = "SELECT ZCM_DOC, ZCM_FILIAL, ZCM_TIPO, ZCM_DATA, ZCM_HORA, ZCM_USUGIR, ZCM_OBS
                    FROM ZCM010 
                    WHERE D_E_L_E_T_ <> '*' 
                    ORDER BY ZCM_DATA DESC, ZCM_HORA DESC";

            $result = $conn->query($sql);
            $this->datagrid->clear();

            $score_sql = "SELECT COALESCE(SUM(cl.ZCL_SCORE), 0)
                          FROM ZCN010 cn
                          INNER JOIN ZCL010 cl ON cl.ZCL_ETAPA = cn.ZCN_ETAPA AND cl.ZCL_TIPO = :tipo
                          WHERE cn.ZCN_DOC = :doc 
                            AND cn.ZCN_NAOCO = 'N' 
                            AND cn.D_E_L_E_T_ <> '*'";

            $stmt_score = $conn->prepare($score_sql);

            foreach ($result as $row) {
                $doc  = trim($row['ZCM_DOC']);
                $tipo = trim($row['ZCM_TIPO']);

                $stmt_score->execute([':doc' => $doc, ':tipo' => $tipo]);
                $score = (float) $stmt_score->fetchColumn();

                $item = new stdClass;
                $item->zcm_doc     = $doc;
                $item->zcm_filial  = trim($row['ZCM_FILIAL']);
                $item->zcm_tipo    = $tipo;
                $item->zcm_data    = $row['ZCM_DATA'];
                $item->zcm_hora    = $row['ZCM_HORA'];
                $item->zcm_usuario = trim($row['ZCM_USUGIR']);
                $item->score       = $score;
                $item->zcm_obs     = trim($row['ZCM_OBS'] ?? '');

                $this->datagrid->addItem($item);
            }

            $this->pageNavigation->setPage($param['page'] ?? 1);

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar dados: ' . $e->getMessage());
            TTransaction::rollbackAll();
        }
    }

    public function formatarData($value)
    {
        if ($value && strlen($value) === 8 && is_numeric($value)) {
            return substr($value, 6, 2) . '/' . substr($value, 4, 2) . '/' . substr($value, 0, 4);
        }
        return $value;
    }

   
    public function formatarHora($value)
    {
        if ($value && strlen($value) >= 4 && is_numeric($value)) {
            return substr($value, 0, 2) . ':' . substr($value, 2, 2);
        }
        return $value;
    }

    public function onView($param)
    {
        $doc = $param['zcm_doc'] ?? null;
        if ($doc) {
            AdiantiCoreApplication::loadPage('AuditoriaView', 'onReload', ['key' => $doc]);
        }
    }


    public function show()
    {
        $this->onReload();
        parent::show();
    }
}