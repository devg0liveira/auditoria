<?php
use Adianti\Control\TPage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;

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

        // Adicione após o campo $desc
$score = new TCombo('ZCL_SCORE');
$score->addItems(['1' => '1', '2' => '2']);
$score->setDefaultOption('Selecione o score');
$score->addValidation('Score', new TRequiredValidator);

$this->form->addFields([new TLabel('Score <span style="color:red">*</span>:', '#ff0000')], [$score]);

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
        // Validações
        if (empty($param['ZCJ_DESCRI'])) {
            throw new Exception('O campo Pergunta é obrigatório.');
        }
        if (empty($param['ZCL_SCORE']) || !in_array($param['ZCL_SCORE'], ['1', '2'])) {
            throw new Exception('Selecione um score válido (1 ou 2).');
        }

        TTransaction::open('auditoria');

        // === SALVAR PERGUNTA EM ZCJ010 ===
        $obj = new ZCJ010;
        $obj->ZCJ_DESCRI = $param['ZCJ_DESCRI'];

        // Calcula próxima etapa
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

        $etapa_gerada = $obj->ZCJ_ETAPA;

        // === SALVAR SCORE EM ZCL_SCORE ===
        $score_obj = new ZCL010; // Assumindo que existe a classe Active Record ZCL_SCORE
        $score_obj->ZCL_ETAPA = $etapa_gerada;
        $score_obj->ZCL_SCORE = $param['ZCL_SCORE'];
        $score_obj->store();

        TTransaction::close();

        new TMessage('info', "Pergunta cadastrada com sucesso! Etapa: {$etapa_gerada}, Score: {$param['ZCL_SCORE']}");
    } catch (Exception $e) {
        TTransaction::rollback();
        new TMessage('error', $e->getMessage());
    }
}
}