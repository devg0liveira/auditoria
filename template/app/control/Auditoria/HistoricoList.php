<?php

use Adianti\Base\AdiantiStandardListTrait;
use Adianti\Base\TStandardList;
use Adianti\Control\TAction;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Base\TScript;

class HistoricoList extends TStandardList
{
    protected $datagrid;
    protected $pageNavigation;
    protected $form;

    use AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('auditoria');
        $this->setActiveRecord('ZCM010');
        $this->setDefaultOrder('zcm__data', 'desc');
        $this->setLimit(10);

        $this->form = new BootstrapFormBuilder('form_filtro_historico');
        $this->form->setFormTitle('Filtros de Pesquisa');

        $data_de  = new TDate('data_de');
        $data_ate = new TDate('data_ate');
        $filial   = new TEntry('filial');
        $doc      = new TEntry('doc');

        $data_de->setMask('dd/mm/yyyy');
        $data_ate->setMask('dd/mm/yyyy');
        $data_de->setSize('100%');
        $data_ate->setSize('100%');
        $filial->setSize('100%');
        $doc->setSize('100%');

        $this->form->addFields([new TLabel('Data de')],  [$data_de]);
        $this->form->addFields([new TLabel('Data até')], [$data_ate]);
        $this->form->addFields([new TLabel('Filial')],   [$filial]);
        $this->form->addFields([new TLabel('Documento')], [$doc]);

        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addAction('Pesquisar', new TAction([$this, 'onSearch']), 'fa:search blue');
        

        $this->form->style = 'display:none';

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->disableDefaultClick();
        $this->datagrid->style = 'width: 100%';

        $col_doc      = new TDataGridColumn('ZCM_DOC', 'Documento', 'center', '10%');
        $col_filial   = new TDataGridColumn('ZCM_FILIAL', 'Filial', 'left', '12%');
        $col_tipo     = new TDataGridColumn('ZCM_TIPO', 'Tipo', 'left', '18%');
        $col_data     = new TDataGridColumn('ZCM_DATA', 'Data', 'center', '10%');
        $col_hora     = new TDataGridColumn('ZCM_HORA', 'Hora', 'center', '8%');
        $col_usuario  = new TDataGridColumn('ZCM_USUGIR', 'Usuário', 'left', '12%');
        $col_score    = new TDataGridColumn('score_calculado', 'Score', 'center', '10%');
        $col_obs      = new TDataGridColumn('ZCM_OBS', 'Observações', 'left', '20%');

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

        $action_view = new TDataGridAction([$this, 'onView'], ['zcm_doc' => '{ZCM_DOC}']);
        $action_view->setLabel('Ver');
        $action_view->setImage('fa:eye blue');
        $this->datagrid->addAction($action_view);
       
        $action_continuar = new TDataGridAction(['checkListForm', 'onContinuar'], ['doc' => '{ZCM_DOC}']);
        $action_continuar->setLabel('Continuar');
        $action_continuar->setImage('fa:play-circle green');
        $this->datagrid->addAction($action_continuar);

        $action_iniciativa = new TDataGridAction(['IniciativaForm', 'onEdit'], ['doc' => '{ZCM_DOC}']);
        $action_iniciativa->setLabel('Iniciativa');
        $action_iniciativa->setImage('fa:lightbulb yellow');
        $action_iniciativa->setDisplayCondition([$this, 'deveExibirIniciativa']);
        $this->datagrid->addAction($action_iniciativa);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup('Histórico de Auditorias Finalizadas');
        $panel->add($this->form);
        $panel->add($this->datagrid);
        $panel->getBody()->style = 'overflow-x: auto';
        $panel->addFooter($this->pageNavigation);

        $panel->addHeaderActionLink(
            'Filtros',
            new TAction([$this, 'onToggleFilters']),
            'fa:filter white'
        )->class = 'btn btn-primary btn-sm';

        $panel->addHeaderActionLink(
            'Nova Auditoria',
            new TAction(['inicioAuditoriaModal', 'onLoad']),
            'fa:plus-circle green'
        );

        parent::add($panel);
    }

    public function onToggleFilters()
    {
        $data = new stdClass;
        $data->data_de  = TSession::getValue('hist_data_de');
        $data->data_ate = TSession::getValue('hist_data_ate');
        $data->filial   = TSession::getValue('hist_filial');
        $data->doc      = TSession::getValue('hist_doc');

        $this->form->setData($data);

        TScript::create("
    var form = $('#form_filtro_historico');

    if (form.is(':visible')) {
        form.slideUp(300);
    } else {
        form.slideDown(300);
    }
");
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();

        TSession::setValue('hist_data_de', $data->data_de ?? null);
        TSession::setValue('hist_data_ate', $data->data_ate ?? null);
        TSession::setValue('hist_filial', $data->filial ?? null);
        TSession::setValue('hist_doc', $data->doc ?? null);

        $this->form->setData($data);

        TScript::create("
            $('#form_filtro_historico').slideUp(300);
        ");

        $this->onReload($param);
    }


    public function onClear($param = null)
    {
        $this->form->clear();

        TSession::setValue('hist_data_de', null);
        TSession::setValue('hist_data_ate', null);
        TSession::setValue('hist_filial', null);
        TSession::setValue('hist_doc', null);

        TScript::create("
            $('#form_filtro_historico').slideUp(300);
        ");

        $this->onReload($param);
    }


    private function calcularScore($documento, $tipo)
    {
        try {
            $conn = TTransaction::get();
            $sql = "
                SELECT SUM(cl.ZCL_SCORE) as total_perdido
                FROM ZCN010 cn
                INNER JOIN ZCL010 cl ON cl.ZCL_ETAPA = cn.ZCN_ETAPA 
                    AND cl.ZCL_TIPO = :tipo 
                    AND cl.D_E_L_E_T_ <> '*'
                WHERE cn.ZCN_DOC = :doc 
                    AND cn.ZCN_NAOCO IN ('NC', 'P', 'OP')
                    AND cn.D_E_L_E_T_ <> '*'
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':doc' => $documento, ':tipo' => $tipo]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_perdido = (int)($result['total_perdido'] ?? 0);
            return 120 - $total_perdido;
        } catch (Exception $e) {
            return null;
        }
    }

    private function planoEstaPendente($documento)
    {
        try {
            $conn = TTransaction::get();
            $sql = "SELECT COUNT(*) as total 
                    FROM ZCN010 
                    WHERE ZCN_DOC = :doc 
                    AND ZCN_NAOCO IN ('NC', 'P', 'OP')
                    AND (ZCN_STATUS IS NULL OR ZCN_STATUS <> 'C')
                    AND D_E_L_E_T_ <> '*'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':doc' => $documento]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['total'] > 0);
        } catch (Exception $e) {
            return false;
        }
    }

    public function deveExibirIniciativa($object)
    {
        return $this->planoEstaPendente($object->ZCM_DOC);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);
            $repository = new TRepository($this->activeRecord);
            $limit = $this->limit;
            $criteria = new TCriteria;

            if ($de = TSession::getValue('hist_data_de')) {
                $data_formatada = str_replace('/', '', implode('', array_reverse(explode('/', $de))));
                $criteria->add(new TFilter('ZCM_DATA', '>=', $data_formatada));
            }

            if ($ate = TSession::getValue('hist_data_ate')) {
                $data_formatada = str_replace('/', '', implode('', array_reverse(explode('/', $ate))));
                $criteria->add(new TFilter('ZCM_DATA', '<=', $data_formatada));
            }

            if ($filial = TSession::getValue('hist_filial')) {
                $criteria->add(new TFilter('ZCM_FILIAL', '=', $filial));
            }

            if ($doc = TSession::getValue('hist_doc')) {
                $criteria->add(new TFilter('ZCM_DOC', 'like', "%$doc%"));
            }

            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            $objects = $repository->load($criteria, false);
            $this->datagrid->clear();

            if ($objects) {
                foreach ($objects as $object) {
                    $object->score_calculado = $this->calcularScore(
                        trim($object->ZCM_DOC),
                        trim($object->ZCM_TIPO)
                    );
                    $this->datagrid->addItem($object);
                }
            }

            $criteria->resetProperties();
            $count = $repository->count($criteria);
            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setLimit($limit);
            $this->pageNavigation->setProperties($param);

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
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
}