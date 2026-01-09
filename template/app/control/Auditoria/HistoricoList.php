<?php

use Adianti\Base\AdiantiStandardListTrait;
use Adianti\Base\TStandardList;
use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HistoricoList extends TStandardList
{
    use AdiantiStandardListTrait;

    protected $form;
    protected $datagrid;
    protected $pageNavigation;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('auditoria');
        $this->setActiveRecord('ZCM010');
        $this->setDefaultOrder('ZCM_DATA', 'desc');
        $this->setLimit(15);

        $this->buildForm();
        $this->buildDatagrid();
        $this->buildPanel();
    }

    private function buildForm()
    {
        $this->form = new BootstrapFormBuilder('form_filtro_historico');
        $this->form->setFormTitle('Filtros de Pesquisa');


        $data_de  = new TDate('data_de');
        $data_ate = new TDate('data_ate');
        $filial   = new TEntry('filial');
        $doc      = new TEntry('doc');

        $data_de->setMask('dd/mm/yyyy');
        $data_ate->setMask('dd/mm/yyyy');
        $data_de->setSize('100%');
        $data_ate->setSize('100%');
        $filial->setSize('100%');
        $doc->setSize('100%');

        $row1 = $this->form->addFields(
            [new TLabel('Data de')],
            [$data_de],
            [new TLabel('Data até')],
            [$data_ate]
        );
        $row1->layout = ['col-sm-2 col-lg-2', 'col-sm-4 col-lg-4', 'col-sm-2 col-lg-2', 'col-sm-4 col-lg-4'];

        $row2 = $this->form->addFields(
            [new TLabel('Filial')],
            [$filial],
            [new TLabel('Documento')],
            [$doc]
        );
        $row2->layout = ['col-sm-2 col-lg-2', 'col-sm-4 col-lg-4', 'col-sm-2 col-lg-2', 'col-sm-4 col-lg-4'];

        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addAction('Pesquisar', new TAction([$this, 'onSearch']), 'fa:search blue');

        $this->form->style = 'display:none; width: 100%; overflow-x: auto;';
    }

    private function buildDatagrid()
    {
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->disableDefaultClick();
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->setHeight(320);

        $col_doc     = new TDataGridColumn('ZCM_DOC',     'Documento',   'center', '14%');
        $col_filial  = new TDataGridColumn('ZCM_FILIAL',  'Filial',      'left',   '10%');
        $col_data    = new TDataGridColumn('ZCM_DATA',    'Data',        'center', '10%');
        $col_hora    = new TDataGridColumn('ZCM_HORA',    'Hora',        'center', '8%');
        $col_usuario = new TDataGridColumn('ZCM_USUGIR', 'Usuário',     'left',   '15%');
        $conformes = new TDataGridColumn('conformes', 'Conformes', 'center', '8%');
        $naoconformes = new TDataGridColumn('naoconformes', 'Não Conformes', 'center', '8%');
        $naoauditados = new TDataGridColumn('naoauditados', 'Não Auditados', 'center', '8%');
        $col_obs     = new TDataGridColumn('ZCM_OBS',     'Observações', 'left',   '20%');
        $col_obs->setTransformer(function ($value) {
            if (!$value) {
                return '';
            }

            $limite = 10;

            if (strlen($value) > $limite) {
                return substr($value, 0, $limite) . '...';
            }

            return $value;
        });


        $col_data->setTransformer([$this, 'formatarData']);
        $col_hora->setTransformer([$this, 'formatarHora']);

        $this->datagrid->addColumn($col_doc);
        $this->datagrid->addColumn($col_filial);
        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_hora);
        $this->datagrid->addColumn($col_usuario);
        $this->datagrid->addColumn($conformes);
        $this->datagrid->addColumn($naoconformes);
        $this->datagrid->addColumn($naoauditados);
        $this->datagrid->addColumn($col_obs);

        $action_view = new TDataGridAction([$this, 'onView'], ['zcm_doc' => '{ZCM_DOC}']);
        $action_view->setLabel('Ver');
        $action_view->setImage('fa:eye blue');
        $this->datagrid->addAction($action_view);

        $action_continuar = new TDataGridAction(['checkListForm', 'onContinuar'], ['doc' => '{ZCM_DOC}']);
        $action_continuar->setLabel('Continuar');
        $action_continuar->setImage('fa:play-circle green');
        $action_continuar->setDisplayCondition([$this, 'auditoriaIncompleta']);
        $this->datagrid->addAction($action_continuar);

        $action_iniciativa = new TDataGridAction(['IniciativaForm', 'onEdit'], ['doc' => '{ZCM_DOC}']);
        $action_iniciativa->setLabel('Iniciativa');
        $action_iniciativa->setImage('fa:lightbulb red');
        $action_iniciativa->setDisplayCondition([$this, 'deveExibirIniciativa']);
        $this->datagrid->addAction($action_iniciativa);

        $this->datagrid->createModel();
    }

    private function buildPanel()
    {
        $this->pageNavigation = new TPageNavigation();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup('Histórico de Auditorias Finalizadas');

        $panel->getBody()->style = 'overflow-x: auto';

        $panel->add($this->form);
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $filter_link = new TButton('filter_button');
        $filter_link->class = 'btn btn-primary btn-sm';
        $filter_link->setLabel('Filtros');
        $filter_link->setImage('fa:filter');
        $filter_link->onclick = "$('[name=form_filtro_historico]').slideToggle('medium');
                          $(this).toggleClass('active');";

        $panel->addHeaderWidget($filter_link);

        $panel->addHeaderActionLink(
            'Nova Auditoria',
            new TAction(['inicioAuditoriaModal', 'onLoad']),
            'fa:plus-circle green'
        );

        $action = new TAction([$this, 'ExcelExport']);
        $action->setParameter('register_state', 'false');

        $panel->addHeaderActionLink(
            '<i style="margin-right: 5px;"></i> Exportar XLS',
            $action
        )->class = 'btn btn-success btn-sm';

        parent::add($panel);
    }


    private function contarRespostas($doc, $tipo)
    {
        try {
            TTransaction::open('auditoria');
            $count = ZCN010::where('ZCN_DOC', '=', $doc)
                ->where('ZCN_NAOCO', '=', $tipo)
                ->where('D_E_L_E_T_', '<>', '*')
                ->count();
            TTransaction::close();
            return $count;
        } catch (Exception $e) {
            return 0;
        }
    }


    public function calcularConformes($doc)
    {
        return $this->contarRespostas($doc, 'C');
    }

    public function calcularNaoConformes($doc)
    {
        return $this->contarRespostas($doc, 'NC');
    }

    public function calcularNaoAuditados($doc)
    {
        return $this->contarRespostas($doc, 'NA');
    }


    public function onToggleFilters()
    {
        $data = new stdClass;
        $data->data_de  = TSession::getValue('hist_data_de');
        $data->data_ate = TSession::getValue('hist_data_ate');
        $data->filial   = TSession::getValue('hist_filial');
        $data->doc      = TSession::getValue('hist_doc');

        $this->form->setData($data);
    }


    public function onSearch($param = null)
    {
        $data = $this->form->getData();

        TSession::setValue('hist_data_de',  $data->data_de  ?? null);
        TSession::setValue('hist_data_ate', $data->data_ate ?? null);
        TSession::setValue('hist_filial',   $data->filial   ?? null);
        TSession::setValue('hist_doc',      $data->doc      ?? null);

        $this->form->setData($data);


        $this->onReload($param);
    }

    public function onClear($param = null)
    {
        $this->form->clear(true);

        TSession::setValue('hist_data_de',  null);
        TSession::setValue('hist_data_ate', null);
        TSession::setValue('hist_filial',   null);
        TSession::setValue('hist_doc',      null);


        $this->onReload($param);
    }


    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);

            $repository = new TRepository($this->activeRecord);
            $criteria   = new TCriteria();

            if ($de = TSession::getValue('hist_data_de')) {
                $d = implode('', array_reverse(explode('/', $de)));
                $criteria->add(new TFilter('ZCM_DATA', '>=', $d));
            }

            if ($ate = TSession::getValue('hist_data_ate')) {
                $d = implode('', array_reverse(explode('/', $ate)));
                $criteria->add(new TFilter('ZCM_DATA', '<=', $d));
            }

            if ($filial = TSession::getValue('hist_filial')) {
                $criteria->add(new TFilter('ZCM_FILIAL', '=', $filial));
            }

            if ($doc = TSession::getValue('hist_doc')) {
                $criteria->add(new TFilter('ZCM_DOC', 'like', "%{$doc}%"));
            }

            $criteria->setProperties($param);
            $criteria->setProperty('limit', $this->limit);

            $objects = $repository->load($criteria, false);

            $this->datagrid->clear();

            if ($objects) {
                foreach ($objects as $object) {
                    //$object->score_calculado = $this->calcularScore(trim($object->ZCM_DOC));
                    $object->conformes = $this->calcularConformes($object->ZCM_DOC);
                    $object->naoconformes = $this->calcularNaoConformes($object->ZCM_DOC);
                    $object->naoauditados = $this->calcularNaoAuditados($object->ZCM_DOC);
                    $this->datagrid->addItem($object);
                }
            }

            $criteria->resetProperties();
            $count = $repository->count($criteria);

            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setLimit($this->limit);
            $this->pageNavigation->setProperties($param);

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    private function calcularScore($doc)
    {
        $abriuAqui = false;

        if (!TTransaction::getDatabase()) {
            TTransaction::open('auditoria');
            $abriuAqui = true;
        }

        try {
            $conn = TTransaction::get();

            $sql = "
                SELECT 
                    cn.ZCN_NAOCO,
                    ISNULL(cl.ZCL_SCORE, 0) as ZCL_SCORE
                FROM ZCN010 cn
                LEFT JOIN ZCL010 cl ON cl.ZCL_ETAPA = cn.ZCN_ETAPA AND cl.D_E_L_E_T_ <> '*'
                WHERE cn.ZCN_DOC = :doc
                  AND cn.D_E_L_E_T_ <> '*'
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':doc' => $doc]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $perda = 0;
            foreach ($rows as $row) {
                $naoco = trim($row['ZCN_NAOCO'] ?? '');
                if (in_array($naoco, ['NC'])) {
                    $perda += (int)$row['ZCL_SCORE'];
                }
            }

            if ($abriuAqui) {
                TTransaction::close();
            }

            return 120 - $perda;
        } catch (Exception $e) {
            if ($abriuAqui) {
                TTransaction::rollback();
            }
            throw $e;
        }
    }

    private function planoEstaPendente($documento)
    {
        try {
            $conn = TTransaction::get();
            $sql = "
                SELECT COUNT(*) as total 
                FROM ZCN010 
                WHERE ZCN_DOC = :doc 
                  AND ZCN_NAOCO IN ('NC')
                  AND (ZCN_STATUS IS NULL OR ZCN_STATUS <> 'C')
                  AND D_E_L_E_T_ <> '*'
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':doc' => $documento]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['total'] > 0);
        } catch (Exception $e) {
            return false;
        }
    }

    public function deveExibirIniciativa($object)
    {
        return $this->planoEstaPendente($object->ZCM_DOC);
    }

    public function auditoriaIncompleta($object)
    {
        try {
            TTransaction::open('auditoria');

            $totalPerguntas = ZCJ010::where('D_E_L_E_T_', '<>', '*')->count();

            $respostas = ZCN010::where('ZCN_DOC', '=', $object->ZCM_DOC)
                ->where('D_E_L_E_T_', '<>', '*')
                ->count();

            if ($respostas < $totalPerguntas) {
                TTransaction::close();
                return true;
            }

            $conn = TTransaction::get();
            $sql = "SELECT COUNT(*) as total FROM ZCN010 WHERE ZCN_DOC = :doc AND ZCN_NAOCO IN ('NC' AND (ZCN_OBS IS NULL OR TRIM(ZCN_OBS) = '') AND D_E_L_E_T_ <> '*'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':doc' => $object->ZCM_DOC]);
            $result = $stmt->fetch();

            if ($result['total'] > 0) {
                TTransaction::close();
                return true;
            }

            $zcm = ZCM010::find($object->ZCM_DOC);
            if (!$zcm || empty(trim($zcm->ZCM_OBS))) {
                TTransaction::close();
                return true;
            }

            TTransaction::close();
            return false;
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            return false;
        }
    }


    public function formatarData($value)
    {
        if ($value && strlen($value) === 8 && is_numeric($value)) {
            return substr($value, 6, 2) . '/' . substr($value, 4, 2) . '/' . substr($value, 0, 4);
        }
        return $value;
    }

    public function formatarHora($value)
    {
        if ($value && strlen($value) >= 4 && is_numeric($value)) {
            return substr($value, 0, 2) . ':' . substr($value, 2, 2);
        }
        return $value;
    }

    public function onView($param)
    {
        $doc = $param['zcm_doc'] ?? null;
        if ($doc) {
            AdiantiCoreApplication::loadPage('AuditoriaView', 'onReload', ['key' => $doc]);
        }
    }

    public function ExcelExport($param)
    {
        try {
            TTransaction::open('auditoria');
            $repository = new TRepository('ZCM010');
            $criteria = new TCriteria;

            if ($de = TSession::getValue('hist_data_de')) {
                $d = implode('', array_reverse(explode('/', $de)));
                $criteria->add(new TFilter('ZCM_DATA', '>=', $d));
            }

            if ($ate = TSession::getValue('hist_data_ate')) {
                $d = implode('', array_reverse(explode('/', $ate)));
                $criteria->add(new TFilter('ZCM_DATA', '<=', $d));
            }

            if ($filial = TSession::getValue('hist_filial')) {
                $criteria->add(new TFilter('ZCM_FILIAL', '=', $filial));
            }

            if ($doc = TSession::getValue('hist_doc')) {
                $criteria->add(new TFilter('ZCM_DOC', 'like', "%{$doc}%"));
            }

            $objects = $repository->load($criteria);
            $widths = [150 / 7, 120 / 7, 120 / 7, 100 / 7, 150 / 7, 100 / 7, 100 / 7, 100 / 7, 
            300 / 7];

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
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
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => '000000'],
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
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'DDDDDD'],
                    ],
                ],
            ];

           /* $scoreStyle = [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
                'font' => [
                    'bold' => true,
                ],
                'numberFormat' => [
                    'formatCode' => '#,##0',
                ],
            ];
            */

            $sheet->setCellValue('A1', 'DOCUMENTO');
            $sheet->setCellValue('B1', 'FILIAL');
            $sheet->setCellValue('C1', 'DATA');
            $sheet->setCellValue('D1', 'HORA');
            $sheet->setCellValue('E1', 'USUÁRIO');
            //$sheet->setCellValue('F1', 'SCORE');
            $sheet->setCellValue('F1', 'CONFORMES');
            $sheet->setCellValue('G1', 'NÃO CONFORMES');
            $sheet->setCellValue('H1', 'NÃO AUDITADOS');
            $sheet->setCellValue('I1', 'OBSERVAÇÃO');
            $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
            $row = 1;
            if ($objects) {
                foreach ($objects as $obj) {
                    $row++;

                    $doc = $this->formatarDocumento($obj->ZCM_DOC);
                    $sheet->setCellValue('A' . $row, $doc);

                    $sheet->setCellValue('B' . $row, $obj->ZCM_FILIAL);

                    $dataFormatada = $this->formatarData($obj->ZCM_DATA);
                    $sheet->setCellValue('C' . $row, $dataFormatada);

                    $horaFormatada = $this->formatarHora($obj->ZCM_HORA);
                    $sheet->setCellValue('D' . $row, $horaFormatada);

                    $usuario = $obj->ZCM_USUGIR;
                    if (is_numeric($usuario)) {
                        $usuario = str_pad($usuario, 4, '0', STR_PAD_LEFT);
                    }
                    $sheet->setCellValue('E' . $row, $usuario);

                    //$score = round($this->calcularScore(trim($obj->ZCM_DOC)));
                    //$sheet->setCellValue('F' . $row, $score);

                    $obs = $obj->ZCM_OBS ?? '';
                    if (strlen($obs) > 255) {
                        $obs = substr($obs, 0, 252) . '...';
                    }
                    $sheet->setCellValue('F' . $row, $this->calcularConformes($obj->ZCM_DOC));
                    $sheet->setCellValue('G' . $row, $this->calcularNaoConformes($obj->ZCM_DOC));
                    $sheet->setCellValue('H' . $row, $this->calcularNaoAuditados($obj->ZCM_DOC));
                    $sheet->setCellValue('I' . $row, $obs);

                    $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($dataStyle);
                    $sheet->getRowDimension($row)->setRowHeight(-1);
                }
            }

            $sheet->getStyle('I2:I' . $row)->getAlignment()->setWrapText(true);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:I' . $row);

            foreach ($columns as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(false);
            }

            $nome = 'historico_auditoria_' . date('Ymd_His') . '.xlsx';
            $path = 'tmp/' . $nome;

            $writer = new Xlsx($spreadsheet);
            $writer->save($path);

            TPage::openFile($path);
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    private function formatarDocumento($doc)
    {
        $doc = preg_replace('/[^0-9]/', '', $doc);

        if (strlen($doc) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        } elseif (strlen($doc) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        }

        return $doc;
    }
}
