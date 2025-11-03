<?php

use Adianti\Control\TPage;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Control\TAction;
use Adianti\Database\TRepository;
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

        // === CARREGA FILIAIS ===
        $filiais = $this->carregarFiliais();
        $filial = new TCombo('ZCK_FILIAL');
        $filial->addItems($filiais);
        $filial->setSize('70%');
        $filial->setDefaultOption('Selecione a filial...');

        // === CARREGA TIPOS ===
        $tipo = new TDBCombo('zck010', 'auditoria', 'ZCK010', 'ZCK_TIPO', 'ZCK_DESCRI', 'ZCK_DESCRI');
        $tipo->setSize('70%');
        $tipo->setDefaultOption('Selecione o tipo...');

        // === BOTÃO CONFIRMAR ===
        $btn_confirmar = new TButton('btn_confirmar');
        $btn_confirmar->setLabel('Iniciar Auditoria');
        $btn_confirmar->setImage('fa:play-circle green');
        $btn_confirmar->setAction(new TAction([$this, 'onConfirmar']));

        // === MONTAGEM DO FORMULÁRIO ===
        $this->form->addFields([new TLabel('Filial <span style="color:red">*</span>:')], [$filial]);
        $this->form->addFields([new TLabel('Tipo <span style="color:red">*</span>:')], [$tipo]);
        $this->form->addFields([], [$btn_confirmar]);

        $this->form->setFields([$filial, $tipo, $btn_confirmar]);

        // === CONTAINER ===
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

    /**
     * Carrega filiais únicas do ZCK010
     */
    private function carregarFiliais()
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $sql = "
                SELECT DISTINCT ZCK_FILIAL, ZCK_DESCRI
                FROM ZCK010
                WHERE D_E_L_E_T_ <> '*'
                  AND ZCK_FILIAL IS NOT NULL
                  AND ZCK_DESCRI IS NOT NULL
                ORDER BY ZCK_DESCRI
            ";

            $result = $conn->query($sql);
            $items = [];
            foreach ($result as $row) {
                $cod = trim($row['ZCK_FILIAL']);
                $nome = trim($row['ZCK_DESCRI']);
                if ($cod && $nome) {
                    $items[$cod] = $nome;
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

    /**
     * Ação do botão confirmar
     */
    public static function onConfirmar($param)
    {
        try {
            $data = $param;

            // Validações
            if (empty($data['ZCK_FILIAL'])) {
                throw new Exception('Selecione a filial.');
            }
            if (empty($data['ZCK_DESCRI'])) {
                throw new Exception('Selecione o tipo de auditoria.');
            }

            TTransaction::open('auditoria');

            // === GERA NÚMERO DA AUDITORIA (ZCK_DOC) ===
            $ano = date('Y');
            $seq = ZCK010::where('ZCK_TIPO', 'LIKE', $ano . '%')
                ->where('D_E_L_E_T_', '<>', '*')
                ->count();
            $seq = str_pad($seq + 1, 4, '0', STR_PAD_LEFT);
            $doc = $ano . $seq;

            // === CRIA AUDITORIA ===
            $auditoria = new ZCK010;
            $auditoria->ZCK_FILIAL = $data['ZCK_FILIAL'];
            $auditoria->ZCK_TIPO   = $data['ZCK_DESCRI']; // Usa tipo selecionado
            $auditoria->ZCK_DESCRI = $data['ZCK_DESCRI']; // Nome do tipo
            $auditoria->ZCK_DATA   = date('Ymd');
            $auditoria->ZCK_HORA   = date('His');
            $auditoria->ZCK_USUGIR = TSession::getValue('userid') ?? 'SYSTEM';
            $auditoria->ZCK_OBS    = '';
            $auditoria->store();

            TTransaction::close();

            // === ABRE CHECKLIST ===
            TScript::create("
                setTimeout(function() {
                    __adianti_load_page('index.php?class=CheckListForm&method=onOpenCurtain&filial={$data['ZCK_FILIAL']}&tipo={$data['ZCK_DESCRI']}&doc={$doc}');
                }, 800);
            ");

            new TMessage('info', "Auditoria {$doc} iniciada com sucesso!");

        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Abre modal
     */
    public static function onOpenCurtain($param)
    {
        $page = \Adianti\Control\TWindow::create('Iniciar Nova Auditoria', 0.6, 0.5);
        $page->add(new self());
        $page->show();
    }
}
