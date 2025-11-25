<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Validator\TRequiredValidator;
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
    private $opcoes_resposta = [
        'C'  => 'Conforme',
        'NC' => 'Não Conforme',
        'OP' => 'Oportunidade de melhoria',
        'P'  => 'Parcialmente',
        'NV' => 'Não visto'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setFormTitle('CheckList de Auditoria');
    }

    public function onStart($param)
    {
        try {
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

            $perguntas = $this->buscarPerguntasComScore($tipo);

            if (empty($perguntas)) {
                throw new Exception("Nenhuma pergunta encontrada para o tipo {$tipo}.");
            }

            $dados_salvos = $this->buscarDadosSalvos();

            TTransaction::close();

            $this->montarFormulario($tipoObj, $tipo, $perguntas, $dados_salvos);
        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function buscarPerguntasComScore($tipo)
    {
        $criteria = new TCriteria;
        $criteria->add(new TFilter('ZCJ010.D_E_L_E_T_', '<>', '*'));
        $criteria->add(new TFilter('ZCL010.D_E_L_E_T_', '<>', '*'));
        $criteria->add(new TFilter('ZCL010.ZCL_TIPO', '=', $tipo));
        $criteria->setProperty('order', 'ZCJ010.ZCJ_ETAPA');

        $repo = new TRepository('ZCJ010');

        $etapas_tipo = ZCL010::where('ZCL_TIPO', '=', $tipo)
            ->where('D_E_L_E_T_', '<>', '*')
            ->load();

        if (empty($etapas_tipo)) {
            throw new Exception("Nenhuma etapa vinculada ao tipo {$tipo}.");
        }

        $scores_por_etapa = [];
        foreach ($etapas_tipo as $rel) {
            $scores_por_etapa[$rel->ZCL_ETAPA] = $rel->ZCL_SCORE ?? 0;
        }

        $criteria_perguntas = new TCriteria;
        $criteria_perguntas->add(new TFilter('D_E_L_E_T_', '<>', '*'));
        $criteria_perguntas->add(new TFilter('ZCJ_ETAPA', 'IN', array_keys($scores_por_etapa)));
        $criteria_perguntas->setProperty('order', 'ZCJ_ETAPA');

        $perguntas = $repo->load($criteria_perguntas);

        foreach ($perguntas as $p) {
            $p->score = $scores_por_etapa[$p->ZCJ_ETAPA] ?? 0;
        }

        return $perguntas;
    }

    private function buscarDadosSalvos()
    {
        $view_mode = TSession::getValue('view_mode') ?? false;
        $view_data = TSession::getValue('view_auditoria');

        $dados = [
            'readonly' => $view_mode,
            'respostas' => [],
            'observacoes' => [],
            'obs_gerais' => ''
        ];

        if ($view_mode && $view_data) {
            $respostas = ZCN010::where('ZCN_DOC', '=', $view_data['doc'])
                ->where('D_E_L_E_T_', '<>', '*')
                ->load();

            foreach ($respostas as $r) {
                $dados['respostas'][$r->ZCN_ETAPA] = $r->ZCN_TIPO ?? '';
                $dados['observacoes'][$r->ZCN_ETAPA] = $r->ZCN_OBS ?? '';
            }

            $zcm = ZCM010::where('ZCM_DOC', '=', $view_data['doc'])
                ->where('D_E_L_E_T_', '<>', '*')
                ->first();

            if ($zcm) {
                $dados['obs_gerais'] = $zcm->ZCM_OBS ?? '';
            }
        }

        return $dados;
    }

    private function montarFormulario($tipoObj, $tipo, $perguntas, $dados_salvos)
    {
        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setColumnClasses(2, ['col-sm-8', 'col-sm-4']);

        $titulo = $dados_salvos['readonly']
            ? "Visualização: {$tipoObj->ZCK_DESCRI}"
            : "CheckList: {$tipoObj->ZCK_DESCRI}";
        $this->form->setFormTitle($titulo);

        $this->form->addFields([new THidden('tipo')]);
        $this->form->setData((object)['tipo' => $tipo]);

        foreach ($perguntas as $p) {
            $this->renderizarPergunta($p, $dados_salvos);
        }

        $this->adicionarObservacoesGerais($dados_salvos);

        if (!$dados_salvos['readonly']) {
            $this->adicionarBotoes();
        }

        parent::add($this->form);
    }

    private function renderizarPergunta($pergunta, $dados_salvos)
    {
        $etapa = $pergunta->ZCJ_ETAPA;
        $desc = $pergunta->ZCJ_DESCRI;
        $score = $pergunta->score;

        $combo = new TCombo("resposta_{$etapa}");
        $combo->addItems($this->opcoes_resposta);
        $combo->setSize('100%');
        $combo->setValue($dados_salvos['respostas'][$etapa] ?? '');
        $combo->addValidation("Etapa {$etapa} - Conformidade", new TRequiredValidator);
        if ($dados_salvos['readonly']) $combo->setEditable(false);

        $score_label = new TLabel("<b>Score:</b> {$score}");
        $score_label->setFontColor('#666');

        $obs = new TText("obs_{$etapa}");
        $obs->setSize('100%', 80);
        $obs->setValue($dados_salvos['observacoes'][$etapa] ?? '');
        $obs->addValidation("Etapa {$etapa} - Observações", new TRequiredValidator);
        if ($dados_salvos['readonly']) $obs->setEditable(false);

        $this->form->addFields(
            [new TLabel("<b>Etapa {$etapa}:</b> {$desc}")],
            [$combo, $score_label]
        );
        $this->form->addFields(
            [new TLabel('Problemas Encontrados <span style="color:red;">*</span>')],
            [$obs]
        );
    }

    private function adicionarObservacoesGerais($dados_salvos)
    {
        $obs_gerais = new TText('observacoes_gerais');
        $obs_gerais->setSize('100%', 120);
        $obs_gerais->setValue($dados_salvos['obs_gerais']);
        if ($dados_salvos['readonly']) $obs_gerais->setEditable(false);

        $this->form->addFields(
            [new TLabel('Observações Gerais:')],
            [$obs_gerais]
        );
    }

    private function adicionarBotoes()
    {
        $this->form->addAction('Voltar', new TAction(['inicioAuditoriaModal', 'onReload']), 'fa:arrow-left');

        $btn = new TButton('salvar');
        $btn->setLabel('Salvar');
        $btn->setImage('fa:save green');
        $btn->setAction(new TAction([$this, 'onSave']));
        $btn->style = 'display:none';

        $this->form->addFields([], [$btn]);

        TScript::create("
            function validarChecklist() {
                let ok = true;
                
                document.querySelectorAll('select[name^=resposta_]').forEach(c => {
                    if (c.value == '') ok = false;
                });

                document.querySelectorAll('textarea[name^=obs_]').forEach(t => {
                    if (t.value.trim() === '') ok = false;
                });

                let obsGerais = document.querySelector('textarea[name=observacoes_gerais]');
                if (!obsGerais || obsGerais.value.trim() === '') ok = false;

                let botao = document.querySelector('button[name=salvar]');
                if (botao) {
                    botao.style.display = ok ? 'inline-block' : 'none';
                }
            }

            document.addEventListener('keyup', validarChecklist);
            document.addEventListener('change', validarChecklist);
        ");
    }

    public function onSave($param)
    {
        try {
            $tipo = $param['tipo'] ?? null;
            if (!$tipo) throw new Exception('Tipo não informado.');

            TTransaction::open('auditoria');

            $novoDoc = self::gerarNovoDocumento();

            self::salvarCabecalho($novoDoc, $tipo, $param);

            $scores_por_etapa = self::buscarScoresPorEtapa($tipo);

            $salvo = self::salvarRespostas($novoDoc, $tipo, $param, $scores_por_etapa);

            if (!$salvo) throw new Exception('Nenhuma resposta registrada.');

            TTransaction::close();

            new TMessage('info', "Auditoria nº {$novoDoc} finalizada com sucesso!");
            AdiantiCoreApplication::loadPage('HistoricoList', 'onReload', ['doc' => $novoDoc]);
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao salvar: ' . $e->getMessage());
            TTransaction::rollbackAll();
        }
    }

    private  function gerarNovoDocumento()
    {
        $ultimo = ZCM010::orderBy('ZCM_DOC', 'desc')->first();
        return $ultimo ? str_pad(((int) $ultimo->ZCM_DOC) + 1, 6, '0', STR_PAD_LEFT) : '000001';
    }

    private  function salvarCabecalho($doc, $tipo, $param)
    {
        $zcm = new ZCM010;
        $zcm->ZCM_DOC     = $doc;
        $zcm->ZCM_FILIAL  = $param['filial'] ?? '1';
        $zcm->ZCM_TIPO    = $tipo;
        $zcm->ZCM_DATA    = date('Ymd');
        $zcm->ZCM_HORA    = date('Hi');
        $zcm->ZCM_USUARIO = TSession::getValue('username');
        $zcm->ZCM_OBS     = trim($param['observacoes_gerais'] ?? '') ?: null;
        $zcm->store();
    }

    private  function buscarScoresPorEtapa($tipo)
    {
        $etapas = ZCL010::where('ZCL_TIPO', '=', $tipo)
            ->where('D_E_L_E_T_', '<>', '*')
            ->load();

        $scores = [];
        foreach ($etapas as $e) {
            $scores[$e->ZCL_ETAPA] = (float) ($e->ZCL_SCORE ?? 0);
        }
        return $scores;
    }

    private  function salvarRespostas($doc, $tipo, $param, $scores_por_etapa)
    {
        $perguntas = ZCJ010::where('ZCJ_ETAPA', 'IN', array_keys($scores_por_etapa))
            ->where('D_E_L_E_T_', '<>', '*')
            ->orderBy('ZCJ_ETAPA')
            ->load();

        $data = date('Ymd');
        $hora = date('Hi');
        $usuario = TSession::getValue('username');
        $salvo = false;

        foreach ($perguntas as $p) {
            $etapa = $p->ZCJ_ETAPA;
            $resposta = $param["resposta_{$etapa}"] ?? '';

            if (!$resposta) continue;

            $zcn = new ZCN010;
            $zcn->ZCN_DOC     = $doc;
            $zcn->ZCN_ETAPA   = $etapa;
            $zcn->ZCN_DESCRI  = $p->ZCJ_DESCRI ?? '';
            $zcn->ZCN_TIPO    = $resposta;
            $zcn->ZCN_DATA    = $data;
            $zcn->ZCN_HORA    = $hora;
            $zcn->ZCN_USUARIO = $usuario;
            $zcn->ZCN_OBS     = trim($param["obs_{$etapa}"] ?? '') ?: null;



            //ACHO QUE RESOLVI AAAAAAAAAAAAAAAAAAAAAAAA
            if (in_array($resposta, ['NC', 'P', 'OP'])) {
                $zcn->ZCN_NAOCO = $resposta;
                $zcn->ZCN_SCORE = ($scores_por_etapa[$etapa] ?? 0);
            }

            $zcn->store();
            $salvo = true;
        }

        return $salvo;
    }

    public  function onOpenCurtain($param)
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
