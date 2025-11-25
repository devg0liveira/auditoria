<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Database\TTransaction;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Validator\TMinLengthValidator;
use Adianti\Validator\TRequiredValidator;
use Adianti\Wrapper\BootstrapFormBuilder;

class IniciativaForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_iniciativa');
        $this->form->setFormTitle('Plano de Ação - Iniciativas de Melhoria');

        $this->form->style = "
            padding: 20px;
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        ";

        $this->form->addAction('Voltar', new TAction(['HistoricoList', 'onReload']), 'fa:arrow-left');

        $action_save = new TAction([$this, 'onSave']);
        $this->form->addAction('Salvar', $action_save, 'fa:save green');
    }

    public function onEdit(array $param)
    {
        try {
            $doc = $param['doc'] ?? null;

            if (!$doc || trim($doc) === '') {
                throw new Exception('Documento da auditoria não informado.');
            }

            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $sql = "
                SELECT 
                    cn.ZCN_ETAPA,
                    ISNULL(cn.ZCN_SEQ, '001') AS ZCN_SEQ,
                    ISNULL(cj.ZCJ_DESCRI, 'Sem descrição') AS ZCJ_DESCRI,
                    cn.ZCN_NAOCO,
                    ISNULL(cn.ZCN_ACAO, '') AS ZCN_ACAO,
                    ISNULL(cn.ZCN_RESP, '') AS ZCN_RESP,
                    cn.ZCN_PRAZO,
                    cn.ZCN_DATA_EXEC,
                    ISNULL(cn.ZCN_STATUS, 'A') AS ZCN_STATUS,
                    ISNULL(cn.ZCN_OBS, '') AS ZCN_OBS
                FROM ZCN010 cn
                INNER JOIN ZCJ010 cj ON cj.ZCJ_ETAPA = cn.ZCN_ETAPA AND cj.D_E_L_E_T_ <> '*'
                WHERE cn.ZCN_DOC = :doc 
                  AND cn.ZCN_NAOCO IN ('NC', 'P', 'OP')
                  AND cn.D_E_L_E_T_ <> '*'
                ORDER BY cn.ZCN_ETAPA, cn.ZCN_SEQ
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':doc' => $doc]);
            $ncs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($ncs)) {
                TTransaction::close();
                new TMessage('info', 'Nenhuma não conformidade encontrada para plano de ação.');
                AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');
                return;
            }

            $this->form->clear();

            $this->form->addContent([
                "<h3 style='color:#0066cc; text-align:center; margin-bottom:10px'>
                    Auditoria: <b>{$doc}</b>
                </h3>"
            ]);

            $hiddenDoc = new THidden('doc');
            $hiddenDoc->setValue($doc);
            $this->form->addFields([$hiddenDoc]);

            foreach ($ncs as $nc) {

                $etapa = trim($nc['ZCN_ETAPA']);
                $seq   = trim($nc['ZCN_SEQ']);
                $key   = "{$etapa}_{$seq}";

                $this->form->addContent(["<hr style='border:0; border-top:1px dashed #ccc; margin:20px 0'>"]);

                $this->form->addContent([
                    "<div style='font-size:15px; font-weight:bold; color:#003366; margin-bottom:5px'>
                        Etapa {$etapa}: <span style='font-weight:normal'>{$nc['ZCJ_DESCRI']}</span>
                    </div>"
                ]);

                $this->form->addContent([
                    "<div style='margin-bottom:10px'>
                        Tipo: <span style='color:red; font-weight:bold'>{$nc['ZCN_NAOCO']}</span>
                    </div>"
                ]);


                $acao = new TText("acao_{$key}");
                $acao->setSize('100%', 90);
                $acao->addValidation('Ação corretiva', new TRequiredValidator);
                $acao->addValidation('Ação corretiva', new TMinLengthValidator, [5]);
                $acao->setValue($nc['ZCN_ACAO']);

                $this->form->addFields(
                    [new TLabel('Ação corretiva <span style="color:red">*</span>')],
                    [$acao]
                );

                $resp = new TEntry("resp_{$key}");
                $resp->setSize('100%');
                $resp->addValidation('Responsável', new TRequiredValidator);
                $resp->addValidation('Responsável', new TMinLengthValidator, [3]);
                $resp->setValue($nc['ZCN_RESP']);

                $prazo = new TDate("prazo_{$key}");
                $prazo->setMask('dd/mm/yyyy');
                $prazo->addValidation('Prazo', new TRequiredValidator);
                $prazo->setValue($this->formatDate($nc['ZCN_PRAZO']));

                $this->form->addFields(
                    [new TLabel('Responsável <span style="color:red">*</span>')],
                    [$resp],
                    [new TLabel('Prazo <span style="color:red">*</span>')],
                    [$prazo]
                );

                $exec = new TDate("exec_{$key}");
                $exec->setMask('dd/mm/yyyy');
                $exec->setValue($this->formatDate($nc['ZCN_DATA_EXEC']));

                $status = new TCombo("status_{$key}");
                $status->addItems(['A' => 'Em Andamento', 'C' => 'Concluído']);
                $status->setSize('100%');
                $status->setValue($nc['ZCN_STATUS']);

                $this->form->addFields(
                    [new TLabel('Data Execução')],
                    [$exec],
                    [new TLabel('Status')],
                    [$status]
                );

                $obs = new TText("obs_{$key}");
                $obs->setSize('100%', 70);
                $obs->setValue($nc['ZCN_OBS']);
                $obs->setEditable(false);

                $this->form->addFields([new TLabel('Observações')], [$obs]);
            }

            TTransaction::close();
            parent::add($this->form);

        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar: ' . $e->getMessage());
            TTransaction::rollbackAll();
        }
    }

    
    public function onSave($param)
    {
       
    }

    private static function toDbDate($date)
    {
        $date = trim($date ?? '');
        if ($date === '' || $date === null) return null;

        $parts = explode('/', $date);
        if (count($parts) === 3) {
            [$d,$m,$y] = $parts;
            if (checkdate($m,$d,$y)) {
                return sprintf('%04d%02d%02d',$y,$m,$d);
            }
        }
        return null;
    }

    private function formatDate($date)
    {
        $date = trim($date ?? '');
        if (strlen($date)==8 && ctype_digit($date)) {
            return substr($date,6,2).'/'.substr($date,4,2).'/'.substr($date,0,4);
        }
        return '';
    }
}
