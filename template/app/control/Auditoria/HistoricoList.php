<?php

use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TScript;
use Adianti\Wrapper\BootstrapDatagridWrapper;

class HistoricoList extends TPage
{
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->disableDefaultClick();

        $col_doc      = new TDataGridColumn('zcm_doc', 'Documento', 'center', '10%');
        $col_filial   = new TDataGridColumn('zcm_filial', 'Filial', 'left', '12%');
        $col_tipo     = new TDataGridColumn('zcm_tipo', 'Tipo', 'left', '18%');
        $col_datahora = new TDataGridColumn('zcm_datahora', 'Data/Hora', 'center', '15%');
        $col_usuario  = new TDataGridColumn('zcm_usuario', 'Usuário', 'left', '15%');
        $col_score    = new TDataGridColumn('score', 'Score', 'center', '10%');
        $col_obs      = new TDataGridColumn('zcm_obs', 'Observações', 'left', '20%');

        $col_datahora->setTransformer([$this, 'formatarDataHora']);
        $col_score->setTransformer(fn($v) => number_format($v, 0));

        $this->datagrid->addColumn($col_doc);
        $this->datagrid->addColumn($col_filial);
        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_datahora);
        $this->datagrid->addColumn($col_usuario);
        $this->datagrid->addColumn($col_score);
        $this->datagrid->addColumn($col_obs);

        // BOTÃO VER
        $action_view = new TDataGridAction([$this, 'onView'], ['zcm_doc' => '{zcm_doc}']);
        $action_view->setLabel('Ver');
        $action_view->setImage('fa:eye blue');
        $this->datagrid->addAction($action_view);

        // BOTÃO INICIATIVA 
        $action_iniciativa = new TDataGridAction(['IniciativaForm', 'onLoad'], ['doc' => '{zcm_doc}']);
        $action_iniciativa->setLabel('Iniciativa');
        $action_iniciativa->setImage('fa:lightbulb yellow');
        $this->datagrid->addAction($action_iniciativa);

        $this->datagrid->createModel();

        $panel = TPanelGroup::pack('Histórico de Auditorias Finalizadas', $this->datagrid);
        $panel->addHeaderActionLink('Nova Auditoria', new \Adianti\Control\TAction(['inicioAuditoriaModal', 'onLoad']), 'fa:plus-circle green');

        parent::add($panel);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $sql = "SELECT ZCM_DOC, ZCM_FILIAL, ZCM_TIPO, ZCM_DATA, ZCM_HORA, ZCM_USUGIR, ZCM_OBS
                    FROM ZCM010 WHERE D_E_L_E_T_ <> '*' 
                    ORDER BY ZCM_DATA DESC, ZCM_HORA DESC";

            $result = $conn->query($sql);
            $this->datagrid->clear();

            foreach ($result as $row) {
                $doc = trim($row['ZCM_DOC']);
                $tipo = trim($row['ZCM_TIPO']);

                $score_sql = "SELECT COALESCE(SUM(cl.ZCL_SCORE), 0)
                              FROM ZCN010 cn
                              INNER JOIN ZCL010 cl ON cl.ZCL_ETAPA = cn.ZCN_ETAPA AND cl.ZCL_TIPO = :tipo
                              WHERE cn.ZCN_DOC = :doc AND cn.ZCN_NAOCO = 'N' AND cn.D_E_L_E_T_ <> '*'";

                $stmt = $conn->prepare($score_sql);
                $stmt->execute([':doc' => $doc, ':tipo' => $tipo]);
                $score = $stmt->fetchColumn();

                $item = (object)[
                    'zcm_doc'      => $doc,
                    'zcm_filial'   => trim($row['ZCM_FILIAL']),
                    'zcm_tipo'     => $tipo,
                    'zcm_datahora' => $row['ZCM_DATA'] . $row['ZCM_HORA'],
                    'zcm_usuario'  => trim($row['ZCM_USUGIR']),
                    'score'        => (float)$score,
                    'zcm_obs'      => trim($row['ZCM_OBS'] ?? '')
                ];
                $this->datagrid->addItem($item);
            }
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar: ' . $e->getMessage());
            TTransaction::rollbackAll();
        }
    }

    private function formatDate($date)
    {
        return ($date && strlen($date) == 8) ? 
            substr($date, 6, 2) . '/' . substr($date, 4, 2) . '/' . substr($date, 0, 4) : '';
    }

    public function formatarDataHora($value)
    {
        if (strlen($value) >= 14) {
            $data = substr($value, 0, 8);
            $hora = substr($value, 8, 6);
            return $this->formatDate($data) . ' ' . 
                   substr($hora, 0, 2) . ':' . substr($hora, 2, 2) . ':' . substr($hora, 4, 2);
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