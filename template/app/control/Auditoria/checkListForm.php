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
use Adianti\Widget\Form\TText;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Dialog\TQuestion;

class checkListForm extends TPage
{
    protected $form;
    private $opcoes_resposta =
    [
        'C'  => 'Conforme',
        'NC' => 'Não Conforme',
        'OP' => 'Oportunidade de melhoria',
        'P'  => 'Parcialmente',
        'NV' => 'Não visto'
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function onStart($param)
    {
        try {
            $filial = $param['filial'] ?? TSession::getValue('auditoria_filial');
            $doc    = $param['doc'] ?? null;

            if (!$filial) {
                throw new Exception('Filial não informada.');
            }

            TSession::setValue('auditoria_filial', $filial);

            if ($doc) {
                TSession::setValue('view_auditoria', ['doc' => $doc]);
                TSession::setValue('view_mode', false);
            }

            $pagina_atual = $param['pagina'] ?? TSession::getValue('checklist_pagina_atual') ?? 0;
            TSession::setValue('checklist_pagina_atual', $pagina_atual);

            TTransaction::open('auditoria');
            $perguntas_agrupadas = $this->buscarPerguntasAgrupadasPorTipo();
            if (empty($perguntas_agrupadas)) {
                throw new Exception('Nenhuma pergunta encontrada.');
            }

            $dados_salvos = $this->buscarDadosSalvos();
            TTransaction::close();

            $this->montarFormulario($filial, $perguntas_agrupadas, $dados_salvos, $pagina_atual);
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }
    }


    private function buscarPerguntasAgrupadasPorTipo()
    {
        $etapas = ZCL010::where('D_E_L_E_T_', '<>', '*')->load();
        if (empty($etapas)) return [];

        $scores = [];
        $ids = [];
        $mapa_tipo = [];

        foreach ($etapas as $e) {
            $ids[] = $e->ZCL_ETAPA;
            $scores[$e->ZCL_ETAPA] = (float) ($e->ZCL_SCORE ?? 0);
            $mapa_tipo[$e->ZCL_ETAPA] = $e->ZCL_TIPO ?? null;
        }

        $pergs = ZCJ010::where('ZCJ_ETAPA', 'IN', $ids)->where('D_E_L_E_T_', '<>', '*')->orderBy('ZCJ_ETAPA')->load();
        $tipos = ZCK010::where('D_E_L_E_T_', '<>', '*')->load();

        $descricao_tipos = [];
        foreach ($tipos as $t) {
            $descricao_tipos[$t->ZCK_TIPO] = $t->ZCK_DESCRI;
        }

        $agrupado = [];
        foreach ($pergs as $p) {
            $tipo_codigo = $mapa_tipo[$p->ZCJ_ETAPA] ?? null;
            $tipo_desc = $descricao_tipos[$tipo_codigo] ?? "Tipo {$tipo_codigo}";
            $p->score = $scores[$p->ZCJ_ETAPA] ?? 0;
            $p->tipo_descricao = $tipo_desc;
            if (!isset($agrupado[$tipo_desc])) {
                $agrupado[$tipo_desc] = [];
            }
            $agrupado[$tipo_desc][] = $p;
        }

        return $agrupado;
    }

    private function montarFormulario($filial, $perguntas_agrupadas, $dados_salvos, $pagina_atual)
    {
        $this->form = new BootstrapFormBuilder('form_checklist');
        $tipos = array_keys($perguntas_agrupadas);
        $total_paginas = count($tipos) + 1;

        if ($pagina_atual < count($tipos)) {
            $tipo_atual = $tipos[$pagina_atual];
            $titulo = "Checklist - Filial $filial - {$tipo_atual} (Etapa " . ($pagina_atual + 1) . " de {$total_paginas})";
        } else {
            $titulo = "Checklist - Filial $filial - Finalização (Etapa {$total_paginas} de {$total_paginas})";
        }

        $this->form->setFormTitle($titulo);

        $hidden_filial = new THidden('filial');
        $hidden_filial->value = $filial;
        $this->form->addFields([$hidden_filial]);

        $data = new stdClass();
        $data->filial = $filial;

        if ($dados_salvos['readonly'] ?? false) {
            foreach ($dados_salvos['respostas'] as $etapa => $valor) {
                $data->{"resposta_{$etapa}"} = $valor;
            }
            foreach ($dados_salvos['observacoes'] as $etapa => $valor) {
                $data->{"obs_{$etapa}"} = $valor;
            }
            $data->observacoes_gerais = $dados_salvos['obs_gerais'] ?? '';
        } else {
            $temp = TSession::getValue('checklist_dados_temp') ?? [];
            foreach ($temp as $campo => $valor) {
                if (
                    strpos($campo, 'resposta_') === 0 ||
                    strpos($campo, 'obs_') === 0 ||
                    $campo === 'observacoes_gerais' ||
                    $campo === 'filial'
                ) {
                    $data->$campo = $valor;
                }
            }
        }

        if ($pagina_atual < count($tipos)) {
            $tipo_atual = $tipos[$pagina_atual];
            $perguntas = $perguntas_agrupadas[$tipo_atual];
            foreach ($perguntas as $p) {
                $this->renderPergunta($p, $dados_salvos);
            }
        } else {
            $this->renderObsGerais($dados_salvos);
        }

        $this->form->setData($data);

        if ($pagina_atual < count($tipos) && !($dados_salvos['readonly'] ?? false)) {
            $js = '';
            $perguntas = $perguntas_agrupadas[$tipo_atual] ?? [];
            foreach ($perguntas as $p) {
                $etapa = $p->ZCJ_ETAPA;
                $value = $data->{"resposta_{$etapa}"} ?? '';
                $required = in_array($value, ['NC', 'P', 'OP']);
                $js .= "var f = document.getElementById('obs_{$etapa}'); if(f) f.required = " . ($required ? 'true' : 'false') . ";\n";
            }
            if ($js) {
                TScript::create($js);
            }
        }

        $dados_temp = TSession::getValue('checklist_dados_temp') ?? [];
        $auditoriaCompleta = $this->auditoriaCompleta($perguntas_agrupadas, $dados_salvos, $dados_temp);

        if (!($dados_salvos['readonly'] ?? false)) {
            $this->addNumericPagination($pagina_atual, $total_paginas);
            $this->addNavigationButtons($pagina_atual, $total_paginas, $auditoriaCompleta);
        }

        parent::add($this->form);
    }

    private function renderPergunta($p, $dados)
    {
        $etapa = $p->ZCJ_ETAPA;
        $combo = new TCombo("resposta_{$etapa}");
        $combo->addItems($this->opcoes_resposta);
        $combo->setSize('100%');
        $combo->addValidation("Etapa {$etapa}", new TRequiredValidator);

        $obs = new TText("obs_{$etapa}");
        $obs->setSize('100%', 80);

        if ($dados['readonly'] ?? false) {
            $combo->setEditable(false);
            $obs->setEditable(false);
        } else {
            $action = new TAction([__CLASS__, 'onChangeResposta']);
            $action->setParameter('etapa', $etapa);
            $combo->setChangeAction($action);
        }

        $score_label = new TLabel("<b>Score:</b> {$p->score}");
        if (method_exists($score_label, 'setFontColor')) {
            $score_label->setFontColor('#666');
        }

        $this->form->addFields([new TLabel("<b>{$p->ZCJ_DESCRI}</b>")], [$combo, $score_label]);
        $this->form->addFields([new TLabel('Problemas Encontrados:')], [$obs]);
    }

    public static function onChangeResposta($param)
    {
        $etapa = $param['etapa'] ?? '';
        $value = $param['value'] ?? '';
        if ($etapa !== '') {
            $required = in_array($value, ['NC', 'P', 'OP']);
            TScript::create("var field = document.getElementById('obs_{$etapa}'); if(field) field.required = " . ($required ? 'true' : 'false') . ";");
        }
    }

    private function renderObsGerais($dados)
    {
        $obs = new TText('observacoes_gerais');
        $obs->setSize('100%', 120);
        if ($dados['readonly'] ?? false) $obs->setEditable(false);
        $this->form->addFields([new TLabel("<b>Observações Gerais da Auditoria:</b>")], [$obs]);
    }

    public function onNavigate($param)
    {
        $this->salvarDadosTemporarios($param);
        try {
            TTransaction::open('auditoria');
            $doc = TSession::getValue('doc_rascunho_atual');
            if (!$doc) {
                $doc = $this->gerarNovoDoc();
                TSession::setValue('doc_rascunho_atual', $doc);
            }
            $dados = TSession::getValue('checklist_dados_temp') ?? [];
            $this->salvarCabecalhoCompleto($doc, $dados);
            $this->salvarRespostas($doc, $dados);
            TTransaction::close();
        } catch (Exception $e) {
        }
        $param['filial'] = TSession::getValue('auditoria_filial');
        $this->onStart($param);
    }

    public function onSaveProgress($param)
    {
        try {
            $this->salvarDadosTemporarios($param);
            TTransaction::open('auditoria');
            $doc = TSession::getValue('doc_rascunho_atual');
            if (!$doc) {
                $doc = $this->gerarNovoDoc();
                TSession::setValue('doc_rascunho_atual', $doc);
            }
            $dados = TSession::getValue('checklist_dados_temp') ?? [];
            $this->salvarCabecalhoCompleto($doc, $dados);
            $this->salvarRespostas($doc, $dados);
            TTransaction::close();
            TSession::delValue('checklist_dados_temp');
            TSession::delValue('checklist_pagina_atual');
            TSession::delValue('doc_rascunho_atual');
            new TMessage('info', 'Progresso salvo com sucesso!');
            AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');
        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onConfirmSave($param)
    {
        $action = new TAction([$this, 'onSave']);
        $action->setParameters($param);
        new TQuestion('Deseja finalizar a auditoria e salvar todas as respostas?', $action);
    }

    public function onSave($param)
    {
        try {
            $this->salvarDadosTemporarios($param);
            TTransaction::open('auditoria');
            $doc = TSession::getValue('doc_rascunho_atual');
            if (!$doc) {
                $doc = $this->gerarNovoDoc();
            }
            $dados = TSession::getValue('checklist_dados_temp') ?? [];
            $this->salvarCabecalhoCompleto($doc, $dados);
            $this->salvarRespostas($doc, $dados);
            TTransaction::close();
            TSession::delValue('checklist_dados_temp');
            TSession::delValue('doc_rascunho_atual');
            TSession::delValue('checklist_pagina_atual');
            new TMessage('info', "Auditoria nº {$doc} finalizada com sucesso!");
            AdiantiCoreApplication::loadPage('HistoricoList', 'onReload', ['doc' => $doc]);
        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function salvarCabecalhoCompleto($doc, $dados)
    {
        $z = ZCM010::where('ZCM_DOC', '=', $doc)->first() ?? new ZCM010;
        $z->ZCM_DOC = $doc;
        $z->ZCM_FILIAL = TSession::getValue('auditoria_filial');
        $z->ZCM_DATA = date('Ymd');
        $z->ZCM_HORA = date('Hi');
        $z->ZCM_USUARIO = TSession::getValue('username');
        $z->ZCM_OBS = $dados['observacoes_gerais'] ?? ($z->ZCM_OBS ?? '');
        $z->store();
    }

    private function salvarRespostas($doc, $dados)
    {
        $perguntas = ZCJ010::orderBy('ZCJ_ETAPA')->load();
        $scores = $this->buscarScores();

        foreach ($perguntas as $p) {
            $etapa = $p->ZCJ_ETAPA;
            $resp = $dados["resposta_{$etapa}"] ?? null;
            if ($resp === null) continue;

            $z = ZCN010::where('ZCN_DOC', '=', $doc)->where('ZCN_ETAPA', '=', $etapa)->first();
            if (!$z) $z = new ZCN010;

            $z->ZCN_DOC = $doc;
            $z->ZCN_ETAPA = $etapa;
            $z->ZCN_DESCRI = $p->ZCJ_DESCRI;
            $z->ZCN_TIPO = $resp;
            $z->ZCN_OBS = $dados["obs_{$etapa}"] ?? null;
            $z->ZCN_DATA = date('Ymd');
            $z->ZCN_HORA = date('Hi');
            $z->ZCN_USUARIO = TSession::getValue('username');

            $z->ZCN_NAOCO = $resp;

            if (in_array($resp, ['NC', 'P', 'OP'])) {
                $z->ZCN_SCORE = $scores[$etapa] ?? 0;
            } else {
                $z->ZCN_SCORE = 0;
            }

            $z->store();
        }
    }

    private function buscarScores()
    {
        $etapas = ZCL010::where('D_E_L_E_T_', '<>', '*')->load();
        $scores = [];
        foreach ($etapas as $e) {
            $scores[$e->ZCL_ETAPA] = (float)($e->ZCL_SCORE ?? 0);
        }
        return $scores;
    }

    private function gerarNovoDoc()
    {
        $ultimo = ZCM010::orderBy('ZCM_DOC', 'desc')->first();
        return $ultimo ? str_pad(((int)$ultimo->ZCM_DOC) + 1, 6, '0', STR_PAD_LEFT) : '000001';
    }

    private function addNavigationButtons($pagina_atual, $total_paginas, $auditoriaCompleta = false)
    {
        $row_buttons = [];
        $btn_salvar_etapa = new TButton('btn_salvar_etapa');
        $btn_salvar_etapa->setLabel('Salvar e Sair');
        $btn_salvar_etapa->setImage('fa:save');
        $btn_salvar_etapa->class = 'btn btn-success';
        $btn_salvar_etapa->setAction(new TAction([$this, 'onSaveProgress']), 'Salvar e Sair');
        $row_buttons[] = $btn_salvar_etapa;

        if ($pagina_atual == 0) {
            $btn_inicio = new TButton('btn_inicio');
            $btn_inicio->setLabel('Voltar ao Início');
            $btn_inicio->setImage('fa:arrow-left');
            $btn_inicio->class = 'btn btn-danger';
            $btn_inicio->setAction(new TAction(['inicioAuditoriaModal', 'onReload']), 'Voltar');
            $row_buttons[] = $btn_inicio;
        } else {
            $btn_voltar = new TButton('btn_voltar');
            $btn_voltar->setLabel('Voltar');
            $btn_voltar->setImage('fa:arrow-left');
            $btn_voltar->class = 'btn btn-default';
            $action_voltar = new TAction([$this, 'onNavigate']);
            $action_voltar->setParameter('pagina', $pagina_atual - 1);
            $btn_voltar->setAction($action_voltar, 'Voltar');
            $row_buttons[] = $btn_voltar;
        }

        if ($pagina_atual < $total_paginas - 1) {
            if (!$auditoriaCompleta) {
                $btn_proximo = new TButton('btn_proximo');
                $btn_proximo->setLabel('Próxima Etapa');
                $btn_proximo->setImage('fa:arrow-right');
                $btn_proximo->class = 'btn btn-primary';
                $action_proximo = new TAction([$this, 'onNavigate']);
                $action_proximo->setParameter('pagina', $pagina_atual + 1);
                $btn_proximo->setAction($action_proximo, 'Próxima Etapa');
                $row_buttons[] = $btn_proximo;
            }
        } else {
            $btn_salvar = new TButton('btn_salvar');
            $btn_salvar->setLabel('Finalizar Auditoria');
            $btn_salvar->setImage('fa:check');
            $btn_salvar->class = 'btn btn-success';
            $btn_salvar->setAction(new TAction([$this, 'onConfirmSave']), 'Finalizar');
            $row_buttons[] = $btn_salvar;
        }

        $this->form->addFields($row_buttons);
    }

    private function addNumericPagination($pagina_atual, $total_paginas)
    {
        $buttons = [];
        for ($i = 0; $i < $total_paginas; $i++) {
            $btn = new TButton("pag_$i");
            $btn->setLabel((string) ($i + 1));
            $btn->class = ($i == $pagina_atual) ? 'btn btn-primary' : 'btn btn-default';
            $action = new TAction([$this, 'onNavigate']);
            $action->setParameter('pagina', $i);
            $btn->setAction($action, (string) ($i + 1));
            $buttons[] = $btn;
        }
        $this->form->addFields($buttons);
    }

    private function salvarDadosTemporarios($param)
    {
        $dados_temp = TSession::getValue('checklist_dados_temp') ?? [];
        foreach ($param as $key => $value) {
            if (strpos($key, 'resposta_') === 0 || strpos($key, 'obs_') === 0 || $key === 'observacoes_gerais' || $key === 'filial') {
                $dados_temp[$key] = $value;
            }
        }
        TSession::setValue('checklist_dados_temp', $dados_temp);
    }

    private function buscarDadosSalvos()
    {
        $view_mode = TSession::getValue('view_mode') ?? false;
        $data = TSession::getValue('view_auditoria');
        $dados = ['readonly' => $view_mode, 'respostas' => [], 'observacoes' => [], 'obs_gerais' => ''];

        if ($data['doc'] ?? null) {
            $resp = ZCN010::where('ZCN_DOC', '=', $data['doc'])->where('D_E_L_E_T_', '<>', '*')->load();
            foreach ($resp as $r) {
                $dados['respostas'][$r->ZCN_ETAPA] = $r->ZCN_TIPO;
                $dados['observacoes'][$r->ZCN_ETAPA] = $r->ZCN_OBS;
            }
            $cab = ZCM010::where('ZCM_DOC', '=', $data['doc'])->first();
            if ($cab) {
                $dados['obs_gerais'] = $cab->ZCM_OBS;
            }
        }

        return $dados;
    }

    public function onContinuar($param)
    {
        try {
            $doc = $param['doc'] ?? null;
            if (!$doc) {
                throw new Exception('Documento não informado.');
            }

            TTransaction::open('auditoria');

            $zcm = ZCM010::where('ZCM_DOC', '=', $doc)
                         ->where('D_E_L_E_T_', '<>', '*')
                         ->first();

            if (!$zcm) {
                throw new Exception('Auditoria não encontrada.');
            }

            $respostas = ZCN010::where('ZCN_DOC', '=', $doc)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->load();

            $dados_temp = [];
            $ultimaEtapa = 0;

            foreach ($respostas as $resp) {
                $dados_temp["resposta_{$resp->ZCN_ETAPA}"] = $resp->ZCN_NAOCO;
                $dados_temp["obs_{$resp->ZCN_ETAPA}"] = $resp->ZCN_OBS;

                if ($resp->ZCN_ETAPA > $ultimaEtapa) {
                    $ultimaEtapa = $resp->ZCN_ETAPA;
                }
            }

            $dados_temp['observacoes_gerais'] = $zcm->ZCM_OBS ?? '';

            $perguntas_agrupadas = $this->buscarPerguntasAgrupadasPorTipo();
            $tipos = array_keys($perguntas_agrupadas);

            $pagina = 0;

            foreach ($tipos as $i => $tipo) {
                foreach ($perguntas_agrupadas[$tipo] as $p) {
                    if ($p->ZCJ_ETAPA == $ultimaEtapa) {
                        $pagina = $i;
                        break 2;
                    }
                }
            }

            TTransaction::close();

            TSession::setValue('checklist_dados_temp', $dados_temp);
            TSession::setValue('doc_rascunho_atual', $doc);
            TSession::setValue('auditoria_filial', $zcm->ZCM_FILIAL);
            TSession::setValue('checklist_pagina_atual', $pagina);
            TSession::setValue('view_mode', false);

            AdiantiCoreApplication::loadPage(
                'checkListForm',
                'onStart',
                ['filial' => $zcm->ZCM_FILIAL, 'doc' => $doc, 'pagina' => $pagina]
            );

        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }


    public function onOpenCurtain($param)
    {
        $win = TWindow::create('CheckList de Auditoria', 0.9, 0.9);
        $win->removePadding();
        $page = new self();
        $page->onStart($param);
        $win->add($page);
        $win->show();
    }

    private function auditoriaCompleta($perguntas_agrupadas, $dados_salvos, $dados_salvos_temp = [])
    {
        if ($dados_salvos['readonly'] ?? false) {
            return true;
        }

        foreach ($perguntas_agrupadas as $grupo) {
            foreach ($grupo as $p) {
                $etapa = $p->ZCJ_ETAPA;

                $resposta = $dados_salvos['respostas'][$etapa] 
                    ?? ($dados_salvos_temp["resposta_{$etapa}"] ?? '');

                if (trim((string)$resposta) === '') {
                    return false;
                }

                if (in_array($resposta, ['NC','P','OP'])) {
                    $obs = $dados_salvos['observacoes'][$etapa] 
                        ?? ($dados_salvos_temp["obs_{$etapa}"] ?? '');

                    if (trim((string)$obs) === '') {
                        return false;
                    }
                }
            }
        }

        $obs_geral = $dados_salvos['obs_gerais'] ?? ($dados_salavos_temp['observacoes_gerais'] ?? '');

        if (trim((string)$obs_geral) === '') {
            return false;
        }

        return true;
    }
}
