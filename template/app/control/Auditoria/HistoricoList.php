<?php

use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TRadioGroup;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Base\TScript;

class CheckListForm extends TPage
{
    protected $form;
    protected $perguntas = [];

    /**
     * Construtor
     */
    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setFormTitle('CheckList de Auditoria');

        parent::add($this->form);
    }

    /**
     * Inicia uma nova auditoria e carrega perguntas
     */
    public function onStart($param)
    {
        try {
            $filial = $param['filial'] ?? TSession::getValue('auditoria_filial');
            $tipo = $param['tipo'] ?? TSession::getValue('auditoria_tipo');

            if (!$filial || !$tipo) {
                throw new Exception('Filial e tipo de auditoria são obrigatórios.');
            }

            TTransaction::open('auditoria');

            // Busca dados do tipo
            $tipoObj = ZCK010::find($tipo);
            if (!$tipoObj) {
                throw new Exception('Tipo de auditoria não encontrado.');
            }

            // Busca perguntas vinculadas ao tipo
            $perguntas = ZCJ010::where('ZCJ_TIPO', '=', $tipo)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->orderBy('ZCJ_ETAPA')
                               ->load();

            if (!$perguntas || count($perguntas) === 0) {
                throw new Exception('Nenhuma pergunta encontrada para este tipo de auditoria.');
            }

            TTransaction::close();

            // Reconstrói o formulário com as perguntas
            $this->form = new BootstrapFormBuilder('form_checklist');
            $this->form->setFormTitle("CheckList: {$tipoObj->ZCK_DESCRI} - Filial: {$filial}");

            // Campos hidden para passar dados
            $hidden_filial = new \Adianti\Widget\Form\THidden('filial');
            $hidden_tipo = new \Adianti\Widget\Form\THidden('tipo');
            $this->form->addFields([$hidden_filial, $hidden_tipo]);

            // Renderiza cada pergunta
            foreach ($perguntas as $pergunta) {
                $this->renderPergunta($pergunta);
            }

            // Botão salvar
            $btn_salvar = new TButton('btn_salvar');
            $btn_salvar->setLabel('Finalizar Auditoria');
            $btn_salvar->setImage('fa:check green');
            $btn_salvar->setAction(new \Adianti\Control\TAction([$this, 'onSave']), 'Salvar');

            $this->form->addFields([], [$btn_salvar]);

            // Define dados iniciais
            $data = new \stdClass;
            $data->filial = $filial;
            $data->tipo = $tipo;
            $this->form->setData($data);

            // Substitui o conteúdo da página
            parent::add($this->form);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) TTransaction::rollback();
        }
    }

    /**
     * Renderiza uma pergunta no formulário
     */
    private function renderPergunta($pergunta)
    {
        $etapa = $pergunta->ZCJ_ETAPA;
        $desc = $pergunta->ZCJ_DESCRI;

        // Campo de resposta (Sim/Não/N/A)
        $resposta = new TRadioGroup("resposta_{$etapa}");
        $resposta->addItems([
            'S' => 'Sim',
            'N' => 'Não',
            'NA' => 'N/A'
        ]);
        $resposta->setLayout('horizontal');
        $resposta->setUseButton();

        // Campo de observação
        $obs = new TText("obs_{$etapa}");
        $obs->setSize('100%', 60);
        $obs->placeholder = 'Observações (opcional)';

        // Adiciona ao formulário
        $this->form->addContent([new TLabel("<b>Etapa {$etapa}:</b> {$desc}", '', 14, 'b')]);
        $this->form->addFields([new TLabel('Resposta:')], [$resposta]);
        $this->form->addFields([new TLabel('Observação:')], [$obs]);
        $this->form->addContent(['<hr>']);
    }

    /**
     * Salva as respostas na tabela ZCL010
     */
    public static function onSave($param)
    {
        try {
            $filial = $param['filial'] ?? null;
            $tipo = $param['tipo'] ?? null;

            if (!$filial || !$tipo) {
                throw new Exception('Dados da auditoria não encontrados.');
            }

            TTransaction::open('auditoria');

            // Busca perguntas do tipo
            $perguntas = ZCJ010::where('ZCJ_TIPO', '=', $tipo)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->load();

            $salvou_alguma = false;

            foreach ($perguntas as $pergunta) {
                $etapa = $pergunta->ZCJ_ETAPA;
                $resposta = $param["resposta_{$etapa}"] ?? null;

                // Só salva se tiver resposta
                if ($resposta) {
                    $zcl = new ZCL010;
                    $zcl->ZCL_FILIAL = $filial;
                    $zcl->ZCL_TIPO   = $tipo;
                    $zcl->ZCL_ETAPA  = $etapa;
                    $zcl->ZCL_RESPOSTA = $resposta;
                    $zcl->ZCL_OBS    = $param["obs_{$etapa}"] ?? '';
                    $zcl->ZCL_DATA   = date('Ymd');
                    $zcl->ZCL_HORA   = date('His');
                    $zcl->ZCL_USUARIO = TSession::getValue('userid') ?? 'SYSTEM';
                    $zcl->store();

                    $salvou_alguma = true;
                }
            }

            TTransaction::close();

            if (!$salvou_alguma) {
                throw new Exception('Nenhuma resposta foi preenchida.');
            }

            new TMessage('info', 'Auditoria finalizada com sucesso!');

            // Fecha a janela modal
            TScript::create("
                setTimeout(function() {
                    Adianti.currentWindow.close();
                }, 1500);
            ");

        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Abre o CheckListForm em janela modal
     */
    public static function onOpenCurtain($param)
    {
        try {
            $page = TWindow::create('CheckList de Auditoria', 0.9, 0.9);
            $page->removePadding();

            $embed = new self();
            $embed->onStart($param);

            $page->add($embed);
            $page->setIsWrapped(true);
            $page->show();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }
}