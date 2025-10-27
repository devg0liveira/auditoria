<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * @version    3.0
 * @create     01/02/2024 - 14:28
 * @update     
 * @author     Josivaldo Santiago Barbosa
 * @copyright  Copyright (c) 2021 JSB Informática (http://www.jsbinformatica.com.br)
 */
class AuditoriasEtapasGenericasForm extends TPage
{
    
    /**
     * Form constructor
     * @param $param Request
     */
    public function __construct( $param )
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_Zcj010');
        $this->form->setFormTitle('Etapa Genérica de Inspeção de Auditoria');
        $this->form->setClientValidation(true);

        // create the form fields
        $ZCJ_ETAPA  = new TEntry('ZCJ_ETAPA');
        $ZCJ_ETAPA->setEditable(false);

        $ZCJ_DESCRI = new TText('ZCJ_DESCRI');

        $ZCJ_DESCRI->addValidation( 'Descrição', new TRequiredValidator ); 

        $R_E_C_N_O_ = new THidden('R_E_C_N_O_');

        $this->form->addFields( [ $R_E_C_N_O_ ] );
        $this->form->addFields( [ new TLabel('Código')     ], [ $ZCJ_ETAPA ], [], [], [], [] );
        $this->form->addFields( [ new TLabel('Descrição' ) ], [ $ZCJ_DESCRI] );

        $ZCJ_ETAPA->setSize('100%');
        $ZCJ_DESCRI->setSize('100%');

        $ZCJ_ETAPA->setMaxLength(6);
        $ZCJ_ETAPA->forceUpperCase();

        $this->form->addHeaderActionLink(_t('Back'), new TAction(['AuditoriasEtapasGenericasList', 'onReload'],['register_state' => 'false','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')] ), 'far:arrow-alt-circle-left blue');

        $this->form->addAction( _t('Save'), new TAction([$this, 'onSave'], ['static'=>'1','register_state' => 'false','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')]), 'fa:save green');
        $this->form->addAction( _t('Back'), new TAction(['AuditoriasEtapasGenericasList', 'onReload'], ['register_state' => 'false','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')]), 'far:arrow-alt-circle-left blue');

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
                $Zcj010 = new Zcj010($param['key']);
                $this->form->setData($Zcj010);
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
            
                try
                {
                    TTransaction::open('protheus'); 
                    
                    $data  = $this->form->getData(); 
                    
                    $object = new Zcj010();  
                    
                    $object->fromArray( (array) $data);

                    if ($data->R_E_C_N_O_ == NULL)
                    {
                        $object->ZCJ_ETAPA  = Zc9010::getNextCode(6,'','ZCJ');
                        $object->ZCJ_USUGIR = TSession::getValue('login');
                        $object->ZCJ_DATA   = date('Ymd');
                        $object->ZCJ_HORA   = date('H:i');
                    }                    
                    
                    $object->store();
                    
                    $data->R_E_C_N_O_ = $object->R_E_C_N_O_;
                    
                    TTransaction::close(); 
                    
                    AdiantiCoreApplication::loadPage('AuditoriasEtapasGenericasList', 'onReload',['register_state' => 'false','offset' => (isset($_GET['offset'])?$_GET['offset']:'0'),'limit' => (isset($_GET['limit'])?$_GET['limit']:'10'), 'page' => (isset($_GET['page'])?$_GET['page']:'1'), 'first_page' => (isset($_GET['first_page'])?$_GET['first_page']:'1')] );
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
        catch (Exception $e) 
        {
            new TMessage('error', $e->getMessage()); 
        }
        
    }    

}

