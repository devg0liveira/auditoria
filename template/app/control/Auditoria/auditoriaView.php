<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Form\TLabel;
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
        $this->form->addHeaderAction('Voltar', new TAction(['HistoricoList', 'onReload']), 'fa:arrow-left');

        $doc = new TEntry('zcm_doc');
        $filial = new TEntry('zcm_filial');
        $tipo_desc = new TEntry('tipo_descricao');
        $data = new TEntry('zcm_data');
        $hora = new TEntry('zcm_hora');
        $usuario = new TEntry('zcm_usuario');
        //$score = new TEntry('score');
        $obs = new TText('zcm_obs');

        $doc->setEditable(false);
        $filial->setEditable(false);
        $tipo_desc->setEditable(false);
        $data->setEditable(false);
        $hora->setEditable(false);
        $usuario->setEditable(false);
        //$score->setEditable(false);
        $obs->setEditable(false);
        $obs->setSize('100%', 50);

        $this->form->addFields([new TLabel('Documento')], [$doc]);
        $this->form->addFields([new TLabel('Filial')], [$filial]);
        $this->form->addFields([new TLabel('Data')], [$data]);
        $this->form->addFields([new TLabel('Hora')], [$hora]);
        $this->form->addFields([new TLabel('Usuário')], [$usuario]);
        //$this->form->addFields([new TLabel('Score Final')], [$score]);
        $this->form->addFields([new TLabel('Observações Gerais')], [$obs]);

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->disableDefaultClick();
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->setHeight(320);

        $col_etapa = new TDataGridColumn('zcn_etapa', 'Etapa', 'left', '8%');
        $col_pergunta = new TDataGridColumn('zcj_descri', 'Pergunta', 'left', '28%');
        $col_resposta = new TDataGridColumn('zcn_naoco', 'Conformidade', 'center', '12%');
        //$col_score = new TDataGridColumn('zcl_score', 'Score', 'center', '12%');
        $col_obs = new TDataGridColumn('zcn_obs', 'Observações', 'left', '30%');
        $col_tipo_pergunta = new TDataGridColumn('zck_descri', 'Tipo', 'left', '10%');

        $col_resposta->setTransformer(function ($value) {
            $value = trim($value);
            return match ($value) {
                'NC' => 'Não Conforme',
                'C'  => 'Conforme',
                'NA' =>'Não Auditado',
                default=>'',
            };
        });

        $this->datagrid->addColumn($col_etapa);
        $this->datagrid->addColumn($col_pergunta);
        $this->datagrid->addColumn($col_tipo_pergunta);
        $this->datagrid->addColumn($col_resposta);
        //$this->datagrid->addColumn($col_score);
        $this->datagrid->addColumn($col_obs);

        $this->datagrid->createModel();

        $panel = new TPanelGroup('Checklist da Auditoria');
        $panel->add($this->datagrid);
        $panel->getBody()->style = 'overflow-x: auto';

        $container = new TVBox;
        $container->style = 'width: 100%; max-width: 1400px; margin: 0 auto;';
        $container->add($this->form);
        $container->add($panel);

        parent::add($container);
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
            SELECT ZCM_DOC, ZCM_FILIAL, ZCM_TIPO, ZCM_DATA, ZCM_HORA, ZCM_USUGIR, ZCM_OBS
            FROM ZCM010
            WHERE ZCM_DOC = :doc
            AND D_E_L_E_T_ <> '*'
        ";

        $stmt_cab = $conn->prepare($sql_cab);
        $stmt_cab->execute([':doc' => $doc]);
        $row_cab = $stmt_cab->fetch(PDO::FETCH_ASSOC);

        if (!$row_cab) {
            throw new Exception('Auditoria não encontrada.');
        }

        $data = $this->formatarData($row_cab['ZCM_DATA']);
        $hora = $this->formatarHora($row_cab['ZCM_HORA']);

        $form_data = new stdClass;
        $form_data->zcm_doc     = trim($row_cab['ZCM_DOC']);
        $form_data->zcm_filial  = trim($row_cab['ZCM_FILIAL']);
        $form_data->zcm_tipo    = trim($row_cab['ZCM_TIPO']);
        $form_data->zcm_data = $data;
        $form_data->zcm_hora = $hora;
        $form_data->zcm_usuario = trim($row_cab['ZCM_USUGIR']);
        $form_data->zcm_obs     = trim($row_cab['ZCM_OBS'] ?? '');

        $this->datagrid->clear();

        $sql_check = "
            SELECT
                cn.ZCN_ETAPA,
                cn.ZCN_NAOCO,
                cn.ZCN_OBS,
                ISNULL(cl.ZCL_SCORE, 0) AS ZCL_SCORE,
                ck.ZCK_TIPO,
                ck.ZCK_DESCRI,
                cj.ZCJ_DESCRI
            FROM ZCN010 cn

            LEFT JOIN ZCL010 cl 
                ON cl.ZCL_ETAPA = cn.ZCN_ETAPA
                AND cl.D_E_L_E_T_ <> '*'

            LEFT JOIN ZCJ010 cj 
                ON cj.ZCJ_ETAPA = cn.ZCN_ETAPA
                AND cj.D_E_L_E_T_ <> '*'

            LEFT JOIN ZCK010 ck 
                ON ck.ZCK_TIPO = cl.ZCL_TIPO
                AND ck.D_E_L_E_T_ <> '*'

            WHERE cn.ZCN_DOC = :doc
            AND cn.D_E_L_E_T_ <> '*'
            ORDER BY cn.ZCN_ETAPA
        ";

        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([':doc' => $doc]);
        $rows = $stmt_check->fetchAll(PDO::FETCH_ASSOC);

        $perda = 0;

        foreach ($rows as $row) {
            $item = (object)[
                'zcn_etapa'  => trim($row['ZCN_ETAPA'] ?? ''),
                'zcj_descri' => trim($row['ZCJ_DESCRI'] ?? ''),
                'zcn_naoco'  => trim($row['ZCN_NAOCO'] ?? ''),
                //'zcl_score'  => (int)$row['ZCL_SCORE'],
                'zcn_obs'    => trim($row['ZCN_OBS'] ?? ''),
                'zck_descri' => trim($row['ZCK_DESCRI'] ?? '')
            ];

            $this->datagrid->addItem($item);

            $naoco = trim($row['ZCN_NAOCO'] ?? '');
            if (in_array($naoco, ['NC'])) {
                $perda += (int)$row['ZCL_SCORE'];
            }
        }

        //$form_data->score = 120 - $perda;
        $this->form->setData($form_data);

        TTransaction::close();

    } catch (Exception $e) {
        new TMessage('error', 'Erro ao carregar auditoria: ' . $e->getMessage());
        if (TTransaction::get()) {
            TTransaction::rollback();
        }
    }
}




    private function formatarData($data)
    {
        if ($data && strlen($data) === 8 && is_numeric($data)) {
            return substr($data, 6, 2) . '/' . substr($data, 4, 2) . '/' . substr($data, 0, 4);
        }
        return $data;
    }

    private function formatarHora($value)
    {
        if ($value && strlen($value) >= 4 && is_numeric($value)) {
            return substr($value, 0, 2) . ':' . substr($value, 2, 2);
        }
        return $value;
    }
}
