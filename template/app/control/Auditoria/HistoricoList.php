<?php

use Adianti\Base\AdiantiStandardListTrait;
use Adianti\Base\TStandardList;
use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TCriteria;
use Adianti\Database\TRepository;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Wrapper\BootstrapDatagridWrapper;

class HistoricoList extends TStandardList
{
    protected $datagrid;
    protected $pageNavigation;

    use AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('auditoria');
        $this->setActiveRecord('ZCM010');
        $this->setDefaultOrder('zcm__data', 'desc');
        $this->setLimit(10);

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->disableDefaultClick();
        $this->datagrid->style = 'width: 100%';

        $col_doc      = new TDataGridColumn('ZCM_DOC', 'Documento', 'center', '10%');
        $col_filial   = new TDataGridColumn('ZCM_FILIAL', 'Filial', 'left', '12%');
        $col_tipo     = new TDataGridColumn('ZCM_TIPO', 'Tipo', 'left', '18%');
        $col_data     = new TDataGridColumn('ZCM_DATA', 'Data', 'center', '10%');
        $col_hora     = new TDataGridColumn('ZCM_HORA', 'Hora', 'center', '8%');
        $col_usuario  = new TDataGridColumn('ZCM_USUGIR', 'Usuário', 'left', '12%');
        $col_score    = new TDataGridColumn('ZCM_SCORE', 'Score', 'center', '10%');
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

        $action_iniciativa = new TDataGridAction(['IniciativaForm', 'onEdit'], ['doc' => '{ZCM_DOC}']);
        $action_iniciativa->setLabel('Iniciativa');
        $action_iniciativa->setImage('fa:lightbulb yellow');
        $action_iniciativa->setDisplayCondition([$this, 'deveExibirIniciativa']);
        $this->datagrid->addAction($action_iniciativa);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup('Histórico de Auditorias Finalizadas');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $panel->addHeaderActionLink(
            'Nova Auditoria',
            new TAction(['inicioAuditoriaModal', 'onLoad']),
            'fa:plus-circle green'
        );

        parent::add($panel);
    }

    private function planoEstaConcluido($documento)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();
            
            $sql = "SELECT COUNT(*) as total 
                    FROM ZCN010 
                    WHERE ZCN_DOC = :doc 
                    AND ZCN_STATUS <> 'C' 
                    AND D_E_L_E_T_ <> '*'";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([':doc' => $documento]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            TTransaction::close();
            
            return ($result['total'] == 0);
        } catch (Exception $e) {
            TTransaction::rollback();
            return false;
        }
    }

    public function deveExibirIniciativa($object)
    {
        return !$this->planoEstaConcluido($object->ZCM_DOC);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);

            $repository = new TRepository($this->activeRecord);
            $limit      = $this->limit;

            $criteria = $this->criteria ?? new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            $objects = $repository->load($criteria, FALSE);

            $this->datagrid->clear();

            if ($objects) {
                foreach ($objects as $object) {
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