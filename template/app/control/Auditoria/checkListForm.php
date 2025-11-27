<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Core\AdiantiCoreApplication;
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
            $filial = $param['filial'] ?? TSession::getValue('auditoria_filial');
            if (!$filial) {
                throw new Exception('Filial não informada.');
            }

            TTransaction::open('auditoria');

            $tipos = ZCK010::where('D_E_L_E_T_', '<>', '*')
                ->orderBy('ZCK_TIPO')
                ->load();

            if (empty($tipos)) {
                throw new Exception('Nenhum tipo de auditoria cadastrado.');
            }

            $perguntas_com_score = $this->buscarTodasPerguntasComScore();

            if (empty($perguntas_com_score)) {
                throw new Exception("Nenhuma pergunta encontrada.");
            }

            $dados_salvos = $this->buscarDadosSalvos();

            TTransaction::close();

            $this->montarFormularioCompleto($filial, $tipos, $perguntas_com_score, $dados_salvos);
        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function buscarTodasPerguntasComScore()
    {
        $perguntas = [];

        $etapas = ZCL010::where('D_E_L_E_T_', '<>', '*')->load();
        if (empty($etapas)) {
            return $perguntas;
        }

        $scores = [];
        $etapas_ids = [];
        foreach ($etapas as $e) {
            $etapas_ids[] = $e->ZCL_ETAPA;
            $scores[$e->ZCL_ETAPA] = (float)($e->ZCL_SCORE ?? 0);
        }

        
        $pergs = ZCJ010::where('ZCJ_ETAPA', 'IN', $etapas_ids)
            ->where('D_E_L_E_T_', '<>', '*')
            ->orderBy('ZCJ_ETAPA')
            ->load();

        if (empty($pergs)) {
            return $perguntas;
        }

        $mapa_etapa_para_tipo = [];
        foreach ($etapas as $e) {
            $mapa_etapa_para_tipo[$e->ZCL_ETAPA] = $e->ZCL_TIPO ?? null;
        }

        $tipos = ZCK010::where('D_E_L_E_T_', '<>', '*')->load();
        $mapa_tipo_descricao = [];
        foreach ($tipos as $t) {
            $mapa_tipo_descricao[$t->ZCK_TIPO] = $t->ZCK_DESCRI ?? "Tipo {$t->ZCK_TIPO}";
        }

        foreach ($pergs as $p) {
            $p->score = $scores[$p->ZCJ_ETAPA] ?? 0;
            $tipo_codigo = $mapa_etapa_para_tipo[$p->ZCJ_ETAPA] ?? null;
            $p->tipo_descricao = $mapa_tipo_descricao[$tipo_codigo] ?? ($tipo_codigo ? "Tipo {$tipo_codigo}" : 'Sem tipo');
            $perguntas[] = $p;
        }

        return $perguntas;
    }

    private function montarFormularioCompleto($filial, $tipos, $perguntas, $dados_salvos)
    {
        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setColumnClasses(2, ['col-sm-8', 'col-sm-4']);
        $this->form->setFormTitle('CheckList Completo - Filial: ' . $filial);

        $this->form->addFields([new THidden('filial')]);
        $this->form->setData((object)['filial' => $filial]);

        $tipo_atual = null;

        foreach ($perguntas as $p) {
            if ($p->tipo_descricao !== $tipo_atual) {
                $tipo_atual = $p->tipo_descricao;
                $this->form->appendPage($tipo_atual);
            }

            $this->renderizarPergunta($p, $dados_salvos);
        }

        $this->adicionarObservacoesGerais($dados_salvos);

        if (!($dados_salvos['readonly'] ?? false)) {
            $this->adicionarBotoes();
        }

        parent::add($this->form);
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
                // Armazenar respostas por etapa
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
        if (!empty($dados_salvos['readonly'])) $combo->setEditable(false);

        $score_label = new TLabel("<b>Score:</b> {$score}");
        $score_label->setFontColor('#666');

        $obs = new TText("obs_{$etapa}");
        $obs->setSize('100%', 80);
        $obs->setValue($dados_salvos['observacoes'][$etapa] ?? '');
        $obs->addValidation("Etapa {$etapa} - Observações", new TRequiredValidator);
        if (!empty($dados_salvos['readonly'])) $obs->setEditable(false);

        $this->form->addFields(
            [new TLabel("<b>Etapa {$etapa}:</b> {$desc}")],
            [$combo, $score_label]
        );
        $this->form->addFields(
            [new TLabel('Problemas Encontrados: <span style="color:red;">*</span>')],
            [$obs]
        );
    }

    private function adicionarObservacoesGerais($dados_salvos)
    {
        $obs_gerais = new TText('observacoes_gerais');
        $obs_gerais->setSize('100%', 120);
        $obs_gerais->setValue($dados_salvos['obs_gerais']);
        if (!empty($dados_salvos['readonly'])) $obs_gerais->setEditable(false);

        $this->form->addFields(
            [new TLabel('Observações Gerais: <span style="color:red">*</span>')],
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
            TTransaction::open('auditoria');

            $novoDoc = self::gerarNovoDocumento();

            self::salvarCabecalho($novoDoc, $param);

            $scores_por_etapa = self::buscarScoresPorEtapa();

            $salvo = self::salvarRespostas($novoDoc, $param, $scores_por_etapa);

            if (!$salvo) throw new Exception('Nenhuma resposta registrada.');

            TTransaction::close();

            new TMessage('info', "Auditoria nº {$novoDoc} finalizada com sucesso!");
            AdiantiCoreApplication::loadPage('HistoricoList', 'onReload', ['doc' => $novoDoc]);
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao salvar: ' . $e->getMessage());
            TTransaction::rollbackAll();
        }
    }

    private function gerarNovoDocumento()
    {
        $ultimo = ZCM010::orderBy('ZCM_DOC', 'desc')->first();
        return $ultimo ? str_pad(((int) $ultimo->ZCM_DOC) + 1, 6, '0', STR_PAD_LEFT) : '000001';
    }

    private function salvarCabecalho($doc, $param)
    {
        $zcm = new ZCM010;
        $zcm->ZCM_DOC     = $doc;
        $zcm->ZCM_FILIAL  = $param['filial'] ?? '1';
        $zcm->ZCM_DATA    = date('Ymd');
        $zcm->ZCM_HORA    = date('Hi');
        $zcm->ZCM_USUARIO = TSession::getValue('username');
        $zcm->ZCM_OBS     = trim($param['observacoes_gerais'] ?? '') ?: null;
        $zcm->store();
    }

    private function buscarScoresPorEtapa()
    {
        $etapas = ZCL010::where('D_E_L_E_T_', '<>', '*')
            ->load();

        $scores = [];
        foreach ($etapas as $e) {
            $scores[$e->ZCL_ETAPA] = (float) ($e->ZCL_SCORE ?? 0);
        }
        return $scores;
    }

    private function salvarRespostas($doc, $param, $scores_por_etapa)
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
