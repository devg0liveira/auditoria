<?php
use Adianti\Control\TPage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Wrapper\TDBCombo;

class PerguntaForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_pergunta');
        $this->form->setFormTitle('Adicionar Pergunta');

        $etapa = new TEntry('ZCJ_ETAPA');
        $etapa->setEditable(false);

        $tipo = new TDBCombo('ZCL_TIPO', 'auditoria', 'ZCK010', 'ZCK_TIPO', 'ZCK_DESCRI', 'ZCK_TIPO');
        $tipo->setSize('100%');
        $tipo->setDefaultOption('Selecione o tipo de insperção...');
        $tipo->addValidation('Tipo', new TRequiredValidator);

        $desc = new TEntry('ZCJ_DESCRI');
        $desc->setSize('100%');
        $desc->addValidation('Pergunta', new TRequiredValidator);

        $score = new TCombo('ZCL_SCORE');
        $score->addItems(['1' => '1', '2' => '2']);
        $score->setDefaultOption('Selecione o score');
        $score->addValidation('Score', new TRequiredValidator);

        $this->form->addFields([new TLabel('Etapa:')], [$etapa]);
        $this->form->addFields([new TLabel('Tipo de Insperção')], [$tipo]);
        $this->form->addFields([new TLabel('Pergunta')], [$desc]);
        $this->form->addFields([new TLabel('Score')], [$score]);

        $btn = new TButton('save');
        $btn->setAction(new \Adianti\Control\TAction([$this, 'onSave']), 'Salvar');
        $btn->setImage('fa:save green');
        $this->form->addFields([], [$btn]);

        parent::add($this->form);
    }

    public static function onSave($param)
    {
        try {
            if (empty($param['ZCJ_DESCRI'])) {
                throw new Exception('O campo Pergunta é obrigatório.');
            }
            if (empty($param['ZCL_TIPO'])) {
                throw new Exception('Selecione um tipo de insperção.');
            }
            if (empty($param['ZCL_SCORE']) || !in_array($param['ZCL_SCORE'], ['1', '2'])) {
                throw new Exception('Selecione um score válido (1 ou 2).');
            }

            TTransaction::open('auditoria');

            $conn = TTransaction::get();
            $stmt = $conn->query("
                SELECT MAX(CAST(ZCJ_ETAPA AS INT)) AS max_etapa
                FROM ZCJ010
                WHERE ISNUMERIC(ZCJ_ETAPA) = 1
                  AND D_E_L_E_T_ <> '*'
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $max = $row['max_etapa'] ?? 0;
            $etapa_gerada = str_pad($max + 1, 5, '0', STR_PAD_LEFT);

            $obj = new ZCJ010;
            $obj->ZCJ_ETAPA = $etapa_gerada;
            $obj->ZCJ_DESCRI = $param['ZCJ_DESCRI'];
            $obj->ZCJ_FILIAL = '01'; 
            $obj->ZCJ_DATA = date('Ymd');
            $obj->ZCJ_HORA = date('Hi');
             $obj->ZCJ_USUARIO = TSession::getValue('username');
            $obj->D_E_L_E_T_ = ' '; 
            $obj->R_E_C_D_E_L_ = 0;
            $obj->store();

            $zcl = new ZCL010;
            $zcl->ZCL_ETAPA = $etapa_gerada;
            $zcl->ZCL_TIPO = $param['ZCL_TIPO'];
            $zcl->ZCL_SCORE = $param['ZCL_SCORE'];
            $zcl->ZCL_FILIAL = '01'; 
            
            $stmt_seq = $conn->query("
                SELECT MAX(CAST(ZCL_SEQ AS INT)) AS max_seq
                FROM ZCL010
                WHERE ZCL_TIPO = '{$param['ZCL_TIPO']}'
                  AND ISNUMERIC(ZCL_SEQ) = 1
                  AND D_E_L_E_T_ <> '*'
            ");
            $row_seq = $stmt_seq->fetch(PDO::FETCH_ASSOC);
            $max_seq = $row_seq['max_seq'] ?? 0;
            $zcl->ZCL_SEQ = str_pad($max_seq + 1, 3, '0', STR_PAD_LEFT);
            
            $zcl->ZCL_DATA = date('Ymd');
            $zcl->ZCL_HORA = date('Hi');
            $zcl->ZCL_USUGIR = TSession::getvalue ('username');
            $zcl->D_E_L_E_T_ = ' ';
            $zcl->store();

            TTransaction::close();

            new TMessage('info', "Pergunta cadastrada com sucesso!<br>Etapa: {$etapa_gerada}<br>Tipo: {$param['ZCL_TIPO']}<br>Score: {$param['ZCL_SCORE']}");
            
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }
    }
}