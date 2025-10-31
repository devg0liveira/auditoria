<?php

use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Wrapper\BootstrapFormBuilder;

class CheckListForm extends TPage
{
    protected $form;

    /**
     * Construtor
     */
    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setFormTitle('CheckList de Auditoria');

        // Adiciona campos e ações conforme necessário
        // ... implementação específica do formulário

        parent::add($this->form);
    }

    /**
     * Inicia uma nova auditoria
     */
    public function onStart($param)
    {
        try {
            $filial = $param['filial'] ?? null;
            $tipo = $param['tipo'] ?? null;

            if (!$filial || !$tipo) {
                throw new Exception('Filial e tipo de auditoria são obrigatórios.');
            }

            // Carrega os dados e prepara o formulário
            TSession::setValue('auditoria_filial', $filial);
            TSession::setValue('auditoria_tipo', $tipo);

            // ... lógica adicional de inicialização

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Edita uma auditoria existente
     */
    public function onEdit($param)
    {
        try {
            $key = $param['key'] ?? TSession::getValue('auditoria_key');
            
            if (!$key) {
                throw new Exception('ID da auditoria não informado.');
            }

            TTransaction::open('auditoria');
            $auditoria = ZCM010::find($key);

            if (!$auditoria || $auditoria->D_E_L_E_T_ === '*') {
                throw new Exception('Auditoria não encontrada.');
            }

            // Carrega dados para o formulário
            $this->form->setData($auditoria);

            TTransaction::close();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Abre o CheckListForm em janela modal (etapa 3 do fluxo)
     */
    public static function onOpenCurtain($param)
    {
        try {
            $page = TWindow::create('CheckList de Auditoria', 0.8, 0.8);
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