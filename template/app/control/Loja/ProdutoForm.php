<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TText;
use Adianti\Wrapper\BootstrapFormBuilder;

class ProdutoForm extends TPage
{
    protected $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_produto');
        $this->form->setFormTitle('Cadastro de Produto');

        $nome = new TEntry('nome');
        $nome->addValidation('Nome', new TRequiredValidator());

        $descricao = new TText('descricao');

        $preco = new TEntry('preco');
        $preco->addValidation('Preço', new TRequiredValidator());

        $estoque = new TEntry('estoque');
        $estoque->addValidation('Estoque', new TRequiredValidator());

        $categoria = new TEntry('categoria');

        $ativo     = new TCombo('ativo');
        $ativo->addItems(['Y' => 'Sim', 'N' => 'Não']);

        $this->form->addFields([new TLabel('Nome')], [$nome]);
        $this->form->addFields([new TLabel('Descrição')], [$descricao]);
        $this->form->addFields([new TLabel('Preço')], [$preco]);
        $this->form->addFields([new TLabel('Estoque')], [$estoque]);
        $this->form->addFields([new TLabel('Categoria')], [$categoria]);
        $this->form->addFields([new TLabel('Ativo')], [$ativo]);

        $this->form->addAction(
            'Salvar',
            new TAction([$this, 'onSave']),
            'fa:save green'
        );

        $this->form->addAction(
            'Voltar',
            new TAction(['ProdutoList', 'onReload']),
            'fa:arrow-circle-o-left blue'
        );

        parent::add($this->form);
    }

    public function onSave()
    {
        try {
            TTransaction::open('Loja');

            $this->form->validate();
            $data = $this->form->getData();

            $produto = new Produto;
            $produto->fromArray((array) $data);
            $produto->store();

            TTransaction::close();

            new TMessage(
                'info',
                'Produto salvo com sucesso',
                new TAction(['ProdutoList', 'onReload'])
            );
        }
        catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onEdit($param)
    {
        try {
            if (!empty($param['id'])) {
                TTransaction::open('Loja');

                $produto = Produto::find($param['id']);
                if ($produto) {
                    $this->form->setData($produto);
                }

                TTransaction::close();
            }
            else {
                $this->form->clear();
            }
        }
        catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }
}
