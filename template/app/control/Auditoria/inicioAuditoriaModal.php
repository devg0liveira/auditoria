<?php
// app/control/InicioAuditoriaModal.php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;

class InicioAuditoriaModal extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_inicio');
        $this->form->setFormTitle('Iniciar Nova Auditoria');

        $filial = new TEntry('ZCM_FILIAL');
        $tipo   = new TCombo('ZCM_TIPO');

        $filial->setValue(TSession::getValue('filial') ?? '');
        $filial->setSize('100%');

        TTransaction::open('auditoria');
        $tipos = ZCK010::where('D_E_L_E_T_', '<>', '*')->orderBy('ZCK_DESCRI')->load();
        $items = ['' => 'Selecione um tipo'];
        foreach ($tipos as $t) {
            $items[$t->ZCK_TIPO] = $t->ZCK_DESCRI;
        }
        $tipo->addItems($items);
        TTransaction::close();

        $this->form->addFields([new TLabel('Filial *')], [$filial]);
        $this->form->addFields([new TLabel('Tipo de Auditoria *')], [$tipo]);

        $this->form->addAction('Avançar', new TAction([$this, 'onAvancar']), 'fa:arrow-right green');

        parent::add($this->form);
    }

    public function onAvancar($param)
    {
        $data = $this->form->getData();
        if (empty($data->ZCM_FILIAL) || empty($data->ZCM_TIPO)) {
            new TMessage('error', 'Preencha filial e tipo!');
            return;
        }

        TSession::setValue('auditoria_filial', $data->ZCM_FILIAL);
        TSession::setValue('auditoria_tipo', $data->ZCM_TIPO);

        AdiantiCoreApplication::loadPage('CheckListForm', 'onStart');
    }

    public function onClear() { }
}