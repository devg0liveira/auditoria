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

        $this->form->addHeaderAction('Voltar', new TAction(['HistoricoList', 'onReload']));
        $this->form->addHeaderAction('Salvar', new TAction([$this, 'onSave']));
    }

    public function onEdit(array $param)
    {
        try {
            $doc = $param['doc'] ?? null;
            if (!$doc || trim($doc) === '') {
                throw new Exception('Documento não informado.');
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
                    ISNULL(cn.ZCN_OBS, '') AS ZCN_OBS,
                    ISNULL(cl.ZCL_SCORE, 0) AS SCORE_PERDIDO
                FROM ZCN010 cn
                INNER JOIN ZCJ010 cj 
                    ON cj.ZCJ_ETAPA = cn.ZCN_ETAPA 
                    AND cj.D_E_L_E_T_ <> '*'
                LEFT JOIN ZCL010 cl 
                    ON cl.ZCL_ETAPA = cn.ZCN_ETAPA 
                    AND cl.D_E_L_E_T_ <> '*'
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
                new TMessage('info', 'Nenhuma não conformidade encontrada.');
                AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');
                return;
            }

            $tem_concluido = false;
            foreach ($ncs as $nc) {
                if ($nc['ZCN_STATUS'] === 'C') {
                    $tem_concluido = true;
                    break;
                }
            }

            $this->form->clear();

            $this->form->addContent(["Auditoria: {$doc}<br>Total: " . count($ncs)]);

            if ($tem_concluido) {
                $this->form->addContent(["Itens concluídos presentes."]);
            }

            $hiddenDoc = new THidden('doc');
            $hiddenDoc->setValue($doc);
            $this->form->addFields([$hiddenDoc]);

            $contador = 1;
            foreach ($ncs as $nc) {
                $this->renderNaoConformidade($nc, $contador, $tem_concluido);
                $contador++;
            }

            $total_pendentes = 0;
            $total_concluidos = 0;
            foreach ($ncs as $nc) {
                if ($nc['ZCN_STATUS'] === 'C') {
                    $total_concluidos++;
                } else {
                    $total_pendentes++;
                }
            }

            $this->form->addContent([
                "Resumo:<br>
                 Total: " . count($ncs) . "<br>
                 Em andamento: {$total_pendentes}<br>
                 Concluídos: {$total_concluidos}"
            ]);

            TTransaction::close();
            parent::add($this->form);

        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar: ' . $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    private function renderNaoConformidade($nc, $numero, $readonly = false)
    {
        $etapa = trim($nc['ZCN_ETAPA']);
        $seq   = trim($nc['ZCN_SEQ']);
        $key   = "{$etapa}_{$seq}";
        $status = $nc['ZCN_STATUS'];

        $this->form->addContent(["Item {$numero} - Etapa {$etapa} - " . htmlspecialchars($nc['ZCJ_DESCRI'])]);

        $acao = new TText("acao_{$key}");
        $acao->setSize('100%', 80);
        if (!$readonly) {
            $acao->addValidation('Ação', new TRequiredValidator);
            $acao->addValidation('Ação', new TMinLengthValidator, [10]);
        }
        $acao->setValue($nc['ZCN_ACAO']);
        $acao->setEditable(!$readonly && $status !== 'C');

        $resp = new TEntry("resp_{$key}");
        $resp->setSize('100%');
        if (!$readonly) {
            $resp->addValidation('Responsável', new TRequiredValidator);
            $resp->addValidation('Responsável', new TMinLengthValidator, [3]);
        }
        $resp->setValue($nc['ZCN_RESP']);
        $resp->setEditable(!$readonly && $status !== 'C');

        $prazo = new TDate("prazo_{$key}");
        $prazo->setMask('dd/mm/yyyy');
        $prazo->setSize('100%');
        if (!$readonly) {
            $prazo->addValidation('Prazo', new TRequiredValidator);
        }
        $prazo->setValue($this->formatDate($nc['ZCN_PRAZO']));
        $prazo->setEditable(!$readonly && $status !== 'C');

        $exec = new TDate("exec_{$key}");
        $exec->setMask('dd/mm/yyyy');
        $exec->setSize('100%');
        $exec->setValue($this->formatDate($nc['ZCN_DATA_EXEC']));
        $exec->setEditable(!$readonly && $status !== 'C');

        $status_combo = new TCombo("status_{$key}");
        $status_combo->addItems([
            'A' => 'Em Andamento',
            'C' => 'Concluído'
        ]);
        $status_combo->setSize('100%');
        $status_combo->setValue($status);
        $status_combo->setEditable(!$readonly && $status !== 'C');

        $obs = new TText("obs_{$key}");
        $obs->setSize('100%', 60);
        $obs->setValue($nc['ZCN_OBS']);
        $obs->setEditable(false);

        $this->form->addFields([new TLabel('Ação')], [$acao]);
        $this->form->addFields([new TLabel('Responsável')], [$resp]);
        $this->form->addFields([new TLabel('Prazo')], [$prazo]);
        $this->form->addFields([new TLabel('Data Execução')], [$exec]);
        $this->form->addFields([new TLabel('Status')], [$status_combo]);
        $this->form->addFields([new TLabel('Observações')], [$obs]);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $doc = $param['doc'] ?? null;
            if (!$doc) {
                throw new Exception('Documento não informado.');
            }

            $stmt = $conn->prepare("
                SELECT ZCN_STATUS, ZCN_ETAPA 
                FROM ZCN010 
                WHERE ZCN_DOC = ? 
                  AND ZCN_NAOCO IN ('NC', 'P', 'OP')
                  AND D_E_L_E_T_ <> '*'
            ");
            $stmt->execute([$doc]);
            $ncs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_concluidos = 0;
            foreach ($ncs as $nc) {
                if ($nc['ZCN_STATUS'] == 'C') {
                    $total_concluidos++;
                }
            }

            if ($total_concluidos === count($ncs) && count($ncs) > 0) {
                new TMessage('warning', 'Todos os itens já estão concluídos.');
                TTransaction::close();
                AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');
                return;
            }

            $erros = [];
            $salvos = 0;

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

                    if (!$zcn) continue;

                    if ($zcn->ZCN_STATUS === 'C') {
                        continue;
                    }

                    $acao  = trim($param["acao_{$etapa}_{$seq}"] ?? '');
                    $resp  = trim($param["resp_{$etapa}_{$seq}"] ?? '');
                    $prazo = trim($param["prazo_{$etapa}_{$seq}"] ?? '');

                    if (empty($acao)) {
                        $erros[] = "Etapa {$etapa}: Ação obrigatória.";
                        continue;
                    }

                    if (strlen($acao) < 10) {
                        $erros[] = "Etapa {$etapa}: Ação deve ter mínimo 10 caracteres.";
                        continue;
                    }

                    if (empty($resp)) {
                        $erros[] = "Etapa {$etapa}: Responsável obrigatório.";
                        continue;
                    }

                    if (strlen($resp) < 3) {
                        $erros[] = "Etapa {$etapa}: Responsável deve ter mínimo 3 caracteres.";
                        continue;
                    }

                    if (empty($prazo)) {
                        $erros[] = "Etapa {$etapa}: Prazo obrigatório.";
                        continue;
                    }

                    $zcn->ZCN_ACAO      = $acao;
                    $zcn->ZCN_RESP      = $resp;
                    $zcn->ZCN_PRAZO     = self::toDbDate($prazo);
                    $zcn->ZCN_DATA_EXEC = self::toDbDate(trim($param["exec_{$etapa}_{$seq}"] ?? ''));
                    $zcn->ZCN_STATUS    = $param["status_{$etapa}_{$seq}"] ?? 'A';
                    $zcn->store();

                    $salvos++;
                }
            }

            if (!empty($erros)) {
                TTransaction::rollback();
                new TMessage('error', implode("<br>", $erros));
                return;
            }

            if ($salvos === 0) {
                TTransaction::rollback();
                new TMessage('warning', 'Nenhuma alteração realizada.');
                return;
            }

            TTransaction::close();

            new TMessage('info', "Plano de ação salvo. Itens atualizados: {$salvos}");
            AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');

        } catch (Exception $e) {
            new TMessage('error', 'Erro ao salvar: ' . $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
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
            [$d, $m, $y] = $parts;
            if (checkdate((int) $m, (int) $d, (int) $y)) {
                return sprintf('%04d%02d%02d', $y, $m, $d);
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
