<?php
use Adianti\Control\TPage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

class PerguntaForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_pergunta');
        $this->form->setFormTitle('Adicionar Pergunta');

        // Campo apenas para exibição da etapa (não editável)
        $etapa = new TEntry('ZCJ_ETAPA');
        $etapa->setEditable(false);

        // Campo principal: descrição da pergunta
        $desc = new TEntry('ZCJ_DESCRI');
        $desc->setSize('100%');

        $this->form->addFields([new TLabel('Etapa:')], [$etapa]);
        $this->form->addFields([new TLabel('Pergunta <span style="color:red">*</span>:', '#ff0000')], [$desc]);

        // Botão salvar
        $btn = new TButton('save');
        $btn->setAction(new \Adianti\Control\TAction([$this, 'onSave']), 'Salvar');
        $btn->setImage('fa:save green');
        $this->form->addFields([], [$btn]);

        parent::add($this->form);
    }

    public function onEdit($param)
    {
        if (isset($param['key'])) {
            TTransaction::open('auditoria');
            $obj = ZCJ010::find($param['key']);
            TTransaction::close();
            if ($obj) {
                $this->form->setData($obj);
            }
        }
    }

    public static function onSave($param)
    {
        try {
            if (empty($param['ZCJ_DESCRI'])) {
                throw new Exception('O campo Pergunta é obrigatório.');
            }

            TTransaction::open('auditoria');

            $obj = new ZCJ010;
            $obj->ZCJ_DESCRI = $param['ZCJ_DESCRI'];

            // Calcula próxima etapa (automática)
            $conn = TTransaction::get();
            $stmt = $conn->query("
                SELECT MAX(CAST(ZCJ_ETAPA AS INT)) AS max_etapa
                FROM ZCJ010
                WHERE ISNUMERIC(ZCJ_ETAPA) = 1
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $max = $row['max_etapa'] ?? 0;

            $obj->ZCJ_ETAPA = str_pad($max + 1, 5, '0', STR_PAD_LEFT);
            $obj->store();

            TTransaction::close();

            new TMessage('info', 'Pergunta cadastrada com sucesso!');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }
}
