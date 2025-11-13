<?php

use Adianti\Control\TPage;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TForm;
use Adianti\Database\TTransaction;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

class AuditoriaView extends TPage
{
    private $form;
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_auditoria_view');
        $this->form->setFormTitle('Detalhes da Auditoria');

        $doc      = new TEntry('zcm_doc');
        $filial   = new TEntry('zcm_filial');
        $tipo     = new TEntry('zcm_tipo');
        $datahora = new TEntry('zcm_datahora');
        $usuario  = new TEntry('zcm_usuario');
        $score    = new TEntry('score');
        $obs      = new TEntry('zcm_obs');

        $doc->setEditable(false);
        $filial->setEditable(false);
        $tipo->setEditable(false);
        $datahora->setEditable(false);
        $usuario->setEditable(false);
        $score->setEditable(false);
        $obs->setEditable(false);

        $this->form->addFields([new TLabel('Documento')], [$doc]);
        $this->form->addFields([new TLabel('Filial')], [$filial]);
        $this->form->addFields([new TLabel('Tipo')], [$tipo]);
        $this->form->addFields([new TLabel('Data/Hora')], [$datahora]);
        $this->form->addFields([new TLabel('Usuário')], [$usuario]);
        $this->form->addFields([new TLabel('Score')], [$score]);
        $this->form->addFields([new TLabel('Observações Gerais')], [$obs]);


        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->disableDefaultClick();

        $col_etapa    = new TDataGridColumn('zcn_etapa', 'Etapa', 'left');
        $col_pergunta = new TDataGridColumn('zcj_descri', 'Pergunta', 'left');
        $col_resposta = new TDataGridColumn('zcn_naoco', 'Resposta', 'center');
        $col_score    = new TDataGridColumn('zcl_score', 'Score', 'center');
        $col_obs      = new TDataGridColumn('zcn_obs', 'Observações', 'left');

        $this->datagrid->addColumn($col_etapa);
        $this->datagrid->addColumn($col_pergunta);
        $this->datagrid->addColumn($col_resposta);
        $this->datagrid->addColumn($col_score);
        $this->datagrid->addColumn($col_obs);

        $this->datagrid->createModel();

        $panel = new TPanelGroup('Checklist da Auditoria');
        $panel->add($this->datagrid);

        parent::add($this->form);
        parent::add($panel);
    }

    public function onReload($param = null)
    {
        try {
            $doc = $param['key'] ?? null;
            if (!$doc) {
                throw new Exception('Documento não informado.');
            }

            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $sql_cab = "
                SELECT 
                    ZCM_DOC, ZCM_FILIAL, ZCM_TIPO, ZCM_DATA, ZCM_HORA, ZCM_USUGIR, ZCM_OBS
                FROM ZCM010
                WHERE ZCM_DOC = :doc AND D_E_L_E_T_ <> '*'
            ";
            $stmt_cab = $conn->prepare($sql_cab);
            $stmt_cab->execute([':doc' => $doc]);
            $row_cab = $stmt_cab->fetch();

            if (!$row_cab) {
                throw new Exception('Auditoria não encontrada.');
            }

            $datahora = $this->formatarData($row_cab['ZCM_DATA']) . ' ' . $this->formatarHora($row_cab['ZCM_HORA']);

            $this->form->setData((object)[
                'zcm_doc'      => trim($row_cab['ZCM_DOC']),
                'zcm_filial'   => trim($row_cab['ZCM_FILIAL']),
                'zcm_tipo'     => trim($row_cab['ZCM_TIPO']),
                'zcm_datahora' => $datahora,
                'zcm_usuario'  => trim($row_cab['ZCM_USUGIR']),
                'zcm_obs'      => trim($row_cab['ZCM_OBS'] ?? ''),
                'score'        => $this->calcularScore($conn, $doc, trim($row_cab['ZCM_TIPO']))
            ]);

            $this->datagrid->clear();
            $sql_check = "
                SELECT 
                    cn.ZCN_DOC, cl.ZCL_ETAPA, cj.ZCJ_DESCRI, cn.ZCN_NAOCO, cl.ZCL_SCORE, cn.ZCN_OBS
                FROM ZCN010 cn
                INNER JOIN ZCL010 cl ON cl.ZCL_ETAPA = cn.ZCN_ETAPA AND cl.ZCL_TIPO = :tipo AND cl.D_E_L_E_T_ <> '*'
                INNER JOIN ZCJ010 cj ON cj.ZCJ_ETAPA = cn.ZCN_ETAPA AND cj.D_E_L_E_T_ <> '*'
                WHERE cn.ZCN_DOC = :doc AND cn.D_E_L_E_T_ <> '*'
                ORDER BY cn.ZCN_ETAPA
            ";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->execute([':doc' => $doc, ':tipo' => trim($row_cab['ZCM_TIPO'])]);
            $results = $stmt_check->fetchAll();

            foreach ($results as $row) {
                $item = (object)[
                    'zcn_doc'    => trim($row['ZCN_DOC']),
                    'zcn_etapa'  => trim($row['ZCL_ETAPA'] ?? ''),
                    'zcj_descri' => trim($row['ZCJ_DESCRI'] ?? ''),
                    'zcn_naoco'  => trim($row['ZCN_NAOCO'] ?? ''),
                    'zcl_score'  => (float)$row['ZCL_SCORE'],
                    'zcn_obs'    => trim($row['ZCN_OBS'] ?? '')
                ];
                $this->datagrid->addItem($item);
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar auditoria: ' . $e->getMessage());
            if (TTransaction::get()) TTransaction::rollback();
        }
    }

    private function calcularScore($conn, $doc, $tipo)
    {
        $sql_score = "
            SELECT COALESCE(SUM(cl.ZCL_SCORE), 0) as total_score
            FROM ZCN010 cn
            INNER JOIN ZCL010 cl ON cl.ZCL_ETAPA = cn.ZCN_ETAPA AND cl.ZCL_TIPO = :tipo AND cl.D_E_L_E_T_ <> '*'
            WHERE cn.ZCN_DOC = :doc AND (cn.ZCN_NAOCO = ' ' OR cn.ZCN_NAOCO IS NULL) AND cn.D_E_L_E_T_ <> '*'
        ";
        $stmt = $conn->prepare($sql_score);
        $stmt->execute([':doc' => $doc, ':tipo' => $tipo]);
        $score_row = $stmt->fetch();
        return (float)($score_row['total_score'] ?? 0);
    }

    private function formatarData($data)
    {
        return strlen($data) == 8 ? substr($data, 6, 2) . '/' . substr($data, 4, 2) . '/' . substr($data, 0, 4) : $data;
    }

    private function formatarHora($hora)
    {
        return strlen($hora) == 6 ? substr($hora, 0, 2) . ':' . substr($hora, 2, 2) . ':' . substr($hora, 4, 2) : $hora;
    }

}