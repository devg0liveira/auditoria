<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TText;
use Adianti\Wrapper\BootstrapFormBuilder;

class livroForm extends TPage
{
    protected $form;
    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_livro');
        $this->form->setFormTitle('Cadastro de Livro');

        $id = new TEntry('id');
        $titulo = new TEntry('titulo');
        $autor = new TEntry('autor');
        $isbn = new TEntry('isbn');
        $sinopse = new TText('sinopse');
        $capa_url = new TEntry('capa_url');
        $tags = new TEntry('tags');
        $quantidade = new TEntry('quantidade');

        $id->setEditable(FALSE);

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Título')], [$titulo]);
        $this->form->addFields([new TLabel('Autor')], [$autor]);
        $this->form->addFields([new TLabel('ISBN')], [$isbn]);
        $this->form->addFields([new TLabel('Quantidade')],[$quantidade]);
        $this->form->addFields([new TLabel('Sinopse')],[$sinopse]);
        $this->form->addFields([new TLabel('Capa (URL)')],[$capa_url]);
        $this->form->addFields([new TLabel('Tags')],[$tags]);

        $titulo->addValidation('Título', new TRequiredValidator());
        $autor->addValidation('Autor', new TRequiredValidator());
        $quantidade->addValidation('Quantidade', new TRequiredValidator());

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Voltar', new TAction(['livroList', 'onReload']), 'fa:arrow-left');
        parent::add($this->form);
    }
    public function onSave()
    {
        try {
            $this->form->validate();
            $data = $this->form->getData();

            TTransaction::open('biblioteca');
            $livro = new livro($data->id);
            $livro->titulo = $data->titulo;
            $livro->autor = $data->autor;
            $livro->isbn = $data->isbn;
            $livro->sinopse = $data->sinopse;
            $livro->capa_url = $data->capa_url;
            $livro->tags = $data->tags;
            $livro->quantidade = $data->quantidade;

            if (empty($data->id)) {
                $livro->disponivel = $data->quantidade;
            }

            $livro->store();
            TTransaction::close();
            new TMessage('info', 'Livro salvo com sucesso!');
            AdiantiCoreApplication::loadPage('livroList', 'onReload');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onEdit($param)
    {
        if (isset($param['key'])) {
            TTransaction::open('biblioteca');

            $livro = new livro($param['key']);
            $this->form->setData($livro);

            TTransaction::close();
        }
    }
}
