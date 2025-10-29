<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Wrapper\BootstrapFormBuilder;

class checkList extends TPage
{
    protected $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder;
        $this->form->setFormTitle('Auditoria');
        $this->form->generateAria();

        $combo1 = new TCombo('combo_1');
        $combo2 = new TCombo('combo_2');
        $combo3 = new TCombo('combo_3');
        $combo4 = new TCombo('combo_4');
        $combo5 = new TCombo('combo_5');
        $combo6 = new TCombo('combo_6');
        $combo7 = new TCombo('combo_7');

        $items = [
            'C'  => 'Conforme',
            'NC' => 'Não Conforme',
            'OP' => 'Oportunidade de melhoria',
            'P'  => 'Parcialmente',
            'NV' => 'Não visto'
        ];

        // Aplica os itens e valor padrão em todos
        foreach ([$combo1, $combo2, $combo3, $combo4, $combo5, $combo6, $combo7] as $c) {
            $c->addItems($items);
            $c->setValue('C');   // valor padrão
        }

        // Define colunas (opcional)
        $this->form->setColumnClasses(2, ['col-sm-7', 'col-sm-4']);

        $this->form->addFields(
            [new TLabel('Encontra-se organizado de forma padronizada, etiquetados com descrição, referência e código Totvs ?')],
            [$combo1]
        );
        $this->form->addFields(
            [new TLabel('As peças usadas sem condições de uso e as ferramentas estavam separadas das novas ?')],
            [$combo2]
        );
        $this->form->addFields(
            [new TLabel('Controle de saída de produto ?')],
            [$combo3]
        );
        $this->form->addFields(
            [new TLabel('Controle de acesso ?')],
            [$combo4]
        );
        $this->form->addFields(
            [new TLabel('O almoxarifado permanece fechado na ausência do responsável ?')],
            [$combo5]
        );
        $this->form->addFields(
            [new TLabel('As requisições de solicitação ao armazem estão sendo registradas diariamente ?')],
            [$combo6]
        );
        $this->form->addFields(
            [new TLabel('As requisições estão organizadas e arquivadas com seu relatório de fechamento diário ?')],
            [$combo7]
        );

        $this->form->addAction('Send', new TAction(array($this, 'onSend')), 'far:check-circle green');
        $this->form->addAction('Change options', new TAction(array($this, 'onChangeOptions')), 'fas:sync orange');


        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $vbox->add($this->form);

        parent::add($vbox);
    }
    public static function onChangeOptions($param)
    {
        $items = [
            'C' => 'Conforme',
            'NC' => 'Não Conforme',
            'OP' => 'Oportunidade de melhoria',
            'P' => 'Parcialmente' //'C' =>'Conforme'
        ];

        TCombo::reload('form_selectors', 'combo', array_merge(['' => ''], $items));
    }
    public function onSend($param)
    {
        $data = $this->form->getData();

        $message = 'Combo : ' . $data->combo . '<br>';
        new TMessage('info', $message);
    }
}
