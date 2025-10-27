<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;

class Etapa1Form extends TPage
{
    protected $form;

    public function __construct()
    {
        parent::__construct();

        // ✅ Corrigido para usar BootstrapFormBuilder
        $this->form = new BootstrapFormBuilder('form_etapa1');
        $this->form->setFormTitle('Editar Registro');

        $id     = new TEntry('id');
        $filial = new TEntry('filial');
        $tipo   = new TEntry('tipo');
        $data   = new TEntry('data_atualizacao');

        $id->setEditable(false);

        // ✅ Agora sim podemos usar addFields()
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Filial')], [$filial]);
        $this->form->addFields([new TLabel('Tipo')], [$tipo]);
        $this->form->addFields([new TLabel('Data Atualização')], [$data]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
$this->form->addAction('Voltar', new TAction(['Datagrid', 'onReload']), 'fa:arrow-left blue');


        $vbox = new TVBox;
        $vbox->add($this->form);

        parent::add($vbox);
    }

    public function onEdit($param)
    {
        try {
            TTransaction::open('banco');
            $obj = new Historico($param['id']);
            $this->form->setData($obj);
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('banco');
            $obj = $this->form->getData('Historico');
            $obj->store();
            TTransaction::close();

            new TMessage('info', 'Registro salvo com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
