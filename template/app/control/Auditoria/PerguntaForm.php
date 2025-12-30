<?php

use Adianti\Control\TPage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Control\TAction;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Database\TCriteria;

class PerguntaForm extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_pergunta');
        $this->form->setFormTitle('Adicionar Pergunta');

        $etapa = new TEntry('ZCJ_ETAPA');
        $etapa->setEditable(false);

        $tipo = new TDBCombo('ZCL_TIPO', 'auditoria', 'ZCK010', 'ZCK_TIPO', 'ZCK_DESCRI', 'ZCK_TIPO');
        $tipo->setSize('100%');
        $tipo->setDefaultOption('Selecione o tipo de insperção...');
        $tipo->addValidation('Tipo', new TRequiredValidator);

        $desc = new TEntry('ZCJ_DESCRI');
        $desc->setSize('100%');
        $desc->addValidation('Pergunta', new TRequiredValidator);

        $score = new TCombo('ZCL_SCORE');
        $score->addItems(['1' => '1', '2' => '2']);
        $score->setDefaultOption('Selecione o score');
        $score->addValidation('Score', new TRequiredValidator);

        $this->form->addFields([new TLabel('Etapa:')], [$etapa]);
        $this->form->addFields([new TLabel('Tipo de Insperção')], [$tipo]);
        $this->form->addFields([new TLabel('Pergunta')], [$desc]);
        $this->form->addFields([new TLabel('Score')], [$score]);

        $btn = new TButton('save');
        $btn->setAction(new TAction([$this, 'onSave']), 'Salvar');
        $btn->setImage('fa:save green');
        $this->form->addFields([], [$btn]);

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->setHeight(400);

        $col_etapa = new TDataGridColumn('ZCJ_ETAPA', 'Etapa', 'center', '10%');
        $col_pergunta = new TDataGridColumn('ZCJ_DESCRI', 'Pergunta', 'left', '35%');
        $col_tipo = new TDataGridColumn('tipo_descricao', 'Tipo Insperção', 'left', '20%');
        $col_score = new TDataGridColumn('ZCL_SCORE', 'Score', 'center', '10%');
        $col_status = new TDataGridColumn('status_label', 'Status', 'center', '10%');
        $col_data = new TDataGridColumn('ZCJ_DATA', 'Data', 'center', '15%');

        $col_status->setTransformer(function($value, $object) {
            $deleted = trim($object->D_E_L_E_T_ ?? ' ');
            if ($deleted === '*') {
                return '<span class="label label-danger">Inativa</span>';
            }
            return '<span class="label label-success">Ativa</span>';
        });

        $col_data->setTransformer(function($value) {
            if ($value && strlen($value) === 8 && is_numeric($value)) {
                return substr($value, 6, 2) . '/' . substr($value, 4, 2) . '/' . substr($value, 0, 4);
            }
            return $value;
        });

        $this->datagrid->addColumn($col_etapa);
        $this->datagrid->addColumn($col_pergunta);
        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_score);
        $this->datagrid->addColumn($col_status);
        $this->datagrid->addColumn($col_data);

        $action_edit = new TDataGridAction([$this, 'onEdit'], ['ZCJ_ETAPA' => '{ZCJ_ETAPA}']);
        $action_edit->setLabel('Editar');
        $action_edit->setImage('fa:edit blue');
        $this->datagrid->addAction($action_edit);

        $action_inactivate = new TDataGridAction([$this, 'onInactivate'], ['ZCJ_ETAPA' => '{ZCJ_ETAPA}']);
        $action_inactivate->setLabel('Inativar');
        $action_inactivate->setImage('fa:ban orange');
        $action_inactivate->setDisplayCondition([$this, 'isActive']);
        $this->datagrid->addAction($action_inactivate);

        $action_activate = new TDataGridAction([$this, 'onActivate'], ['ZCJ_ETAPA' => '{ZCJ_ETAPA}']);
        $action_activate->setLabel('Ativar');
        $action_activate->setImage('fa:check-circle green');
        $action_activate->setDisplayCondition([$this, 'isInactive']);
        $this->datagrid->addAction($action_activate);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setLimit(20);

        $panel = new TPanelGroup('Perguntas Cadastradas');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($panel);

        parent::add($container);

        $this->onReload();
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $limit = 20;
            $offset = isset($param['offset']) ? (int)$param['offset'] : 0;

            $sql = "
                SELECT 
                    cj.ZCJ_ETAPA,
                    cj.ZCJ_DESCRI,
                    cj.ZCJ_DATA,
                    cj.D_E_L_E_T_,
                    cl.ZCL_SCORE,
                    ck.ZCK_DESCRI as tipo_descricao
                FROM ZCJ010 cj
                LEFT JOIN ZCL010 cl 
                    ON cl.ZCL_ETAPA = cj.ZCJ_ETAPA
                LEFT JOIN ZCK010 ck 
                    ON ck.ZCK_TIPO = cl.ZCL_TIPO 
                    AND ck.D_E_L_E_T_ <> '*'
                ORDER BY 
                    CASE WHEN cj.D_E_L_E_T_ = '*' THEN 1 ELSE 0 END,
                    cj.ZCJ_ETAPA
                OFFSET {$offset} ROWS
                FETCH NEXT {$limit} ROWS ONLY
            ";

            $stmt = $conn->query($sql);
            $perguntas = $stmt->fetchAll(PDO::FETCH_OBJ);

            $sql_count = "SELECT COUNT(*) as total FROM ZCJ010";
            $stmt_count = $conn->query($sql_count);
            $row_count = $stmt_count->fetch(PDO::FETCH_ASSOC);
            $count = $row_count['total'] ?? 0;

            $this->datagrid->clear();

            if ($perguntas) {
                foreach ($perguntas as $pergunta) {
                    $this->datagrid->addItem($pergunta);
                }
            }

            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public function onEdit($param)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $sql = "
                SELECT 
                    cj.ZCJ_ETAPA,
                    cj.ZCJ_DESCRI,
                    cl.ZCL_TIPO,
                    cl.ZCL_SCORE
                FROM ZCJ010 cj
                LEFT JOIN ZCL010 cl 
                    ON cl.ZCL_ETAPA = cj.ZCJ_ETAPA
                WHERE cj.ZCJ_ETAPA = :etapa
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':etapa' => $param['ZCJ_ETAPA']]);
            $pergunta = $stmt->fetch(PDO::FETCH_OBJ);

            if ($pergunta) {
                $data = new stdClass;
                $data->ZCJ_ETAPA = $pergunta->ZCJ_ETAPA;
                $data->ZCJ_DESCRI = $pergunta->ZCJ_DESCRI;
                $data->ZCL_TIPO = $pergunta->ZCL_TIPO;
                $data->ZCL_SCORE = $pergunta->ZCL_SCORE;
                $this->form->setData($data);
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public function isActive($object)
    {
        return trim($object->D_E_L_E_T_ ?? ' ') !== '*';
    }

    public function isInactive($object)
    {
        return trim($object->D_E_L_E_T_ ?? ' ') === '*';
    }

    public function onInactivate($param)
    {
        $action = new TAction([$this, 'confirmInactivate']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente inativar esta pergunta? Ela não aparecerá mais nas auditorias futuras.', $action);
    }

    public function confirmInactivate($param)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $stmt = $conn->prepare("
                UPDATE ZCJ010 
                SET D_E_L_E_T_ = '*'
                WHERE ZCJ_ETAPA = :etapa
            ");
            $stmt->execute([':etapa' => $param['ZCJ_ETAPA']]);

            $stmt2 = $conn->prepare("
                UPDATE ZCL010 
                SET D_E_L_E_T_ = '*'
                WHERE ZCL_ETAPA = :etapa
            ");
            $stmt2->execute([':etapa' => $param['ZCJ_ETAPA']]);

            TTransaction::close();

            new TMessage('info', 'Pergunta inativada com sucesso!');
            $this->onReload();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public function onActivate($param)
    {
        $action = new TAction([$this, 'confirmActivate']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente ativar esta pergunta? Ela voltará a aparecer nas auditorias.', $action);
    }

    public function confirmActivate($param)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $stmt = $conn->prepare("
                UPDATE ZCJ010 
                SET D_E_L_E_T_ = ' '
                WHERE ZCJ_ETAPA = :etapa
            ");
            $stmt->execute([':etapa' => $param['ZCJ_ETAPA']]);

            $stmt2 = $conn->prepare("
                UPDATE ZCL010 
                SET D_E_L_E_T_ = ' '
                WHERE ZCL_ETAPA = :etapa
            ");
            $stmt2->execute([':etapa' => $param['ZCJ_ETAPA']]);

            TTransaction::close();

            new TMessage('info', 'Pergunta ativada com sucesso!');
            $this->onReload();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public function onSave($param)
    {
        try {
            if (empty($param['ZCJ_DESCRI'])) {
                throw new Exception('O campo Pergunta é obrigatório.');
            }
            if (empty($param['ZCL_TIPO'])) {
                throw new Exception('Selecione um tipo de insperção.');
            }
            if (empty($param['ZCL_SCORE']) || !in_array($param['ZCL_SCORE'], ['1', '2'])) {
                throw new Exception('Selecione um score válido (1 ou 2).');
            }

            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            if (!empty($param['ZCJ_ETAPA'])) {
                $obj = ZCJ010::where('ZCJ_ETAPA', '=', $param['ZCJ_ETAPA'])
                            ->where('D_E_L_E_T_', '<>', '*')
                            ->first();
                
                if (!$obj) {
                    throw new Exception('Pergunta não encontrada.');
                }
                
                $obj->ZCJ_DESCRI = $param['ZCJ_DESCRI'];
                $obj->store();

                $zcl = ZCL010::where('ZCL_ETAPA', '=', $param['ZCJ_ETAPA'])
                            ->where('D_E_L_E_T_', '<>', '*')
                            ->first();
                
                if ($zcl) {
                    $zcl->ZCL_TIPO = $param['ZCL_TIPO'];
                    $zcl->ZCL_SCORE = $param['ZCL_SCORE'];
                    $zcl->store();
                }

                $mensagem = 'Pergunta atualizada com sucesso!';
            } else {
                $stmt = $conn->query("
                    SELECT MAX(CAST(ZCJ_ETAPA AS INT)) AS max_etapa
                    FROM ZCJ010
                    WHERE ISNUMERIC(ZCJ_ETAPA) = 1
                      AND D_E_L_E_T_ <> '*'
                ");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $max = $row['max_etapa'] ?? 0;
                $etapa_gerada = str_pad($max + 1, 5, '0', STR_PAD_LEFT);

                $obj = new ZCJ010;
                $obj->ZCJ_ETAPA = $etapa_gerada;
                $obj->ZCJ_DESCRI = $param['ZCJ_DESCRI'];
                $obj->ZCJ_FILIAL = '01'; 
                $obj->ZCJ_DATA = date('Ymd');
                $obj->ZCJ_HORA = date('Hi');
                $obj->ZCJ_USUARIO = TSession::getValue('username');
                $obj->D_E_L_E_T_ = ' '; 
                $obj->R_E_C_D_E_L_ = 0;
                $obj->store();

                $zcl = new ZCL010;
                $zcl->ZCL_ETAPA = $etapa_gerada;
                $zcl->ZCL_TIPO = $param['ZCL_TIPO'];
                $zcl->ZCL_SCORE = $param['ZCL_SCORE'];
                $zcl->ZCL_FILIAL = '01'; 
                
                $stmt_seq = $conn->query("
                    SELECT MAX(CAST(ZCL_SEQ AS INT)) AS max_seq
                    FROM ZCL010
                    WHERE ZCL_TIPO = '{$param['ZCL_TIPO']}'
                      AND ISNUMERIC(ZCL_SEQ) = 1
                      AND D_E_L_E_T_ <> '*'
                ");
                $row_seq = $stmt_seq->fetch(PDO::FETCH_ASSOC);
                $max_seq = $row_seq['max_seq'] ?? 0;
                $zcl->ZCL_SEQ = str_pad($max_seq + 1, 3, '0', STR_PAD_LEFT);
                
                $zcl->ZCL_DATA = date('Ymd');
                $zcl->ZCL_HORA = date('Hi');
                $zcl->ZCL_USUGIR = TSession::getValue('username');
                $zcl->D_E_L_E_T_ = ' ';
                $zcl->store();

                $mensagem = "Pergunta cadastrada com sucesso!<br>Etapa: {$etapa_gerada}<br>Tipo: {$param['ZCL_TIPO']}<br>Score: {$param['ZCL_SCORE']}";
            }

            TTransaction::close();

            new TMessage('info', $mensagem);
            
            $this->form->clear();
            $this->onReload();
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }
    }
}