<?php

use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Form\TText;

class checkListForm extends TPage
{
    protected $form;

    public function __construct()
    {
        parent::__construct();
        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setFormTitle('CheckList de Auditoria');
    }

    public function onStart($param)
    {
        try {
            $view_mode = TSession::getValue('view_mode') ?? false;
            $view_data = TSession::getValue('view_auditoria');

            $tipo = $param['tipo'] ?? TSession::getValue('auditoria_tipo');
            if (!$tipo) {
                throw new Exception('Tipo de auditoria não informado.');
            }

            TTransaction::open('auditoria');

            $tipoObj = ZCK010::where('ZCK_TIPO', '=', $tipo)
                ->where('D_E_L_E_T_', '<>', '*')
                ->first();
            if (!$tipoObj) {
                throw new Exception('Tipo não encontrado.');
            }

            $etapas_tipo = ZCL010::where('ZCL_TIPO', '=', $tipo)
                ->where('D_E_L_E_T_', '<>', '*')
                ->getIndexedArray('ZCL_ETAPA', 'ZCL_ETAPA');

            if (empty($etapas_tipo)) {
                throw new Exception("Nenhuma etapa vinculada ao tipo {$tipo} encontrada em ZCL010.");
            }

            $criteria = new TCriteria;
            $criteria->add(new TFilter('D_E_L_E_T_', '<>', '*'));
            $criteria->add(new TFilter('ZCJ_ETAPA', 'IN', array_keys($etapas_tipo)));
            $criteria->setProperty('order', 'ZCJ_ETAPA');

            $repo = new TRepository('ZCJ010');
            $perguntas = $repo->load($criteria) ?? [];

            if (empty($perguntas)) {
                throw new Exception("Nenhuma pergunta encontrada para o tipo {$tipo}.");
            }

            // === BUSCA RESPOSTAS SALVAS (ZCN) EM MODO VISUALIZAÇÃO ===
            $respostas_salvas = [];
            $obs_salvas = [];

            if ($view_mode && $view_data) {
                $criteria_cn = new TCriteria;
                $criteria_cn->add(new TFilter('ZCN_DOC', '=', $view_data['doc']));
                $criteria_cn->add(new TFilter('D_E_L_E_T_', '<>', '*'));
                $repo_cn = new TRepository('ZCN');
                $respostas = $repo_cn->load($criteria_cn);

                foreach ($respostas as $r) {
                    $respostas_salvas[$r->ZCN_ETAPA] = $r->ZCN_TIPO ?? '';
                    $obs_salvas[$r->ZCN_ETAPA] = $r->ZCN_NAOCO ?? '';
                }
            }

            TTransaction::close();

            $this->montarFormulario($tipoObj, $tipo, $perguntas, $respostas_salvas, $obs_salvas, $view_mode, $view_data);

        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function montarFormulario($tipoObj, $tipo, $perguntas, $respostas_salvas, $obs_salvas, $readonly = false, $view_data = null)
    {
        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setColumnClasses(2, ['col-sm-8', 'col-sm-4']);

        $titulo = $readonly
            ? "Visualização: {$tipoObj->ZCK_DESCRI}"
            : "CheckList: {$tipoObj->ZCK_DESCRI}";
        $this->form->setFormTitle($titulo);

        $this->form->addFields([new THidden('tipo')]);
        $this->form->setData((object)['tipo' => $tipo]);

        $opcoes = [
            'C'  => 'Conforme',
            'NC' => 'Não Conforme',
            'OP' => 'Oportunidade de melhoria',
            'P'  => 'Parcialmente',
            'NV' => 'Não visto'
        ];

        TTransaction::open('auditoria');

        foreach ($perguntas as $p) {
            $etapa = $p->ZCJ_ETAPA;
            $desc  = $p->ZCJ_DESCRI;

            $etapa_info = ZCL010::where('ZCL_ETAPA', '=', $etapa)
                ->where('D_E_L_E_T_', '<>', '*')
                ->first();
            $score = $etapa_info->ZCL_SCORE ?? 0;

            $combo = new TCombo("resposta_{$etapa}");
            $combo->addItems($opcoes);
            $combo->setSize('100%');
            $combo->setValue($respostas_salvas[$etapa] ?? 'C');
            if ($readonly) $combo->setEditable(false);

            // Campo de observação sempre visível agora
            $obs = new TText("obs_{$etapa}");
            $obs->setSize('100%', 80);
            $obs->setValue($obs_salvas[$etapa] ?? '');
            if ($readonly) $obs->setEditable(false);

            $score_label = new TLabel("<b>Score:</b> {$score}");
            $score_label->setFontColor('#666');

            $this->form->addFields(
                [new TLabel("<b>Etapa {$etapa}:</b> {$desc}")],
                [$combo, $score_label]
            );
            $this->form->addFields(
                [new TLabel('Observações:')],
                [$obs]
            );
        }

        TTransaction::close();

        if (!$readonly) {
            $btn = new TButton('salvar');
            $btn->setLabel('Finalizar Auditoria');
            $btn->setImage('fa:check green');
            $btn->setAction(new \Adianti\Control\TAction([$this, 'onSave']));
            $this->form->addFields([], [$btn]);
        }

        parent::add($this->form);
    }

    public static function onSave($param)
    {
        try {
            $tipo = $param['tipo'] ?? null;
            if (!$tipo) throw new Exception('Tipo não informado.');

            TTransaction::open('auditoria');

            $data    = date('Ymd');
            $hora    = date('Hi');
            $usuario = TSession::getValue('userid') ?? 'SYSTEM';
            $filial  = $param['filial'] ?? '1';

            // === CABEÇALHO (ZCM010) ===
            $zcm = new ZCM010;
            $ultimo = ZCM010::orderBy('ZCM_DOC', 'desc')->first();
            $novoDoc = $ultimo ? str_pad(((int) $ultimo->ZCM_DOC) + 1, 6, '0', STR_PAD_LEFT) : '000001';
            $zcm->ZCM_DOC     = $novoDoc;
            $zcm->ZCM_FILIAL  = $filial;
            $zcm->ZCM_TIPO    = $tipo;
            $zcm->ZCM_DATA    = $data;
            $zcm->ZCM_HORA    = $hora;
            $zcm->ZCM_USUARIO = $usuario;

            $observacoes_gerais = []; // guarda todas observações de etapas

            // === PERGUNTAS ===
            $etapas_tipo = ZCL010::where('ZCL_TIPO', '=', $tipo)
                ->where('D_E_L_E_T_', '<>', '*')
                ->getIndexedArray('ZCL_ETAPA', 'ZCL_ETAPA');

            $criteria = new TCriteria;
            $criteria->add(new TFilter('D_E_L_E_T_', '<>', '*'));
            $criteria->add(new TFilter('ZCJ_ETAPA', 'IN', array_keys($etapas_tipo)));
            $criteria->setProperty('order', 'ZCJ_ETAPA');

            $repo = new TRepository('ZCJ010');
            $perguntas = $repo->load($criteria);

            $salvo = false;

            foreach ($perguntas as $p) {
                $etapa    = $p->ZCJ_ETAPA;
                $pergunta = $p->ZCJ_DESCRI ?? '';
                $resposta = $param["resposta_{$etapa}"] ?? '';
                $obs_etapa = trim($param["obs_{$etapa}"] ?? '');

                if ($resposta) {
                    $zcn = new ZCN010;
                    $zcn->ZCN_DOC     = $novoDoc;
                    $zcn->ZCN_ETAPA   = $etapa;
                    $zcn->ZCN_DESCRI  = $pergunta;
                    $zcn->ZCN_TIPO    = $resposta;
                    $zcn->ZCN_DATA    = $data;
                    $zcn->ZCN_HORA    = $hora;
                    $zcn->ZCN_USUARIO = $usuario;

                    // Só grava em ZCN_NAOCO se for ≠ Conforme
                    $zcn->ZCN_NAOCO = ($resposta !== 'C') ? $obs_etapa : null;

                    $zcn->store();

                    // acumula observações (todas, inclusive conforme)
                    if ($obs_etapa !== '') {
                        $observacoes_gerais[] = "Etapa {$etapa}: {$obs_etapa}";
                    }

                    $salvo = true;
                }
            }

            if (!$salvo) throw new Exception('Nenhuma resposta registrada.');

            // Salva as observações gerais no cabeçalho
            $zcm->ZCM_OBS = !empty($observacoes_gerais)
                ? implode("\n", $observacoes_gerais)
                : null;

            $zcm->store();

            TTransaction::close();

            new TMessage('info', "Auditoria nº {$novoDoc} finalizada com sucesso!");
            TScript::create("
                setTimeout(() => {
                    Adianti.currentWindow?.close();
                    __adianti_load_page('index.php?class=HistoricoList&doc={$novoDoc}');
                }, 1500);
            ");

        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function formatarData($d)
    {
        return strlen($d) == 8 ? substr($d, 6, 2) . '/' . substr($d, 4, 2) . '/' . substr($d, 0, 4) : $d;
    }

    private function formatarHora($h)
    {
        return strlen($h) == 6 ? substr($h, 0, 2) . ':' . substr($h, 2, 2) . ':' . substr($h, 4, 2) : $h;
    }

    public static function onOpenCurtain($param)
    {
        $win = TWindow::create('CheckList de Auditoria', 0.9, 0.9);
        $win->removePadding();
        $page = new self();
        $page->onStart($param);
        $win->add($page);
        $win->setIsWrapped(true);
        $win->show();
    }
}
