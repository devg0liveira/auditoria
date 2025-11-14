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
use Adianti\Widget\Base\TScript;
use Adianti\Database\TTransaction;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Wrapper\BootstrapFormBuilder;

class IniciativaForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();
        $this->form = new BootstrapFormBuilder('form_iniciativa');
        $this->form->setFormTitle('Plano de Ação - Iniciativas de Melhoria');
        $this->form->style = 'padding:20px; max-width:1000px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1)';
        $action_save = new TAction([$this, 'onSave']);
        $this->form->addAction('Salvar', $action_save, 'fa:save green');
    }

    public function onLoad(array $param)
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
            $this->form->addFields([new TLabel("<h3 style='color:#0066cc; text-align:center'>Auditoria: <b>{$doc}</b></h3>")]);

            $hiddenDoc = new THidden('doc');
            $hiddenDoc->setValue($doc);
            $this->form->addFields([$hiddenDoc]);

            foreach ($ncs as $nc) {
                $etapa = trim($nc['ZCN_ETAPA']);
                $seq   = trim($nc['ZCN_SEQ']);
                $key   = "{$etapa}_{$seq}";

                $this->form->addFields([new TLabel('<hr style="border:1px dashed #ccc; margin:20px 0">')]);
                $this->form->addFields([
                    new TLabel("<b style='font-size:15px'>Etapa {$etapa}</b>: " . htmlspecialchars($nc['ZCJ_DESCRI']), '#003366')
                ]);
                $this->form->addFields([
                    new TLabel("Tipo: <span style='color:red; font-weight:bold'>{$nc['ZCN_NAOCO']}</span>")
                ]);

                $acao   = new TText("acao_{$key}");
                $acao->setSize('100%', 90);

                $resp   = new TEntry("resp_{$key}");
                $resp->setSize('100%');

                $prazo  = new TDate("prazo_{$key}");
                $prazo->setMask('dd/mm/yyyy');

                $exec   = new TDate("exec_{$key}");
                $exec->setMask('dd/mm/yyyy');

                $status = new TCombo("status_{$key}");
                $status->addItems(['A' => 'Em Andamento', 'C' => 'Concluído']);

                $obs    = new TText("obs_{$key}");
                $obs->setSize('100%', 70);

                $acao->setValue($nc['ZCN_ACAO']);
                $resp->setValue($nc['ZCN_RESP']);
                $prazo->setValue($this->formatDate($nc['ZCN_PRAZO']));
                $exec->setValue($this->formatDate($nc['ZCN_DATA_EXEC']));
                $status->setValue($nc['ZCN_STATUS']);
                $obs->setValue($nc['ZCN_OBS']);

                $this->form->addFields([new TLabel('Ação corretiva <span style="color:red">*</span>')], [$acao]);
                $this->form->addFields([new TLabel('Responsável <span style="color:red">*</span>'), new TLabel('Prazo')], [$resp, $prazo]);
                $this->form->addFields([new TLabel('Data Execução'), new TLabel('Status')], [$exec, $status]);
                $this->form->addFields([new TLabel('Observações')], [$obs]);
            }

            TTransaction::close();
            parent::add($this->form);
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar: ' . $e->getMessage());
            TTransaction::rollbackAll();
        }
    }

   public static function onSave(array $param = [])
{
    try {
        if (empty($param)) {
            throw new Exception('Nenhum dado recebido para salvar.');
        }

        $doc = $param['doc'] ?? null;
        if (!$doc || trim($doc) === '') {
            throw new Exception('Documento não informado.');
        }

        TTransaction::open('auditoria');

        foreach ($param as $key => $value) {
            if (strpos($key, 'acao_') === 0) {
                $parts = explode('_', $key);
                $etapa = $parts[1] ?? null;
                $seq   = $parts[2] ?? '001';
                if (!$etapa) continue;

                $zcn = ZCN010::where('ZCN_DOC', '=', $doc)
                             ->where('ZCN_ETAPA', '=', $etapa)
                             ->where('ZCN_SEQ', '=', $seq)
                             ->first();

                if ($zcn) {
                    $zcn->ZCN_ACAO      = $param["acao_{$etapa}_{$seq}"] ?? '';
                    $zcn->ZCN_RESP      = $param["resp_{$etapa}_{$seq}"] ?? '';
                    $zcn->ZCN_PRAZO     = self::toDbDate($param["prazo_{$etapa}_{$seq}"] ?? null);
                    $zcn->ZCN_DATA_EXEC = self::toDbDate($param["exec_{$etapa}_{$seq}"] ?? null);
                    $zcn->ZCN_STATUS    = $param["status_{$etapa}_{$seq}"] ?? 'A';
                    $zcn->ZCN_OBS       = $param["obs_{$etapa}_{$seq}"] ?? '';
                    $zcn->store();
                }
            }
        }

        TTransaction::close();


     new TMessage('info', 'Plano de ação salvo com sucesso!');

        AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');

    } catch (Exception $e) {
        new TMessage('error', 'Erro ao salvar: ' . $e->getMessage());
        TTransaction::rollbackAll();
    }
}


    private static function toDbDate($date)
{
    $date = trim($date ?? '');
    if ($date === '' || $date === null) {
        return null; 
    }

    $parts = explode('/', $date);
    if (count($parts) === 3) {
        [$day, $month, $year] = $parts;
        if (checkdate((int)$month, (int)$day, (int)$year)) {
            return sprintf('%04d%02d%02d', $year, $month, $day);
        }
    }

    return null; 
}

    private function formatDate($date)
    {
        $date = trim($date ?? '');
        if (strlen($date) === 8 && ctype_digit($date)) {
            return substr($date, 6, 2) . '/' . substr($date, 4, 2) . '/' . substr($date, 0, 4);
        }
        return '';
    }

}