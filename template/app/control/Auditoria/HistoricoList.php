<?php

use Adianti\Control\TPage;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
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

        // === COLUNAS DO DATAGRID ===
        $col_doc      = new TDataGridColumn('zcm_doc', 'Documento', 'center', '10%');
        $col_filial   = new TDataGridColumn('zcm_filial', 'Filial', 'left', '12%');
        $col_tipo     = new TDataGridColumn('zcm_tipo', 'Tipo', 'left', '18%');
        $col_datahora = new TDataGridColumn('zcm_datahora', 'Data/Hora', 'center', '15%');
        $col_usuario  = new TDataGridColumn('zcm_usuario', 'Usuário', 'left', '15%');
        $col_score    = new TDataGridColumn('score', 'Score', 'center', '10%'); // Sem %
        $col_obs      = new TDataGridColumn('zcm_obs', 'Observações', 'left', '20%');

        // Formatadores
        $col_datahora->setTransformer([$this, 'formatarDataHora']);
        $col_score->setTransformer(fn($v) => number_format($v, 0)); // Pontos inteiros

        $this->datagrid->addColumn($col_doc);
        $this->datagrid->addColumn($col_filial);
        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_datahora);
        $this->datagrid->addColumn($col_usuario);
        $this->datagrid->addColumn($col_score);
        $this->datagrid->addColumn($col_obs);

        // === AÇÃO VER ===
        $action_view = new TDataGridAction([$this, 'onView'], ['zcm_doc' => '{zcm_doc}']);
        $action_view->setLabel('Ver');
        $action_view->setImage('fa:eye blue');
        $this->datagrid->addAction($action_view);

        $this->datagrid->createModel();

        // === PAINEL ===
        $panel = TPanelGroup::pack('Histórico de Auditorias Finalizadas', $this->datagrid);
        $panel->addHeaderActionLink(
            'Nova Auditoria',
            new \Adianti\Control\TAction(['inicioAuditoriaModal', 'onLoad']),
            'fa:plus-circle green'
        );

        parent::add($panel);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            // === BUSCA CABEÇALHOS EM ZCM010 ===
            $sql = "
                SELECT 
                    ZCM_DOC,
                    ZCM_FILIAL,
                    ZCM_TIPO,
                    ZCM_DATA,
                    ZCM_HORA,
                    ZCM_USUGIR,
                    ZCM_OBS
                FROM ZCM010
                WHERE D_E_L_E_T_ <> '*'
                ORDER BY ZCM_DATA DESC, ZCM_HORA DESC
            ";

            $result = $conn->query($sql);
            $this->datagrid->clear();

            foreach ($result as $row) {
                $doc      = trim($row['ZCM_DOC']);
                $filial   = trim($row['ZCM_FILIAL']);
                $tipo     = trim($row['ZCM_TIPO']);
                $data     = $row['ZCM_DATA'];
                $hora     = $row['ZCM_HORA'];
                $usuario  = trim($row['ZCM_USUGIR']);
                $obs      = trim($row['ZCM_OBS'] ?? '');

                // === CÁLCULO DO SCORE: SOMA DOS ZCL_SCORE DAS RESPOSTAS 'S' ===
                $sql_score = "
                    SELECT COALESCE(SUM(cl.ZCL_SCORE), 0) as total_score
                    FROM ZCN010 cn
                    INNER JOIN ZCL010 cl ON cl.ZCL_ETAPA = cn.ZCN_ETAPA 
                                         AND cl.ZCL_TIPO = :tipo
                                         AND cl.D_E_L_E_T_ <> '*'
                    WHERE cn.ZCN_DOC = :doc
                      AND cn.ZCN_NAOCO = 'N'  -- Apenas respostas 'S' (conformes)
                      AND cn.D_E_L_E_T_ <> '*'
                ";

                $stmt = $conn->prepare($sql_score);
                $stmt->execute([
                    ':doc'  => $doc,
                    ':tipo' => $tipo
                ]);
                $score_row = $stmt->fetch();
                $score = $score_row['total_score'] ?? 0;

                $item = (object)[
                    'zcm_doc'      => $doc,
                    'zcm_filial'   => $filial,
                    'zcm_tipo'     => $tipo,
                    'zcm_datahora' => $data . $hora,
                    'zcm_usuario'  => $usuario,
                    'score'        => (float)$score,
                    'zcm_obs'      => $obs
                ];

                $this->datagrid->addItem($item);
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar histórico: ' . $e->getMessage());
            if (TTransaction::get()) TTransaction::rollback();
        }
    }

    public function formatarDataHora($value)
    {
        if (strlen($value) >= 14) {
            $data = substr($value, 0, 8);
            $hora = substr($value, 8, 6);
            return $this->formatarData($data) . ' ' . $this->formatarHora($hora);
        }
        return $value;
    }

    private function formatarData($data)
    {
        return strlen($data) == 8 ? substr($data, 6, 2) . '/' . substr($data, 4, 2) . '/' . substr($data, 0, 4) : $data;
    }

    private function formatarHora($hora)
    {
        return strlen($hora) == 6 ? substr($hora, 0, 2) . ':' . substr($hora, 2, 2) . ':' . substr($hora, 4, 2) : $hora;
    }

    public function onView($param)
    {
        try {
            $doc = $param['zcm_doc'] ?? null;
            if (!$doc) {
                throw new Exception('Documento não informado.');
            }

            // Redireciona para tela de visualização detalhada
            TScript::create("
                __adianti_load_page('index.php?class=AuditoriaView&method=onReload&key={$doc}');
            ");
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