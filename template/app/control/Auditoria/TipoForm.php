<?php

use Adianti\Control\TPage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Dialog\TMessage;
class TipoForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_tipo');
        $this->form->setFormTitle('Cadastro de Tipo de Auditoria');

        $tipo  = new TEntry('ZCK_TIPO');
        $tipo->setEditable(false);
        $desc  = new TEntry('ZCK_DESCRI');

        $this->form->addFields([new TLabel('Código:')], [$tipo]);
        $this->form->addFields([new TLabel('Insperção <span style="color:red">*</span>:')], [$desc]);

        $btn = new TButton('save');
        $btn->setAction(new \Adianti\Control\TAction([$this, 'onSave']), 'Salvar');
        $btn->setImage('fa:save green');
        $this->form->addFields([], [$btn]);

        parent::add($this->form);
    }

    public static function onSave($param)
    {
        try {
            if (empty($param['ZCK_DESCRI'])) {
                throw new Exception('Insperção é obrigatória.');
            }

            TTransaction::open('auditoria');

            $obj = new ZCK010;
            $obj->fromArray($param);

            if (!$obj->ZCK_TIPO) {
                $conn = TTransaction::get();
                $result = $conn->query("
        SELECT MAX(CAST(ZCK_TIPO AS INT)) AS max_tipo 
        FROM ZCK010 
        WHERE ISNUMERIC(ZCK_TIPO) = 1
    ");
                $row = $result->fetch(PDO::FETCH_ASSOC);
                $max = $row['max_tipo'] ?? 0;
                $obj->ZCK_TIPO = str_pad($max + 1, 3, '0', STR_PAD_LEFT);
            }

            $obj->ZCK_DATA   = date('Ymd');
            $obj->ZCK_HORA   = date('Hi');
            $obj->ZCK_USUARIO = TSession::getValue('username');
            $obj->store();

            TTransaction::close();
            new TMessage('info', 'Insperção cadastrada com sucesso!');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }
}
