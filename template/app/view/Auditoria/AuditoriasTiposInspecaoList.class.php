<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Util\AdiantiFuncoesSistema;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TDropDown;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * @version    3.0
 * @create     03/04/2024 - 15:15
 * @update     
 * @author     Josivaldo Santiago Barbosa
 * @copyright  Copyright (c) 2024 JSB Informática (http://www.jsbinformatica.com.br)
 */
class AuditoriasTiposInspecaoList extends TPage
{
    protected $form;     
    protected $datagrid; 
    protected $pageNavigation;
    protected $formgrid;
    protected $deleteButton;
    
    use Adianti\base\AdiantiStandardListTrait;
    
    /**
     * Page constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->setDatabase('protheus');
        $this->setActiveRecord('Zck010');
        $this->setDefaultOrder('ZCK_TIPO', 'asc');
        $this->setLimit(10);
        
        $criteria = new TCriteria();
        $criteria->add(new TFilter('D_E_L_E_T_','<>', '*'));   

        $programs = (array) TSession::getValue('programs'); // programs with permission
        
        $this->setCriteria($criteria); 
        
        $this->addFilterField('ZCK_TIPO'   , 'like', 'ZCK_TIPO'    ); 
        $this->addFilterField('ZCK_DESCRI' , 'like', 'ZCK_DESCRI'  ); 
        
        // creates the form
        $this->form = new BootstrapFormBuilder('form_search_Zck010');
        $this->form->setFormTitle('Tipos de Inspeção de Auditoria');

        // create the form fields
        $ZCK_TIPO = new TEntry('ZCK_TIPO');
        $ZCK_TIPO->setMask('###');
        $ZCK_TIPO->setMaxLength(3);

        $ZCK_DESCRI = new TEntry('ZCK_DESCRI');
        $ZCK_DESCRI->setMaxLength(70);

        $R_E_C_N_O_ = new TEntry('R_E_C_N_O_');

        // add the fields
        $this->form->addFields( [ new TLabel('Tipo')     ], [ $ZCK_TIPO   ], [ new TLabel('Descrição') ], [ $ZCK_DESCRI ] );

        // set sizes
        $ZCK_TIPO->setSize('100%');
        $ZCK_DESCRI->setSize('100%');
        
        // keep the form filled during navigation with session data
        $this->form->setData( TSession::getValue(__CLASS__.'_filter_data') );
        
        $this->form->addExpandButton('Filtro','fa:filter');
        
        // add the search form actions
        $btn = $this->form->addAction(_t('Find'), new TAction([$this, 'onSearch']), 'fa:search');
        $btn->class = 'btn btn-sm btn-primary';
        
        // creates a Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';
        //$this->datagrid->disableDefaultClick();

        // creates the datagrid columns
        $column_ZCK_TIPO    = new TDataGridColumn('ZCK_TIPO'   , 'Tipo'     , 'left'  );
        $column_ZCK_DESCRI  = new TDataGridColumn('ZCK_DESCRI' , 'Descrição', 'left'  );
        $column_ZCK_USUGIR  = new TDataGridColumn('ZCK_USUGIR' , 'Resp.'    , 'left'  );
        $column_R_E_C_N_O_  = new TDataGridColumn('R_E_C_N_O_' , 'ID'       , 'right' );
        
        $column_R_E_C_N_O_->setTransformer(function($value, $object, $row) {
        
            $select_row = TSession::getValue(__CLASS__.'_select_row');
            
            if($select_row == $value)
            {
                $row->style = "background: #abdef9";
                
                $button = $row->find('i', ['class'=>'far fa-square fa-fw black'])[0];
                
                if($button)
                {
                    $button->class = 'far fa-check-square fa-fw black';
                }
            }
            
            return $value;
        });

        // add the columns to the DataGrid
        $this->datagrid->addColumn($column_ZCK_TIPO   );
        $this->datagrid->addColumn($column_ZCK_DESCRI );
        $this->datagrid->addColumn($column_ZCK_USUGIR );
        $this->datagrid->addColumn($column_R_E_C_N_O_ );
        
        $actionSelect = new TDataGridAction([$this, 'onSelect'], ['register_state' => 'false','R_E_C_N_O_' => '{R_E_C_N_O_}', 'offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')]);
        $this->datagrid->addAction($actionSelect, '', 'far:square fa-fw black');        

        $action1 = new TDataGridAction([$this, 'confirmEdit'      ], ['register_state' => 'false','R_E_C_N_O_'=>'{R_E_C_N_O_}','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')]);
        
        $this->datagrid->addAction($action1, _t('Edit') ,   'far:edit blue');
        
        // create the datagrid model
        $this->datagrid->createModel();
        
        // creates the page navigation
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload'],['register_state' => 'false']));
        
        $panel = new TPanelGroup('', 'white');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);
        
        // header actions
        $panel->addHeaderActionLink(_t('New'), new TAction(['AuditoriasTiposInspecaoForm', 'onEdit'],['register_state' => 'false','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')] ), 'fa:plus green');

        // vertical box container
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        $container->add($panel);
        
        parent::add($container);
    }
    
    /**
     * Save the object reference in session
     */
    public function onSelect($param)
    {
        TSession::setValue(__CLASS__.'_select_row', $param['R_E_C_N_O_']); 
        $this->onReload( func_get_arg(0) );
    }     
    
    public function confirmEdit($param)
    {
        $this->onSelect($param);
        AdiantiCoreApplication::loadPage('AuditoriasTiposInspecaoForm', 'onEdit', $param );
    }

}
