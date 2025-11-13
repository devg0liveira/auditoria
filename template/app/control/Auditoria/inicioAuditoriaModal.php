<?php

use Adianti\Control\TPage;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Control\TAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Base\TScript;
use Adianti\Registry\TSession;
use Adianti\Widget\Wrapper\TDBCombo;

class inicioAuditoriaModal extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_inicio_auditoria');
        $this->form->setFormTitle('Iniciar Nova Auditoria');

        $filiais = $this->carregarFiliais();
        $filial = new TCombo('filial');
        $filial->addItems($filiais);
        $filial->setSize('70%');
        $filial->setDefaultOption('Selecione a filial...');

        $tipo = new TDBCombo('tipo', 'auditoria', 'ZCK010', 'ZCK_TIPO', 'ZCK_DESCRI', 'ZCK_TIPO');
        $tipo->setSize('70%');
        $tipo->setDefaultOption('Selecione o tipo...');

        $btn_confirmar = new TButton('btn_confirmar');
        $btn_confirmar->setLabel('Iniciar Auditoria');
        $btn_confirmar->setImage('fa:play-circle green');
        $btn_confirmar->setAction(new TAction([$this, 'onConfirmar']));

        $this->form->addFields([new TLabel('Filial <span style="color:red">*</span>:')], [$filial]);
        $this->form->addFields([new TLabel('Tipo <span style="color:red">*</span>:')], [$tipo]);
        $this->form->addFields([], [$btn_confirmar]);

        $this->form->setFields([$filial, $tipo, $btn_confirmar]);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

     public function onLoad($param = null)
     {
        
     }
    

    private function carregarFiliais()
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $sql = "
                SELECT DISTINCT ZCK_FILIAL
                FROM ZCK010
                WHERE D_E_L_E_T_ <> '*'
                  AND ZCK_FILIAL IS NOT NULL
                  AND LTRIM(RTRIM(ZCK_FILIAL)) <> ''
                ORDER BY ZCK_FILIAL
            ";

            $result = $conn->query($sql);
            $items = [];
            foreach ($result as $row) {
                $cod = trim($row['ZCK_FILIAL']);
                if ($cod) {
                    $items[$cod] = $cod;
                }
            }

            TTransaction::close();
            return $items;

        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', 'Erro ao carregar filiais: ' . $e->getMessage());
            return [];
        }
    }

    public static function onConfirmar($param)
    {
        try {
            if (empty($param['filial'])) {
                throw new Exception('Selecione a filial.');
            }
            if (empty($param['tipo'])) {
                throw new Exception('Selecione o tipo de auditoria.');
            }

            TTransaction::open('auditoria');

            $tipoObj = ZCK010::find($param['tipo']);
            if (!$tipoObj || $tipoObj->D_E_L_E_T_ === '*') {
                throw new Exception('Tipo de auditoria não encontrado.');
            }

            TTransaction::close();

            TSession::setValue('auditoria_filial', $param['filial']);
            TSession::setValue('auditoria_tipo', $param['tipo']);

            TScript::create("
                __adianti_load_page('index.php?class=checkListForm&method=onStart&filial={$param['filial']}&tipo={$param['tipo']}');
            ");

        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    
    public static function onOpenCurtain($param)
    {
        $page = \Adianti\Control\TWindow::create('Iniciar Nova Auditoria', 0.6, 0.5);
        $page->add(new self());
        $page->show();
    }
}