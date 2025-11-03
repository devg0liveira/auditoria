<?php

use Adianti\Control\TPage;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Control\TAction;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
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

        // === CARREGA FILIAIS (ZCK010 - distinct ZCK_FILIAL + ZCK_DESCRI) ===
        $filiais = $this->carregarFiliais();
        $filial = new TCombo('ZCK_FILIAL');
        $filial->addItems($filiais);
        $filial->setSize('70%');
        $filial->setDefaultOption('Selecione a filial...');

        // === CARREGA TIPOS (ZCK010 - distinct ZCK_DESCRI) ===
        $tipos = $this->carregarTipos();
        $tipo = new TCombo('ZCK_DESCRI');
        $tipo->addItems($tipos);
        $tipo->setSize('70%');
        $tipo->setDefaultOption('Selecione o tipo...');

        // === CAMPO NOVO TIPO (oculto inicialmente) ===
        $novo_tipo = new \Adianti\Widget\Form\TEntry('novo_tipo');
        $novo_tipo->setSize('70%');
        $novo_tipo->placeholder = 'Digite o novo tipo de auditoria...';
        $novo_tipo->style = 'display:none';

        // === BOTÃO CONFIRMAR ===
        $btn_confirmar = new TButton('btn_confirmar');
        $btn_confirmar->setLabel('Iniciar Auditoria');
        $btn_confirmar->setImage('fa:play-circle green');
        $btn_confirmar->setAction(new TAction([$this, 'onConfirmar']));

        // === MONTAGEM DO FORMULÁRIO ===
        $this->form->addFields([new TLabel('Filial <span style="color:red">*</span>:')], [$filial]);
        $this->form->addFields([new TLabel('Tipo <span style="color:red">*</span>:')], [$tipo]);
        $this->form->addFields([new TLabel('Novo Tipo:')], [$novo_tipo]);
        $this->form->addFields([], [$btn_confirmar]);

        $this->form->setFields([$filial, $tipo, $novo_tipo, $btn_confirmar]);

        // === SCRIPT PARA MOSTRAR CAMPO NOVO TIPO ===
        $script = <<<JS
        document.addEventListener('DOMContentLoaded', function() {
            const comboTipo = document.querySelector('[name="ZCK_DESCRI"]');
            const inputNovoTipo = document.querySelector('[name="novo_tipo"]');
            const labelNovoTipo = inputNovoTipo.closest('.form-row').querySelector('.control-label');

            if (comboTipo && inputNovoTipo) {
                function toggleNovoTipo() {
                    const isNovo = comboTipo.value === 'NOVO';
                    inputNovoTipo.style.display = isNovo ? 'block' : 'none';
                    labelNovoTipo.style.display = isNovo ? 'block' : 'none';
                    if (isNovo) inputNovoTipo.focus();
                    else inputNovoTipo.value = '';
                }
                comboTipo.addEventListener('change', toggleNovoTipo);
                toggleNovoTipo();
            }
        });
JS;
        TScript::create($script);

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
     * Carrega tipos únicos do ZCK010 (baseado em ZCK_DESCRI)
     */
   private function carregarTipos()
{
    try {
        TTransaction::open('auditoria');
        $conn = TTransaction::get();

        $sql = "
            SELECT DISTINCT ZCK_DESCRI
            FROM ZCK010
            WHERE D_E_L_E_T_ <> '*'
              AND ZCK_DESCRI IS NOT NULL
              AND ZCK_DESCRI <> ''
            ORDER BY ZCK_DESCRI
        ";

        $result = $conn->query($sql);

        $items = [];
        foreach ($result as $row) {
            $descricao = trim($row['ZCK_DESCRI']);
            if ($descricao) {
                $items[$descricao] = $descricao;
            }
        }

        $items['NOVO'] = 'Criar Novo Tipo...';

        TTransaction::close();
        return $items;

    } catch (Exception $e) {
        if (TTransaction::get()) {
            TTransaction::rollback();
        }
        new TMessage('error', 'Erro ao carregar tipos: ' . $e->getMessage());
        return ['NOVO' => 'Criar Novo Tipo...'];
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
            if (empty($data['ZCK_DESCRI']) || $data['ZCK_DESCRI'] === 'NOVO') {
                if (empty($data['novo_tipo'])) {
                    throw new Exception('Digite o novo tipo de auditoria.');
                }
            }

            TTransaction::open('auditoria');

            // === DEFINE TIPO FINAL (ZCK_DESCRI) ===
            $tipoDescricao = $data['ZCK_DESCRI'];
            if ($data['ZCK_DESCRI'] === 'NOVO') {
                $tipoDescricao = trim($data['novo_tipo']);
            }

            // === GERA PRÓXIMO ZCK_TIPO (ID numérico sequencial) ===
            $maxTipo = ZCK010::max('ZCK_TIPO') ?? 0;
            $novoTipoId = str_pad($maxTipo + 1, 3, '0', STR_PAD_LEFT); // 001, 002...

            // === GERA NÚMERO DA AUDITORIA (ZCK_DOC) ===
            $ano = date('Y');
            $seq = ZCK010::where('ZCK_DOC', 'LIKE', $ano . '%')
                ->where('D_E_L_E_T_', '<>', '*')
                ->count();
            $seq = str_pad($seq + 1, 4, '0', STR_PAD_LEFT);
            $doc = $ano . $seq;

            // === CRIA AUDITORIA ===
            $auditoria = new ZCK010;
            $auditoria->ZCK_FILIAL = $data['ZCK_FILIAL'];
            $auditoria->ZCK_TIPO   = $novoTipoId;                    // Sempre novo ID
            $auditoria->ZCK_DESCRI = $tipoDescricao;                 // Nome do tipo
            $auditoria->ZCK_DATA   = date('Ymd');                    // Hoje
            $auditoria->ZCK_HORA   = date('His');                    // Agora
            $auditoria->ZCK_USUGIR = TSession::getValue('userid') ?? 'SYSTEM';
            $auditoria->ZCK_DOC    = $doc;
            $auditoria->ZCK_OBS    = '';                             // Será preenchido no checklist
            $auditoria->store();

            TTransaction::close();

            // === ABRE CHECKLIST ===
            TScript::create("
                setTimeout(function() {
                    __adianti_load_page('index.php?class=CheckListForm&method=onOpenCurtain&filial={$data['ZCK_FILIAL']}&tipo={$novoTipoId}&doc={$doc}');
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
