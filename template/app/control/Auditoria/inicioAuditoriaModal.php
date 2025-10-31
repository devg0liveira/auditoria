<?php
// app/control/InicioAuditoriaModal.php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Container\TPanel;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Base\TScript;

class inicioAuditoriaModal extends TPage
{
    protected $form;

    /**
     * Construtor
     */
    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_inicio');
        $this->form->setFormTitle('Nova Auditoria');

        // === CAMPOS ===
        $filial = new TEntry('ZCM_FILIAL');
        $tipo = new TCombo('ZCM_TIPO');

        // === TIPOS FIXOS (fallback) ===
        $itensFixos = [
            'A'  => 'Almoxarifado',
            'NF' => 'Entradas de notas fiscais',
            'ST' => 'Segurança do trabalho - depósito de ferramentas operacionais de rotas',
            'CC' => 'Controle de combustível interno e externo',
            'AA' => 'Ordem e serviço',
            'VI' => 'Vistoria de veículos',
            'M'  => 'Manutenção',
            'BM' => 'Boletim de medição',
            'DP' => 'Departamento pessoal',
            'FP' => 'Folha de pagamento',
            'F'  => 'Férias',
            'EJ' => 'Estagiário / Jovem aprendiz'
        ];

        // === CARREGA TIPOS DA TABELA ZCK010 ===
        $items = ['' => 'Selecione um tipo'];

        try {
            TTransaction::open('auditoria');

            $tipos = ZCK010::where('D_E_L_E_T_', '<>', '*')
                ->orderBy('ZCK_DESCRI')
                ->load();

            foreach ($tipos as $t) {
                $items[$t->ZCK_TIPO] = $t->ZCK_DESCRI;
            }

            // Adiciona tipos fixos que ainda não existem na tabela
            foreach ($itensFixos as $cod => $desc) {
                if (!isset($items[$cod])) {
                    $items[$cod] = $desc;
                }
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar tipos: ' . $e->getMessage());
            $items = array_merge(['' => 'Selecione um tipo'], $itensFixos);
        }

        // === ADICIONA OPÇÃO DE CRIAR NOVO TIPO ===
        $items['NOVO'] = '-- Criar novo tipo --';
        $tipo->addItems($items);

        // === CAMPOS PARA NOVO TIPO (inicialmente ocultos) ===
        $novoTipoCod = new TEntry('NOVO_TIPO_COD');
        $novoTipoDesc = new TEntry('NOVO_TIPO_DESC');

        $novoTipoCod->setSize('100%');
        $novoTipoDesc->setSize('100%');
        $novoTipoCod->placeholder = 'Ex: XX (máx. 3 letras)';
        $novoTipoDesc->placeholder = 'Descrição completa do novo tipo';

        $novoContainer = new TElement('div');
        $novoContainer->id = 'novo-tipo-container';
        $novoContainer->style = 'display: none; margin-top: 15px; padding: 10px; border: 1px dashed #ccc; border-radius: 5px;';
        $novoContainer->add(new TLabel('Código do novo tipo:'));
        $novoContainer->add($novoTipoCod);
        $novoContainer->add(new TLabel('Descrição do novo tipo:'));
        $novoContainer->add($novoTipoDesc);

        // === ADICIONA O CONTAINER AO FORMULÁRIO ===
        $this->form->addContent([$novoContainer]);

        // === JAVASCRIPT COM TScript::create() (MÉTODO CORRETO) ===
        $script = <<<JS
        document.addEventListener('DOMContentLoaded', function() {
            const combo = document.querySelector('[name="ZCM_TIPO"]');
            const container = document.getElementById('novo-tipo-container');
            
            if (combo && container) {
                const toggle = () => {
                    container.style.display = combo.value === 'NOVO' ? 'block' : 'none';
                };
                combo.addEventListener('change', toggle);
                toggle(); // estado inicial
            }
        });
JS;

        TScript::create($script);

        // === CONFIGURAÇÕES DOS CAMPOS ===
        $filial->setValue(TSession::getValue('filial') ?? '');
        $filial->setSize('100%');
        $tipo->setSize('100%');

        // === MONTAGEM DO FORMULÁRIO ===
        $this->form->addFields([new TLabel('Filial *', '#333')], [$filial]);
        $this->form->addFields([new TLabel('Tipo de Auditoria *', '#333')], [$tipo]);
        $this->form->addContent([$novoContainer]);

        $this->form->addAction('Avançar', new TAction([$this, 'onAvancar']), 'fa:arrow-right green');

        parent::add($this->form);
    }

    /**
     * Abre a modal de nova auditoria
     */
    public static function onOpenCurtain($param = null)
    {
        try {
            $page = TWindow::create('Nova Auditoria', 600, null);
            $page->removePadding();

            $embed = new self();

            $page->add($embed->form);
            $page->setIsWrapped(true);
            $page->show();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Método executado ao clicar em Avançar
     */
    public function onAvancar($param)
    {
        try {
            $data = $this->form->getData();

            // === VALIDAÇÕES BÁSICAS ===
            if (empty($data->ZCM_FILIAL)) {
                throw new Exception('Informe a filial.');
            }

            if (empty($data->ZCM_TIPO)) {
                throw new Exception('Selecione o tipo de auditoria.');
            }

            $tipoFinal = $data->ZCM_TIPO;

            // === SE FOR CRIAR NOVO TIPO ===
            if ($data->ZCM_TIPO === 'NOVO') {
                $novoCod = strtoupper(trim($data->NOVO_TIPO_COD ?? ''));
                $novoDesc = trim($data->NOVO_TIPO_DESC ?? '');

                if (empty($novoCod) || empty($novoDesc)) {
                    throw new Exception('Preencha código e descrição do novo tipo.');
                }

                if (strlen($novoCod) > 3) {
                    throw new Exception('O código do tipo deve ter no máximo 3 caracteres.');
                }

                if (!preg_match('/^[A-Z0-9]+$/', $novoCod)) {
                    throw new Exception('O código deve conter apenas letras maiúsculas e números.');
                }

                TTransaction::open('auditoria');

                // Verifica duplicidade
                $existe = ZCK010::where('ZCK_TIPO', '=', $novoCod)
                    ->where('D_E_L_E_T_', '<>', '*')
                    ->first();

                if ($existe) {
                    TTransaction::close();
                    throw new Exception("O tipo '{$novoCod}' já existe.");
                }

                // Cria novo tipo
                $novoTipo = new ZCK010();
                $novoTipo->ZCK_TIPO = $novoCod;
                $novoTipo->ZCK_DESCRI = $novoDesc;
                $novoTipo->D_E_L_E_T_ = '';
                $novoTipo->store();

                TTransaction::close();

                $tipoFinal = $novoCod;

                new TMessage('info', "Novo tipo '{$novoCod}' criado com sucesso!");
            }

            // === SALVA NA SESSÃO E AVANÇA ===
            TSession::setValue('auditoria_filial', $data->ZCM_FILIAL);
            TSession::setValue('auditoria_tipo', $tipoFinal);

            // Limpa campos temporários
            $this->form->clear();

            // Abre o CheckListForm em modal
            CheckListForm::onOpenCurtain([
                'filial' => $data->ZCM_FILIAL,
                'tipo' => $tipoFinal
            ]);

            // Fecha a janela atual após abrir a próxima
            TWindow::closeWindow();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (isset($data)) {
                $this->form->setData($data);
            }
        }
    }

    /**
     * Limpa o formulário
     */
    public function onClear()
    {
        $this->form->clear();
    }
}
