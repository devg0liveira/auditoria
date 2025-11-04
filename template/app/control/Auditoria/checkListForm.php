<?php

use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TAlert;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Base\TScript;

class checkListForm extends TPage
{
    protected $form;

    public function __construct()
    {
        parent::__construct();
        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setFormTitle('CheckList de Auditoria');
        parent::add($this->form);
    }

    /**
     * Carrega checklist com TCombo + histórico
     */
    public function onStart($param)
    {
        try {
            $filial = $param['filial'] ?? TSession::getValue('auditoria_filial');
            $tipo   = $param['tipo']   ?? TSession::getValue('auditoria_tipo');

            if (!$filial || !$tipo) {
                throw new Exception('Filial e tipo são obrigatórios.');
            }

            TTransaction::open('auditoria');

            // === TIPO DE AUDITORIA ===
            $tipoObj = ZCK010::find($tipo);
            if (!$tipoObj || $tipoObj->D_E_L_E_T_ === '*') {
                throw new Exception('Tipo não encontrado.');
            }

            // === PERGUNTAS (ZCJ010) ===
            $perguntas = ZCJ010::where('ZCJ_TIPO', '=', $tipo)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->orderBy('ZCJ_ETAPA')
                               ->load();

            if (empty($perguntas)) {
                throw new Exception('Nenhuma pergunta cadastrada para este tipo.');
            }

            // === RESPOSTAS ANTERIORES (ZCL010) ===
            $respostas_anteriores = [];
            $historico = ZCL010::where('ZCL_FILIAL', '=', $filial)
                               ->where('ZCL_TIPO', '=', $tipo)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->load();

            foreach ($historico as $h) {
                $respostas_anteriores[$h->ZCL_ETAPA] = $h->ZCL_RESPOSTA;
            }

            TTransaction::close();

            // === RECONSTRÓI FORMULÁRIO ===
            $this->form = new BootstrapFormBuilder('form_checklist');
            $this->form->setFormTitle("CheckList: {$tipoObj->ZCK_DESCRI} - Filial: {$filial}");
            $this->form->setColumnClasses(2, ['col-sm-8', 'col-sm-4']);

            // Hidden
            $this->form->addFields([
                new \Adianti\Widget\Form\THidden('filial'),
                new \Adianti\Widget\Form\THidden('tipo')
            ]);
            $this->form->setData((object)['filial' => $filial, 'tipo' => $tipo]);

            // === OPÇÕES DO COMBO ===
            $opcoes = [
                'C'  => 'Conforme',
                'NC' => 'Não Conforme',
                'OP' => 'Oportunidade de melhoria',
                'P'  => 'Parcialmente',
                'NV' => 'Não visto'
            ];

            // === RENDERIZA CADA PERGUNTA ===
            foreach ($perguntas as $p) {
                $etapa = $p->ZCJ_ETAPA;
                $desc  = $p->ZCJ_DESCRI;

                $combo = new TCombo("resposta_{$etapa}");
                $combo->addItems($opcoes);
                $combo->setSize('100%');
                $combo->setValue($respostas_anteriores[$etapa] ?? 'C'); // padrão = C

                $this->form->addFields(
                    [new TLabel("<b>Etapa {$etapa}:</b> {$desc}")],
                    [$combo]
                );
            }

            // === BOTÃO SALVAR ===
            $btn = new TButton('salvar');
            $btn->setLabel('Finalizar Auditoria');
            $btn->setImage('fa:check green');
            $btn->setAction(new \Adianti\Control\TAction([$this, 'onSave']));

            $this->form->addFields([], [$btn]);

            parent::add($this->form);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) TTransaction::rollback();
        }
    }

    /**
     * Salva respostas no ZCL010
     */
    public static function onSave($param)
    {
        try {
            $filial = $param['filial'] ?? null;
            $tipo   = $param['tipo'] ?? null;

            if (!$filial || !$tipo) {
                throw new Exception('Dados inválidos.');
            }

            TTransaction::open('auditoria');

            $perguntas = ZCJ010::where('ZCJ_TIPO', '=', $tipo)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->load();

            $salvo = false;

            foreach ($perguntas as $p) {
                $etapa = $p->ZCJ_ETAPA;
                $resposta = $param["resposta_{$etapa}"] ?? null;

                if ($resposta) {
                    $zcl = ZCL010::where('ZCL_FILIAL', '=', $filial)
                                 ->where('ZCL_TIPO', '=', $tipo)
                                 ->where('ZCL_ETAPA', '=', $etapa)
                                 ->first();

                    if (!$zcl) {
                        $zcl = new ZCL010;
                        $zcl->ZCL_FILIAL = $filial;
                        $zcl->ZCL_TIPO   = $tipo;
                        $zcl->ZCL_ETAPA  = $etapa;
                    }

                    $zcl->ZCL_RESPOSTA = $resposta;
                    $zcl->ZCL_DATA     = date('Ymd');
                    $zcl->ZCL_HORA     = date('His');
                    $zcl->ZCL_USUARIO  = TSession::getValue('userid') ?? 'SYSTEM';
                    $zcl->store();

                    $salvo = true;
                }
            }

            TTransaction::close();

            if (!$salvo) {
                throw new Exception('Nenhuma resposta selecionada.');
            }

            new TMessage('info', 'Auditoria finalizada com sucesso!');

            TScript::create("
                setTimeout(() => Adianti.currentWindow?.close(), 1500);
            ");

        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Abre em modal
     */
    public static function onOpenCurtain($param)
    {
        $win = TWindow::create('CheckList de Auditoria', 0.9, 0.9);
        $win->removePadding();

        $page = new self();
        $page->onStart($param);

        $win->add($page);
        $win->setIsWrapped(true);
        $win->show();
    }
}