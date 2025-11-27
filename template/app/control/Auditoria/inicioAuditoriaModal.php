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

class inicioAuditoriaModal extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_inicio_auditoria');
        $this->form->setFormTitle('Iniciar Nova Auditoria');

        $this->form->addAction('Voltar', new TAction(['HistoricoList', 'onReload']), 'fa:arrow-left');

        $filiais = $this->carregarFiliais();
        $filial = new TCombo('filial');
        $filial->addItems($filiais);
        $filial->setSize('100%');
        $filial->setDefaultOption('Selecione a filial...');
        $filial->addValidation('Filial', new \Adianti\Validator\TRequiredValidator);

        $btn_confirmar = new TButton('btn_confirmar');
        $btn_confirmar->setLabel('Iniciar Auditoria Completa');
        $btn_confirmar->setImage('fa:play-circle green');
        $btn_confirmar->setAction(new TAction([$this, 'onConfirmar']));

        $this->form->addFields([new TLabel('Filial <span style="color:red">*</span>:')], [$filial]);
        $this->form->addFields([], [$btn_confirmar]);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

     public function onLoad($param = null) {}

    private function carregarFiliais()
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $sql = "SELECT DISTINCT ZCK_FILIAL FROM ZCK010 WHERE D_E_L_E_T_ <> '*' AND ZCK_FILIAL IS NOT NULL AND LTRIM(RTRIM(ZCK_FILIAL)) <> '' ORDER BY ZCK_FILIAL";
            $result = $conn->query($sql);
            $items = [];
            foreach ($result as $row) {
                $cod = trim($row['ZCK_FILIAL']);
                if ($cod) $items[$cod] = $cod;
            }
            TTransaction::close();
            return $items;
        } catch (Exception $e) {
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

            TSession::setValue('auditoria_filial', $param['filial']);
            // Não precisamos mais do tipo aqui

            TScript::create("
                __adianti_load_page('index.php?class=checkListForm&method=onStart&filial={$param['filial']}');
            ");
        } catch (Exception $e) {
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