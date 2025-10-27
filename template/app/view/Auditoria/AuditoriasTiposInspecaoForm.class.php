<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TSpinner;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * @version    3.0
 * @create     01/02/2024 - 14:28
 * @update     
 * @author     Josivaldo Santiago Barbosa
 * @copyright  Copyright (c) 2021 JSB Informática (http://www.jsbinformatica.com.br)
 */
class AuditoriasTiposInspecaoForm extends TPage
{
    
    /**
     * Form constructor
     * @param $param Request
     */
    public function __construct( $param )
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_Zck010');
        $this->form->setFormTitle('Tipo de Inspeção de Auditoria');
        $this->form->setClientValidation(true);

        // create the form fields
        $ZCK_TIPO  = new TEntry('ZCK_TIPO');
        $ZCK_TIPO->setEditable(false);

        $ZCK_DESCRI = new TEntry('ZCK_DESCRI');

        $ZCK_DESCRI->addValidation( 'Descrição', new TRequiredValidator ); 

        $R_E_C_N_O_ = new THidden('R_E_C_N_O_');

        $this->form->addFields( [ $R_E_C_N_O_ ] );
        $this->form->addFields( [ new TLabel('Código') ], [ $ZCK_TIPO ], [ new TLabel('Descrição' ) ], [ $ZCK_DESCRI] );

        $ZCK_TIPO->setSize('100%');
        $ZCK_DESCRI->setSize('100%');

        $ZCK_TIPO->setMaxLength(6);
        $ZCK_DESCRI->forceUpperCase();

        $this->etapas = new BootstrapFormBuilder('form_Tipo_Inspecao_Auditoria');
        $this->etapas->setFormTitle('<font color="red">Inclusão/Alteração de Etapa</font>');

        $etapa_detail_uniqid = new THidden('etapa_detail_uniqid');
        $etapa_detail_id     = new THidden('etapa_detail_id'    );

        $etapa_detail_codigo = new TDBCombo('etapa_detail_codigo','protheus','Zcj010','ZCJ_ETAPA','{ZCJ_ETAPA}-{ZCJ_DESCRI}');

        $etapa_detail_score = new TSpinner('etapa_detail_score');
        $etapa_detail_score->setRange(1, 4, 1);

        $this->etapas->addFields( [ $etapa_detail_uniqid ], [ $etapa_detail_id     ] );
        $this->etapas->addFields( [ new TLabel('Etapa')  ], [ $etapa_detail_codigo ], [ new TLabel('Score') ], [ $etapa_detail_score ] );

        $add_etapa = TButton::create('add_candidato', [$this, 'onItemAdd'], 'Salvar', 'fa:save green');
        $add_etapa->getAction()->setParameter('static','1');

        $clear_etapa = TButton::create('clear_candidato', [$this, 'onItemClear'], _t('Clear'), 'fa:eraser red');
        $clear_etapa->getAction()->setParameter('static','1');

        $this->etapas->addFields( [$add_etapa], [$clear_etapa] );                

        $this->etapas_list = new BootstrapDatagridWrapper(new TDataGrid());
        $this->etapas_list->setId('etapas_list');
        $this->etapas_list->generateHiddenFields();
        $this->etapas_list->style = 'width:100%';
        $this->etapas_list->disableDefaultClick();
        $this->etapas_list->setHeight(200);
        $this->etapas_list->makeScrollable();

        $col_uniq     = new TDataGridColumn( 'uniqid'    , 'Uniqid'     , 'left' , '0%'  );
        $col_id       = new TDataGridColumn( 'id'        , 'ID'         , 'left' , '0%'  );
        $col_data     = new TDataGridColumn( 'ZCL_DATA'  , 'Data'       , 'left' , '0%'  );
        $col_hora     = new TDataGridColumn( 'ZCL_HORA'  , 'Hora'       , 'left' , '0%'  );
        $col_etapa    = new TDataGridColumn( 'ZCL_ETAPA' , 'Etapa'      , 'left' , '20%' );
        $col_etapa_d  = new TDataGridColumn( 'ZCL_ETAPAD', 'Descrição'  , 'left' , '70%' );
        $col_score    = new TDataGridColumn( 'ZCL_SCORE' , 'Score'      , 'left' , '10%' );

        $col_uniq->setVisibility(false);
        $col_id->setVisibility(false);
        $col_data->setVisibility(false);
        $col_hora->setVisibility(false);

        $this->etapas_list->addColumn( $col_uniq    );
        $this->etapas_list->addColumn( $col_id      );
        $this->etapas_list->addColumn( $col_data    );
        $this->etapas_list->addColumn( $col_hora    );
        $this->etapas_list->addColumn( $col_etapa   );
        $this->etapas_list->addColumn( $col_etapa_d );
        $this->etapas_list->addColumn( $col_score   );

        // creates two datagrid actions
        $action1 = new TDataGridAction([$this, 'onEditItem'] );
        $action1->setFields( ['uniqid', '*'] );
        
        $action2 = new TDataGridAction([$this, 'onDelete']);
        $action2->setFields( ['uniqid', '*'] );
        
        // add the actions to the datagrid
        $this->etapas_list->addAction($action1, _t('Edit'), 'far:edit blue');
        $this->etapas_list->addAction($action2, _t('Delete'), 'far:trash-alt red');        

        $this->etapas_list->createModel();

        $this->form->addHeaderActionLink(_t('Back'), new TAction(['AuditoriasTiposInspecaoList', 'onReload'],['register_state' => 'false','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')] ), 'far:arrow-alt-circle-left blue');

        $this->form->addAction( _t('Save'), new TAction([$this, 'onSave'], ['static'=>'1','register_state' => 'false','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')]), 'fa:save green');
        $this->form->addAction( _t('Back'), new TAction(['AuditoriasTiposInspecaoList', 'onReload'], ['register_state' => 'false','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')]), 'far:arrow-alt-circle-left blue');

        $panel = new TPanelGroup('<font color="blue">Etapas Vinculadas</font>');
        $panel->add($this->etapas_list);
        $panel->getBody()->style = 'overflow-x:auto;min-height:220px';

        $this->form->addContent([$this->etapas     ]);
        $this->form->addContent([$panel            ]);      

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        
        parent::add($container);
        
    }

    /**
     * Load object to form data
     * @param $param Request
     */
    public function onEdit( $param )
    {
        try
        {
            TTransaction::open('protheus'); 

            if(isset($param['key']))
            {            
                $Zck010 = new Zck010($param['key']);
                $this->form->setData($Zck010);

                /*
                *  Recupera os registros de candidato
                */                    
                $itens = Zcl010::where('ZCL_TIPO', '=', $Zck010->ZCK_TIPO)->where('D_E_L_E_T_', '<>', '*')->orderby('ZCL_ETAPA')->load();
                
                foreach( $itens as $item => $value )
                {
                    $uniqid = uniqid();
                    $etapa = Zcj010::where('ZCJ_ETAPA','=',$value->ZCL_ETAPA)->where('D_E_L_E_T_', '<>', '*')->first();

					$grid_data = ['uniqid'       => $uniqid,
								  'id'           => $value->R_E_C_N_O_,
								  'ZCL_ETAPA'    => $value->ZCL_ETAPA,
                                  'ZCL_ETAPAD'   => $etapa->ZCJ_DESCRI,
                                  'ZCL_SCORE'    => $value->ZCL_SCORE,
								  'ZCL_DATA'     => $value->ZCL_DATA,
								  'ZCL_HORA'     => $value->ZCL_HORA
                                 ];

                    $row = $this->etapas_list->addItem( (object) $grid_data );
                    $row->id = $uniqid;
                    
                    TDataGrid::replaceRowById('etapas_list', $uniqid, $row);
                }   

            }
            else
            {
                $this->form->clear(TRUE);
            }

            TTransaction::close(); 
        }
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage()); 
        }

    }

    public function onSave( $param )
    {
        try
        {
            $this->form->validate(); 
            
            try
            {
                TTransaction::open('protheus'); 
                
                $data  = $this->form->getData(); 
                
                $object = new Zck010($data->R_E_C_N_O_);  
                
                $object->fromArray( (array) $data);

                if ($data->R_E_C_N_O_ == NULL)
                {
                    $object->ZCK_TIPO   = Zc9010::getNextCode(3,'','ZCK');
                    $object->ZCK_USUGIR = TSession::getValue('login');
                    $object->ZCK_DATA   = date('Ymd');
                    $object->ZCK_HORA   = date('H:i');
                }                    
                
                $object->store();
                
                $data->R_E_C_N_O_ = $object->R_E_C_N_O_;
                
                TTransaction::close(); 
                
                AdiantiCoreApplication::loadPage('AuditoriasTiposInspecaoList', 'onReload',['register_state' => 'false','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')] );
            }    
            catch (Exception $e) 
            {
                new TMessage('error', $e->getMessage()); 
                $this->form->setData($data); 
                TTransaction::rollback(); 
            }
    
        }
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage()); 
        }
        
    }    

    public function onItemAdd( $param )
    {    
        try
        {
            $this->form->validate();
            $data = $this->form->getData();

            if( !$data->etapa_detail_codigo )
            {
                throw new Exception('Informe o código da etapa.');
            }
            
            if( !empty($param['etapas_list_ZCL_ETAPA']) )
            {
                // checa se o item já foi lançado no formulário
                foreach( $param['etapas_list_ZCL_ETAPA'] as $key => $item_id )
                {
                    if($param['etapas_list_ZCL_ETAPA'][$key] == $data->etapa_detail_codigo && ( empty($data->etapa_detail_uniqid) || $param['etapas_list_uniqid'][$key] != $data->etapa_detail_uniqid ) ) 
                    {
                        throw new Exception('Etapa já lançada na lista.');
                    }
                }
            }

            // checa se o item já foi lançado em outro formulário
            TTransaction::open('protheus');

            /*
            *  Salva as informações do LOTE 
            */
            try
            {
                /*
                *  Salva as informações do LOTE 
                */
                if( empty($param['etapas_list_ZCL_ETAPA'] ) )
                {
                    $dataZCK = $this->form->getData(); 
                    
                    $object = new Zck010();  
                    
                    $object->fromArray((array) $dataZCK);

                    if ($data->R_E_C_N_O_ == NULL)
                    {
                        $object->ZCK_TIPO   = Zc9010::getNextCode(3,'','ZCK');
                        $object->ZCK_USUGIR = TSession::getValue('login');
                        $object->ZCK_DATA   = date('Ymd');
                        $object->ZCK_HORA   = date('H:i');
                    }                    
                    
                    $object->D_E_L_E_T_  = ' ';
                    
                    $object->store();
                    
                    $dataZCK->R_E_C_N_O_ = $object->R_E_C_N_O_;
                    
                    $data->R_E_C_N_O_    = $object->R_E_C_N_O_;

                    $param['ZCK_TIPO']   = $object->ZCK_TIPO;
                    $data->ZCK_TIPO      = $object->ZCK_TIPO;

                    TSession::setValue('AuditoriasTiposInspecaoList_select_row', $data->R_E_C_N_O_);
                }

                /*
                *  Salva as informações da etapa
                */
				$data->etapa_detail_data = ( empty($data->candidato_detail_data  ) ? date('Ymd') : $data->candidato_detail_data   );
				$data->etapa_detail_hora = ( empty($data->candidato_detail_hora  ) ? date('H:i') : $data->candidato_detail_hora   );
    
                $item = new Zcl010($data->etapa_detail_id);

				$item->ZCL_TIPO   = $param['ZCK_TIPO'];
				$item->ZCL_ETAPA  = $data->etapa_detail_codigo;
				$item->ZCL_SCORE  = $data->etapa_detail_score;

                if( $data->etapa_detail_id == '' )
                {
                    $item->ZCL_USUGIR = TSession::getValue('login');
                    $item->ZCL_DATA   = $data->etapa_detail_data;
                    $item->ZCL_HORA   = $data->etapa_detail_hora;
                }

                $item->store();

                /**
                 * Checagens necessárias para permitir a gravação do registro de candidato
                 */
                $data->etapa_detail_id = $item->R_E_C_N_O_;    
    
                $uniqid = !empty($data->etapa_detail_uniqid) ? $data->etapa_detail_uniqid : uniqid();
                
                $resultZCL = Zcl010::where('R_E_C_N_O_','=',$item->R_E_C_N_O_)->first();
                
                if($resultZCL)
                {
                    $etapa = Zcj010::where('ZCJ_ETAPA','=',$resultZCL->ZCL_ETAPA)->where('D_E_L_E_T_', '<>', '*')->first();

                    $grid_data = ['uniqid'       => $uniqid,
								  'id'           => $resultZCL->R_E_C_N_O_,
								  'ZCL_ETAPA'    => $resultZCL->ZCL_ETAPA,
                                  'ZCL_ETAPAD'   => $etapa->ZCJ_DESCRI,
                                  'ZCL_SCORE'    => $resultZCL->ZCL_SCORE,
								  'ZCL_DATA'     => $resultZCL->ZCL_DATA,
								  'ZCL_HORA'     => $resultZCL->ZCL_HORA
                                 ];
					
                    $row = $this->etapas_list->addItem( (object) $grid_data );
                    $row->id = $uniqid;
                    
                    TDataGrid::replaceRowById('etapas_list', $uniqid, $row);
                    
                    // clear product form fields after add
                    $data->etapa_detail_uniqid = '';
                    $data->etapa_detail_id     = '';
                    $data->etapa_detail_codigo = '';
                    $data->etapa_detail_score  = '1';
                                
                    // send data, do not fire change/exit events
                    TForm::sendData( 'form_Zck010', $data, false, false );

                    TEntry::disableField('form_Zck010','ZCK_TIPO');

                    TCombo::enableField('form_Zck010','etapa_detail_codigo');
                    TSpinner::enableField('form_Zck010','etapa_detail_score');
                }
                
                TTransaction::close();
    
            }
            catch (Exception $e)
            {
                TTransaction::rollback();

                new TMessage('error', $e->getMessage());
            }

        }
        catch (Exception $e)
        {
            $this->form->setData( $this->form->getData());
            new TMessage('error', $e->getMessage());
        }    
    }
    
    public static function onItemClear( $param )
    {    
        $data = new stdClass();

        // clear product form fields after add
        $data->etapa_detail_uniqid = ''; 
        $data->etapa_detail_id     = '';
        $data->etapa_detail_codigo = '';
        $data->etapa_detail_score  = '1';

        // send data, do not fire change/exit events
        TForm::sendData( 'form_Zck010', $data, false, false );

        TCombo::enableField('form_Zck010','etapa_detail_codigo');
        TSpinner::enableField('form_Zck010','etapa_detail_score');

    }    

    /**
     * Edit a product from item list
     * @param $param URL parameters
     */
    public static function onEditItem( $param )
    {
        try
        {
            $data = new stdClass;

            $data->etapa_detail_uniqid = $param['uniqid'];
            $data->etapa_detail_id     = $param['id'];
            $data->etapa_detail_codigo = $param['ZCL_ETAPA'];
            $data->etapa_detail_score  = $param['ZCL_SCORE'];
                
            // send data, do not fire change/exit events
            TForm::sendData( 'form_Zck010', $data, false, false );
        }
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage()); 
            TTransaction::rollback();
        }            
            
    }
    
    /**
     * Delete a product from item list
     * @param $param URL parameters
     */
    public static function onDelete( $param )
    {
        try
        {
            TTransaction::open('protheus');
            
            $Zcl010 = new Zcl010($param['id']);
            
            $etapa_encontrada = FALSE;
            
            if($Zcl010->R_E_C_N_O_ > 0)
            {
                $etapa_encontrada = TRUE;
                $Zcl010->D_E_L_E_T_   = '*';
                $Zcl010->R_E_C_D_E_L_ = $param['id'];
                
                $Zcl010->store();
            }
            
            TTransaction::close();
        
            $data = new stdClass;
            
            $data->etapa_detail_uniqid = '';
            $data->etapa_detail_id     = '';
            $data->etapa_detail_codigo = '';
            $data->etapa_detail_score  = '1';
                
            // send data, do not fire change/exit events
            TForm::sendData( 'form_Zck010', $data, false, false );
            
            // remove row
            if($etapa_encontrada == TRUE)
            {
                TDataGrid::removeRowById('etapas_list', $param['uniqid']);
            }
        }
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage()); 
            TTransaction::rollback();
        }

    }    

}

