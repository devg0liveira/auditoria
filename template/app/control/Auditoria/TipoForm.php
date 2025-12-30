<?php
use Adianti\Control\TPage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Control\TAction;
use Adianti\Widget\Dialog\TQuestion;

class TipoForm extends TPage
{
    private $form;
    private $datagrid;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_tipo');
        $this->form->setFormTitle('Cadastro de Tipo de Auditoria');

        $tipo = new TEntry('ZCK_TIPO');
        $tipo->setEditable(false);

        $desc = new TEntry('ZCK_DESCRI');
        $desc->setSize('100%');

        $this->form->addFields([new TLabel('Código:')], [$tipo]);
        $this->form->addFields([new TLabel('Inspeção <span style="color:red">*</span>:')], [$desc]);

        $btn = new TButton('save');
        $btn->setAction(new TAction([$this, 'onSave']), 'Salvar');
        $btn->setImage('fa:save green');

        $this->form->addFields([], [$btn]);

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->setHeight(320);

        $col_tipo = new TDataGridColumn('ZCK_TIPO', 'Código', 'center', '15%');
        $col_desc = new TDataGridColumn('ZCK_DESCRI', 'Inspeção', 'left', '50%');
        $col_data = new TDataGridColumn('ZCK_DATA', 'Data', 'center', '15%');
        $col_data->setTransformer(function($value) {
            if ($value && strlen($value) === 8 && is_numeric($value)) {
                return substr($value, 6, 2) . '/' . substr($value, 4, 2) . '/' . substr($value, 0, 4);
            }
            return $value;
        });
        $col_status = new TDataGridColumn('D_E_L_E_T_', 'Status', 'center', '10%');
        $col_status->setTransformer(function($value) {
            return $value == '*' ? 'Inativo' : 'Ativo';
        });

        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_desc);
        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_status);

        $action_edit = new TDataGridAction([$this, 'onEdit'], ['ZCK_TIPO' => '{ZCK_TIPO}']);
        $action_edit->setLabel('Editar');
        $action_edit->setImage('fa:edit blue');
        $this->datagrid->addAction($action_edit);

        $action_inactivate = new TDataGridAction([$this, 'onInactivate'], ['ZCK_TIPO' => '{ZCK_TIPO}']);
        $action_inactivate->setLabel('Inativar');
        $action_inactivate->setImage('fa:ban orange');
        $action_inactivate->setDisplayCondition([$this, 'isActive']);
        $this->datagrid->addAction($action_inactivate);

        $action_activate = new TDataGridAction([$this, 'onActivate'], ['ZCK_TIPO' => '{ZCK_TIPO}']);
        $action_activate->setLabel('Ativar');
        $action_activate->setImage('fa:check-circle green');
        $action_activate->setDisplayCondition([$this, 'isInactive']);
        $this->datagrid->addAction($action_activate);

        $this->datagrid->createModel();

        
        $panel = new TPanelGroup('Inspeções Cadastradas');
        $panel->add($this->datagrid);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($panel);

        parent::add($container);

        $this->onReload();
    }

    public function onReload()
    {
        try {
            TTransaction::open('auditoria');
            $tipos = ZCK010::orderBy('ZCK_TIPO')
                          ->load();
            $this->datagrid->clear();
            if ($tipos) {
                foreach ($tipos as $tipo) {
                    $this->datagrid->addItem($tipo);
                }
            }
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
            $tipo = ZCK010::where('ZCK_TIPO', '=', $param['ZCK_TIPO'])
                         ->where('D_E_L_E_T_', '<>', '*')
                         ->first();
            if ($tipo) {
                $data = new stdClass;
                $data->ZCK_TIPO = $tipo->ZCK_TIPO;
                $data->ZCK_DESCRI = $tipo->ZCK_DESCRI;
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

    public function onInactivate($param)
    {
        $action = new TAction([$this, 'confirmInactivate']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente inativar esta inspeção?', $action);
    }

    public function confirmInactivate($param)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total
                FROM ZCL010
                WHERE ZCL_TIPO = :tipo
                AND D_E_L_E_T_ <> '*'
            ");
            $stmt->execute([':tipo' => $param['ZCK_TIPO']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['total'] > 0) {
                throw new Exception('Não é possível inativar esta inspeção pois existem perguntas vinculadas a ela.');
            }
            $tipo = ZCK010::where('ZCK_TIPO', '=', $param['ZCK_TIPO'])
                         ->first();
            if ($tipo) {
                $tipo->D_E_L_E_T_ = '*';
                $tipo->store();
            }
            TTransaction::close();
            new TMessage('info', 'Inspeção inativada com sucesso!');
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
        new TQuestion('Deseja realmente ativar esta inspeção?', $action);
    }

    public function confirmActivate($param)
    {
        try {
            TTransaction::open('auditoria');
            $tipo = ZCK010::where('ZCK_TIPO', '=', $param['ZCK_TIPO'])
                         ->first();
            if ($tipo) {
                $tipo->D_E_L_E_T_ = ' ';
                $tipo->store();
            }
            TTransaction::close();
            new TMessage('info', 'Inspeção ativada com sucesso!');
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
            if (empty($param['ZCK_DESCRI'])) {
                throw new Exception('Inspeção é obrigatória.');
            }
            TTransaction::open('auditoria');
            if (!empty($param['ZCK_TIPO'])) {
                $obj = ZCK010::where('ZCK_TIPO', '=', $param['ZCK_TIPO'])
                            ->where('D_E_L_E_T_', '<>', '*')
                            ->first();
               
                if (!$obj) {
                    throw new Exception('Inspeção não encontrada.');
                }
               
                $obj->ZCK_DESCRI = $param['ZCK_DESCRI'];
            } else {
                
                $obj = new ZCK010;
                $conn = TTransaction::get();
                $result = $conn->query("
                    SELECT MAX(CAST(ZCK_TIPO AS INT)) AS max_tipo
                    FROM ZCK010
                    WHERE ISNUMERIC(ZCK_TIPO) = 1
                ");
                $row = $result->fetch(PDO::FETCH_ASSOC);
                $max = $row['max_tipo'] ?? 0;
                $obj->ZCK_TIPO = str_pad($max + 1, 3, '0', STR_PAD_LEFT);
                $obj->ZCK_DESCRI = $param['ZCK_DESCRI'];
                $obj->ZCK_DATA = date('Ymd');
                $obj->ZCK_HORA = date('Hi');
                $obj->ZCK_USUARIO = TSession::getValue('username');
                $obj->D_E_L_E_T_ = ' ';
            }
            $obj->store();
            TTransaction::close();
            new TMessage('info', 'Inspeção salva com sucesso!');
           
            $this->form->clear();
            $this->onReload();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function isActive($row)
    {
        return $row->D_E_L_E_T_ != '*';
    }

    public static function isInactive($row)
    {
        return $row->D_E_L_E_T_ == '*';
    }
}