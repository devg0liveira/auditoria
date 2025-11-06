<?php

use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
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
        $col_doc      = new TDataGridColumn('zcm_doc', 'Documento', 'center', '8%');
        $col_filial   = new TDataGridColumn('zcm_filial', 'Filial', 'left', '15%');
        $col_tipo     = new TDataGridColumn('zcm_tipo', 'Tipo', 'left', '20%');
        $col_datahora = new TDataGridColumn('zcm_datahora', 'Data/Hora', 'center', '15%');
        $col_usuario  = new TDataGridColumn('zcm_usuario', 'Usuário', 'left', '15%');
        $col_score    = new TDataGridColumn('score', 'Score %', 'center', '10%');
        $col_obs      = new TDataGridColumn('zcm_obs', 'Observações', 'left', '30%');

        // Formatadores
        $col_datahora->setTransformer([$this, 'formatarDataHora']);
        $col_score->setTransformer(fn($v) => number_format($v, 1) . '%');

        // Adiciona as colunas
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
        
        // 🔹 CORREÇÃO: Mudando de modal para página comum
        $panel->addHeaderActionLink(
            'Nova Auditoria',
            new \Adianti\Control\TAction(['inicioAuditoriaModal', 'onLoad']),
            'fa:plus-circle green'
        );

        parent::add($panel);
    }

    /**
     * Carrega dados consolidados de ZCL010 e converte para formato ZCM010
     */
    public function onReload($param = null)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            // Agrupa auditorias finalizadas
            $sql = "
           SELECT 
           ZCM_FILIAL,
        ZCM_TIPO,
        ZCM_DATA,
        ZCM_HORA,
        ZCM_USUGIR,
        COUNT(*) AS total_perguntas,
        SUM(CASE WHEN ZCM_OBS IS NULL OR ZCM_OBS = '' THEN 1 ELSE 0 END) AS conformes,
        STRING_AGG(
            CASE 
                WHEN ZCM_OBS IS NOT NULL AND ZCM_OBS <> ''
                THEN ZCM_OBS
                ELSE NULL 
            END, 
            '; '
            )AS obs_nao_conformes
              FROM ZCM010
              WHERE D_E_L_E_T_ <> '*'
               GROUP BY ZCM_FILIAL, ZCM_TIPO, ZCM_DATA, ZCM_HORA, ZCM_USUGIR
             ORDER BY ZCM_DATA DESC, ZCM_HORA DESC
";

            $result = $conn->query($sql);
            $this->datagrid->clear();
            $contador = 1;

            foreach ($result as $row) {
                $tipo_completo = trim($row['ZCL_TIPO']);
                $tipo     = substr($tipo_completo, 0, 3); // Extrai os 3 primeiros caracteres
                $data     = $row['ZCL_DATA'];
                $hora     = $row['ZCL_HORA'];
                $usuario  = trim($row['ZCL_USUARIO']);
                $total    = $row['total_perguntas'];
                $conformes = $row['conformes'];
                $score    = $total > 0 ? ($conformes / $total) * 100 : 0;
                $observacoes = $row['obs_nao_conformes'] ?? '';

                $zcm_doc = str_pad($contador, 6, '0', STR_PAD_LEFT);

                $item = (object)[
                    'zcm_doc'      => $zcm_doc,
                    'zcm_filial'   => 'N/A', // Não temos mais filial no ZCL010
                    'zcm_tipo'     => $this->obterDescricaoTipo($tipo),
                    'zcm_datahora' => $data . $hora,
                    'zcm_usuario'  => $usuario,
                    'score'        => $score,
                    'zcm_obs'      => $observacoes,
                    'tipo_cod'     => $tipo,
                    'data'         => $data,
                    'hora'         => $hora
                ];

                $this->datagrid->addItem($item);
                $contador++;
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar histórico: ' . $e->getMessage());
            if (TTransaction::get()) TTransaction::rollback();
        }
    }

    private function obterDescricaoTipo($tipo)
    {
        try {
            TTransaction::open('auditoria');
            $obj = ZCK010::where('ZCK_TIPO', '=', $tipo)
                ->where('D_E_L_E_T_', '<>', '*')
                ->first();
            TTransaction::close();
            return $obj ? trim($obj->ZCK_DESCRI) : $tipo;
        } catch (Exception $e) {
            return $tipo;
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

            // Aqui você pode abrir a visualização da auditoria
            new TMessage('info', "Abrir auditoria do documento {$doc}");
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






