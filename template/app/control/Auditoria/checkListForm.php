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
            if (!$filial) throw new Exception('Filial não informada.');

            // Armazena a filial na sessão
            TSession::setValue('auditoria_filial', $filial);

            // Página atual (começa em 0)
            $pagina_atual = isset($param['pagina']) ? (int)$param['pagina'] : 0;
            TSession::setValue('checklist_pagina_atual', $pagina_atual);

            TTransaction::open('auditoria');

            $perguntas_agrupadas = $this->buscarPerguntasAgrupadasPorTipo();
            if (empty($perguntas_agrupadas)) throw new Exception('Nenhuma pergunta encontrada.');

            $dados_salvos = $this->buscarDadosSalvos();

            TTransaction::close();

            $this->montarFormulario($filial, $perguntas_agrupadas, $dados_salvos, $pagina_atual);
        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
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

        $pergs = ZCJ010::where('ZCJ_ETAPA', 'IN', $ids)
            ->where('D_E_L_E_T_', '<>', '*')
            ->orderBy('ZCJ_ETAPA')
            ->load();

        $tipos = ZCK010::where('D_E_L_E_T_', '<>', '*')->load();
        $descricao_tipos = [];
        foreach ($tipos as $t) {
            $descricao_tipos[$t->ZCK_TIPO] = $t->ZCK_DESCRI;
        }

        // Agrupa perguntas por tipo
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
        $this->form->addFields([$hidden_filial]);
        $this->form->setData((object)['filial' => $filial]);

        if ($pagina_atual < count($tipos)) {
            $tipo_atual = $tipos[$pagina_atual];
            $perguntas = $perguntas_agrupadas[$tipo_atual];

            foreach ($perguntas as $p) {
                $this->renderPergunta($p, $dados_salvos);
            }
        } else {
            $this->renderObsGerais($dados_salvos);
        }

        if (!($dados_salvos['readonly'] ?? false)) {
            $this->addNumericPagination($pagina_atual, $total_paginas);
            $this->addNavigationButtons($pagina_atual, $total_paginas);
        }

        parent::add($this->form);
    }


    private function buscarDadosSalvos()
    {
        $view = TSession::getValue('view_mode') ?? false;
        $data = TSession::getValue('view_auditoria');

        $dados = ['readonly' => $view, 'respostas' => [], 'observacoes' => [], 'obs_gerais' => ''];

        if ($view && $data) {
            $resp = ZCN010::where('ZCN_DOC', '=', $data['doc'])
                ->where('D_E_L_E_T_', '<>', '*')
                ->load();

            foreach ($resp as $r) {
                $dados['respostas'][$r->ZCN_ETAPA] = $r->ZCN_TIPO;
                $dados['observacoes'][$r->ZCN_ETAPA] = $r->ZCN_OBS;
            }

            $cab = ZCM010::where('ZCM_DOC', '=', $data['doc'])
                ->where('D_E_L_E_T_', '<>', '*')
                ->first();

            if ($cab) $dados['obs_gerais'] = $cab->ZCM_OBS;
        }

        return $dados;
    }

    private function renderPergunta($p, $dados)
    {
        $etapa = $p->ZCJ_ETAPA;

        $combo = new TCombo("resposta_{$etapa}");
        $combo->addItems($this->opcoes_resposta);
        $combo->setValue($dados['respostas'][$etapa] ?? '');
        $combo->addValidation("Etapa {$etapa}", new TRequiredValidator);
        if ($dados['readonly']) $combo->setEditable(false);

        $obs = new TText("obs_{$etapa}");
        $obs->setValue($dados['observacoes'][$etapa] ?? '');
        $obs->addValidation("Observações {$etapa}", new TRequiredValidator);
        if ($dados['readonly']) $obs->setEditable(false);

        $this->form->addFields([new TLabel("<b>{$p->ZCJ_DESCRI}</b>")], [$combo]);
        $this->form->addFields([new TLabel('Problemas Encontrados:')], [$obs]);
    }

    private function renderObsGerais($dados)
    {
        $obs = new TText('observacoes_gerais');
        $obs->setValue($dados['obs_gerais'] ?? '');
        if ($dados['readonly']) $obs->setEditable(false);

        $this->form->addFields([new TLabel("<b>Observações Gerais da Auditoria:</b>")], [$obs]);
    }

    private function addNavigationButtons($pagina_atual, $total_paginas)
    {
        $row_buttons = [];

        if ($pagina_atual == 0) {
            $btn_inicio = new TButton('btn_inicio');
            $btn_inicio->setLabel('Voltar ao Início');
            $btn_inicio->setImage('fa:arrow-left');
            $btn_inicio->class = 'btn btn-danger';

            $action_inicio = new TAction(['inicioAuditoriaModal', 'onReload']);
            $btn_inicio->setAction($action_inicio, 'Voltar');

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
            $btn_proximo = new TButton('btn_proximo');
            $btn_proximo->setLabel('Próxima Etapa');
            $btn_proximo->setImage('fa:arrow-right');
            $btn_proximo->class = 'btn btn-primary';

            $action_proximo = new TAction([$this, 'onNavigate']);
            $action_proximo->setParameter('pagina', $pagina_atual + 1);
            $btn_proximo->setAction($action_proximo, 'Próxima Etapa');

            $row_buttons[] = $btn_proximo;
        } else {
            $btn_salvar = new TButton('btn_salvar');
            $btn_salvar->setLabel('Finalizar Auditoria');
            $btn_salvar->setImage('fa:check');
            $btn_salvar->class = 'btn btn-success';
            $btn_salvar->setAction(new TAction([$this, 'onSave']), 'Finalizar');

            $row_buttons[] = $btn_salvar;
        }

        $this->form->addFields($row_buttons);
    }


    public function onNavigate($param)
    {
        try {
            // Salva os dados do formulário atual na sessão antes de navegar
            $this->salvarDadosTemporarios($param);

            // Navega para a página solicitada
            $param['filial'] = TSession::getValue('auditoria_filial');
            $this->onStart($param);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    private function addNumericPagination($pagina_atual, $total_paginas)
    {
        $buttons = [];

        for ($i = 0; $i < $total_paginas; $i++) {
            $btn = new TButton("pag_$i");
            $btn->setLabel((string) ($i + 1));

            if ($i == $pagina_atual) {
                $btn->class = 'btn btn-primary';
            } else {
                $btn->class = 'btn btn-default';
            }

            $action = new TAction([$this, 'onNavigate']);
            $action->setParameter('pagina', $i);
            $btn->setAction($action, (string) ($i + 1));
            $buttons[] = $btn;
        }

        $this->form->addFields($buttons);
    }

    public function onCloseWindow($param)
    {
        TWindow::closeWindow($param['__WINDOW_ID__']);
    }


    private function salvarDadosTemporarios($param)
    {
        // Armazena os dados do formulário na sessão para não perder ao navegar
        $dados_temp = TSession::getValue('checklist_dados_temp') ?? [];

        foreach ($param as $key => $value) {
            if (strpos($key, 'resposta_') === 0 || strpos($key, 'obs_') === 0 || $key === 'observacoes_gerais') {
                $dados_temp[$key] = $value;
            }
        }

        TSession::setValue('checklist_dados_temp', $dados_temp);
    }

    public function onSave($param)
    {
        try {
            // Combina dados temporários com dados atuais
            $dados_temp = TSession::getValue('checklist_dados_temp') ?? [];
            $param = array_merge($dados_temp, $param);

            TTransaction::open('auditoria');

            $doc = $this->gerarNovoDoc();
            $this->salvarCabecalho($doc, $param);
            $scores = $this->buscarScores();
            $this->salvarRespostas($doc, $param, $scores);

            TTransaction::close();

            // Limpa dados temporários
            TSession::delValue('checklist_dados_temp');
            TSession::delValue('checklist_pagina_atual');

            new TMessage('info', "Auditoria nº $doc finalizada com sucesso!");
            AdiantiCoreApplication::loadPage('HistoricoList', 'onReload', ['doc' => $doc]);
        } catch (Exception $e) {
            TTransaction::rollbackAll();
            new TMessage('error', $e->getMessage());
        }
    }

    private function gerarNovoDoc()
    {
        $ultimo = ZCM010::orderBy('ZCM_DOC', 'desc')->first();
        return $ultimo ? str_pad(((int) $ultimo->ZCM_DOC) + 1, 6, '0', STR_PAD_LEFT) : '000001';
    }

    private function salvarCabecalho($doc, $p)
    {
        $z = new ZCM010;
        $z->ZCM_DOC     = $doc;
        $z->ZCM_FILIAL  = $p['filial'];
        $z->ZCM_DATA    = date('Ymd');
        $z->ZCM_HORA    = date('Hi');
        $z->ZCM_USUARIO = TSession::getValue('username');
        $z->ZCM_OBS     = $p['observacoes_gerais'] ?? null;
        $z->store();
    }

    private function buscarScores()
    {
        $etapas = ZCL010::where('D_E_L_E_T_', '<>', '*')->load();
        $scores = [];
        foreach ($etapas as $e) {
            $scores[$e->ZCL_ETAPA] = (float)$e->ZCL_SCORE;
        }
        return $scores;
    }

    private function salvarRespostas($doc, $param, $scores)
    {
        $perguntas = ZCJ010::orderBy('ZCJ_ETAPA')->load();
        $data = date('Ymd');
        $hora = date('Hi');
        $usuario = TSession::getValue('username');

        foreach ($perguntas as $p) {
            $etapa = $p->ZCJ_ETAPA;
            $resp = $param["resposta_{$etapa}"] ?? null;
            if (!$resp) continue;

            $z = new ZCN010;
            $z->ZCN_DOC = $doc;
            $z->ZCN_ETAPA = $etapa;
            $z->ZCN_DESCRI = $p->ZCJ_DESCRI;
            $z->ZCN_TIPO = $resp;
            $z->ZCN_OBS = $param["obs_{$etapa}"] ?? null;
            $z->ZCN_DATA = $data;
            $z->ZCN_HORA = $hora;
            $z->ZCN_USUARIO = $usuario;

            if (in_array($resp, ['NC', 'P', 'OP'])) {
                $z->ZCN_NAOCO = $resp;
                $z->ZCN_SCORE = $scores[$etapa] ?? 0;
            }

            $z->store();
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
}
