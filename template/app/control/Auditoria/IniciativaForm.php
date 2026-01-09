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
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Database\TTransaction;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Registry\TSession;
use Adianti\Validator\TMinLengthValidator;
use Adianti\Validator\TRequiredValidator;
use Adianti\Wrapper\BootstrapFormBuilder;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class IniciativaForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_iniciativa');
        $this->form->setFormTitle('Plano de Ação – Iniciativas de Melhoria');

        $this->form->addHeaderAction('Voltar', new TAction(['HistoricoList', 'onReload']), 'fa:arrow-left red');
        /*$this->form->addHeaderActionLink(
            '<i style="margin-right: 5px;"></i> Exportar XLS',
            new TAction(['IniciativaForm', 'onExportXLS'])
            )->class = 'btn btn-success btn-sm';
            */
        $this->form->addHeaderAction('Salvar alterações', new TAction([$this, 'onConfirmSave']), 'fa:save green');
    }

    public function onEdit(array $param)
    {
        try {
            $doc = $param['doc'] ?? null;

            if (!$doc || trim($doc) === '') {
                throw new Exception('Auditoria não identificada.');
            }

            TSession::setValue('hist_doc', $doc);

            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $stmt = $conn->prepare("
                SELECT 
                    cn.ZCN_ETAPA,
                    ISNULL(cn.ZCN_SEQ, '001') AS ZCN_SEQ,
                    ISNULL(cj.ZCJ_DESCRI, 'Sem descrição') AS ZCJ_DESCRI,
                    ISNULL(cn.ZCN_ACAO, '') AS ZCN_ACAO,
                    ISNULL(cn.ZCN_RESP, '') AS ZCN_RESP,
                    cn.ZCN_PRAZO,
                    cn.ZCN_DATA_EXEC,
                    ISNULL(cn.ZCN_STATUS, 'A') AS ZCN_STATUS,
                    ISNULL(cn.ZCN_OBS, '') AS ZCN_OBS
                FROM ZCN010 cn
                INNER JOIN ZCJ010 cj 
                    ON cj.ZCJ_ETAPA = cn.ZCN_ETAPA 
                    AND cj.D_E_L_E_T_ <> '*'
                WHERE cn.ZCN_DOC = :doc 
                  AND cn.ZCN_NAOCO IN ('NC')
                  AND cn.D_E_L_E_T_ <> '*'
                ORDER BY cn.ZCN_ETAPA, cn.ZCN_SEQ
            ");

            $stmt->execute([':doc' => $doc]);
            $ncs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($ncs)) {
                TTransaction::close();
                new TMessage('info', 'Nenhum item pendente encontrado.');
                AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');
                return;
            }

            $this->form->clear();

            $total = count($ncs);
            $concluidos = count(array_filter($ncs, fn($n) => $n['ZCN_STATUS'] === 'C'));
            $pendentes = $total - $concluidos;

            $this->form->addContent([
                "<b>Auditoria:</b> {$doc}<br>
                 <span class='badge badge-secondary'>Total: {$total}</span>
                 <span class='badge badge-warning'>Aguardando resposta: {$pendentes}</span>
                 <span class='badge badge-success'>Concluídos: {$concluidos}</span>
                 <hr>"
            ]);

            $hiddenDoc = new THidden('doc');
            $hiddenDoc->setValue($doc);
            $this->form->addFields([$hiddenDoc]);

            $contador = 1;
            foreach ($ncs as $nc) {
                $this->renderNaoConformidade($nc, $contador);
                $contador++;
            }

            TTransaction::close();
            parent::add($this->form);

        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', 'Não foi possível carregar o plano de ação.');
        }
    }

    private function renderNaoConformidade($nc, $numero)
    {
        $etapa  = trim($nc['ZCN_ETAPA']);
        $seq    = trim($nc['ZCN_SEQ']);
        $key    = "{$etapa}_{$seq}";
        $status = $nc['ZCN_STATUS'];
        $readonly = ($status === 'C');

        $badge = $status === 'C'
            ? "<span class='badge badge-success'>Concluído</span>"
            : "<span class='badge badge-warning'>Aguardando resposta</span>";

        $this->form->addContent([
            "<details open>
                <summary>
                    <b>Item {$numero} – Etapa {$etapa}</b> {$badge}<br>
                    {$nc['ZCJ_DESCRI']}
                </summary><br>"
        ]);

        $acao = new TText("acao_{$key}");
        $acao->setSize('100%', 80);
        $acao->setValue($nc['ZCN_ACAO']);
        $acao->setEditable(!$readonly);
        if (!$readonly) {
            $acao->addValidation('Ação', new TRequiredValidator);
            $acao->addValidation('Ação', new TMinLengthValidator, [1]);
        }

        $resp = new TEntry("resp_{$key}");
        $resp->setSize('100%');
        $resp->setValue($nc['ZCN_RESP']);
        $resp->setEditable(!$readonly);
        if (!$readonly) {
            $resp->addValidation('Responsável', new TRequiredValidator);
            $resp->addValidation('Responsável', new TMinLengthValidator, [1]);
        }

        $prazo = new TDate("prazo_{$key}");
        $prazo->setMask('dd/mm/yyyy');
        $prazo->setSize('100%');
        $prazo->setValue(self::formatDate($nc['ZCN_PRAZO']));
        $prazo->setEditable(!$readonly);
        if (!$readonly) {
            $prazo->addValidation('Prazo', new TRequiredValidator);
        }

        $exec = new TDate("exec_{$key}");
        $exec->setMask('dd/mm/yyyy');
        $exec->setSize('100%');
        $exec->setValue(self::formatDate($nc['ZCN_DATA_EXEC']));
        $exec->setEditable(!$readonly);

        $status_combo = new TCombo("status_{$key}");
        $status_combo->addItems([
            'A' => 'Aguardando resposta',
            'C' => 'Concluído'
        ]);
        $status_combo->setSize('100%');
        $status_combo->setValue($status);
        $status_combo->setEditable(!$readonly);

        $obs = new TText("obs_{$key}");
        $obs->setSize('100%', 60);
        $obs->setValue($nc['ZCN_OBS']);
        $obs->setEditable(false);

        $this->form->addFields([new TLabel('Ação *')], [$acao]);
        $this->form->addFields([new TLabel('Responsável *')], [$resp]);
        $this->form->addFields([new TLabel('Prazo *')], [$prazo]);
        $this->form->addFields([new TLabel('Data de execução')], [$exec]);
        $this->form->addFields([new TLabel('Status *')], [$status_combo]);
        $this->form->addFields([new TLabel('Observações')], [$obs]);

        $this->form->addContent(["</details><hr>"]);
    }

    public function onConfirmSave($param)
    {
        $actionYes = new TAction([$this, 'onSave']);
        $actionYes->setParameters($param);

        new TQuestion('Deseja salvar as alterações do plano de ação?', $actionYes);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $doc = $param['doc'] ?? null;

            if (!$doc) {
                throw new Exception('Auditoria não identificada.');
            }

            $erros = [];
            $salvos = 0;

            foreach ($param as $key => $value) {
                if (strpos($key, 'acao_') === 0) {
                    [, $etapa, $seq] = array_pad(explode('_', $key), 3, '001');

                    $zcn = ZCN010::where('ZCN_DOC', '=', $doc)
                        ->where('ZCN_ETAPA', '=', $etapa)
                        ->where('ZCN_SEQ', '=', $seq)
                        ->first();

                    if (!$zcn || $zcn->ZCN_STATUS === 'C') {
                        continue;
                    }

                    $acao  = trim($param["acao_{$etapa}_{$seq}"] ?? '');
                    $resp  = trim($param["resp_{$etapa}_{$seq}"] ?? '');
                    $prazo = trim($param["prazo_{$etapa}_{$seq}"] ?? '');

                    if ($acao === '' || strlen($acao) < 1) {
                        $erros[] = "Item {$etapa}: Ação obrigatória (mín. 1 caractere).";
                        continue;
                    }

                    if ($resp === '' || strlen($resp) < 3) {
                        $erros[] = "Item {$etapa}: Responsável obrigatório.";
                        continue;
                    }

                    if ($prazo === '') {
                        $erros[] = "Item {$etapa}: Prazo obrigatório.";
                        continue;
                    }

                    $zcn->ZCN_ACAO      = $acao;
                    $zcn->ZCN_RESP      = $resp;
                    $zcn->ZCN_PRAZO     = self::toDbDate($prazo);
                    $zcn->ZCN_DATA_EXEC = self::toDbDate($param["exec_{$etapa}_{$seq}"] ?? '');
                    $zcn->ZCN_STATUS    = $param["status_{$etapa}_{$seq}"] ?? 'A';
                    $zcn->store();

                    $salvos++;
                }
            }

            if (!empty($erros)) {
                TTransaction::rollback();
                new TMessage('error', implode('<br>', $erros));
                return;
            }

            if ($salvos === 0) {
                TTransaction::rollback();
                new TMessage('warning', 'Nenhuma alteração foi realizada.');
                return;
            }

            TTransaction::close();

            new TMessage('info', "Plano de ação salvo com sucesso. Itens atualizados: {$salvos}");
            AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');

        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', 'Erro ao salvar o plano de ação.');
        }
    }

    private static function toDbDate($date)
    {
        $date = trim($date ?? '');
        if ($date === '') {
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

    private static function formatDate($date)
    {
        $date = trim($date ?? '');
        if (strlen($date) === 8 && ctype_digit($date)) {
            return substr($date, 6, 2) . '/' . substr($date, 4, 2) . '/' . substr($date, 0, 4);
        }
        return '';
    }
/*
    public static function onExportXLS($param)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $doc = TSession::getValue('hist_doc');

            if (!$doc) {
                throw new Exception('Auditoria não identificada.');
            }

            $stmt = $conn->prepare("
                SELECT 
                    cn.ZCN_ETAPA,
                    cn.ZCN_SEQ,
                    cj.ZCJ_DESCRI,
                    cn.ZCN_ACAO,
                    cn.ZCN_RESP,
                    cn.ZCN_PRAZO,
                    cn.ZCN_DATA_EXEC,
                    cn.ZCN_STATUS,
                    cn.ZCN_OBS
                FROM ZCN010 cn
                INNER JOIN ZCJ010 cj 
                    ON cj.ZCJ_ETAPA = cn.ZCN_ETAPA
                   AND cj.D_E_L_E_T_ <> '*'
                WHERE cn.ZCN_DOC = :doc
                  AND cn.ZCN_NAOCO = 'NC'
                  AND cn.D_E_L_E_T_ <> '*'
                ORDER BY cn.ZCN_ETAPA, cn.ZCN_SEQ
            ");
            $stmt->execute([':doc' => $doc]);
            $objects = $stmt->fetchAll(PDO::FETCH_OBJ);

            if (!$objects) {
                throw new Exception('Nenhuma iniciativa encontrada.');
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $columns = ['A','B','C','D','E','F','G','H','I'];
            $widths  = [80/7, 80/7, 250/7, 250/7, 120/7, 90/7, 110/7, 120/7, 300/7];

            foreach ($columns as $i => $col) {
                $sheet->getColumnDimension($col)->setWidth($widths[$i]);
            }

            $headerStyle = [
                'font' => [
                    'name' => 'Arial',
                    'size' => 10,
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => '4B8BBE'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];

            $dataStyle = [
                'font' => [
                    'name' => 'Arial',
                    'size' => 9,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                    'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'DDDDDD'],
                    ],
                ],
            ];

            $sheet->setCellValue('A1', 'ETAPA');
            $sheet->setCellValue('B1', 'SEQ');
            $sheet->setCellValue('C1', 'DESCRIÇÃO');
            $sheet->setCellValue('D1', 'AÇÃO');
            $sheet->setCellValue('E1', 'RESPONSÁVEL');
            $sheet->setCellValue('F1', 'PRAZO');
            $sheet->setCellValue('G1', 'DATA EXECUÇÃO');
            $sheet->setCellValue('H1', 'STATUS');
            $sheet->setCellValue('I1', 'OBSERVAÇÕES');

            $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

            $row = 1;
            foreach ($objects as $obj) {
                $row++;

                $sheet->setCellValue('A'.$row, $obj->ZCN_ETAPA);
                $sheet->setCellValue('B'.$row, $obj->ZCN_SEQ);
                $sheet->setCellValue('C'.$row, $obj->ZCJ_DESCRI);
                $sheet->setCellValue('D'.$row, $obj->ZCN_ACAO);
                $sheet->setCellValue('E'.$row, $obj->ZCN_RESP);
                $sheet->setCellValue('F'.$row, self::formatDate($obj->ZCN_PRAZO));
                $sheet->setCellValue('G'.$row, self::formatDate($obj->ZCN_DATA_EXEC));
                $sheet->setCellValue(
                    'H'.$row,
                    $obj->ZCN_STATUS === 'C' ? 'Concluído' : 'Aguardando resposta'
                );

                $obs = $obj->ZCN_OBS ?? '';
                if (strlen($obs) > 255) {
                    $obs = substr($obs, 0, 252) . '...';
                }
                $sheet->setCellValue('I'.$row, $obs);

                $sheet->getStyle('A'.$row.':I'.$row)->applyFromArray($dataStyle);
                $sheet->getRowDimension($row)->setRowHeight(-1);
            }

            $sheet->getStyle('C2:D'.$row)->getAlignment()->setWrapText(true);
            $sheet->getStyle('I2:I'.$row)->getAlignment()->setWrapText(true);

            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:I'.$row);

            $nome = 'plano_acao_iniciativas_' . date('Ymd_His') . '.xlsx';
            $path = 'tmp/' . $nome;

            $writer = new Xlsx($spreadsheet);
            $writer->save($path);

            TPage::openFile($path);
            TTransaction::close();

        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }
    }
    */
}
