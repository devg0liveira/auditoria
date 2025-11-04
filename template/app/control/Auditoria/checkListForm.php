<?php

use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Base\TScript;

class checkListForm extends TPage
{
    protected $form;

    public function __construct()
    {
        parent::__construct();
        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setFormTitle('CheckList de Auditoria');
        parent::add($this->form);
    }

    /**
     * Carrega checklist (NOVO ou VISUALIZAÇÃO)
     */
    public function onStart($param)
    {
        try {
            // Verifica se é modo visualização
            $view_mode = TSession::getValue('view_mode') ?? false;
            $key = $param['key'] ?? TSession::getValue('auditoria_key');

            if ($view_mode && $key) {
                $this->onEdit(['key' => $key]);
                return;
            }

            // === MODO CRIAÇÃO ===
            $filial = $param['filial'] ?? TSession::getValue('auditoria_filial');
            $tipo   = $param['tipo']   ?? TSession::getValue('auditoria_tipo');

            if (!$filial || !$tipo) {
                throw new Exception('Filial e tipo são obrigatórios.');
            }

            TTransaction::open('auditoria');

            // === BUSCA TIPO ===
            $tipoObj = ZCK010::where('ZCK_TIPO', '=', $tipo)
                             ->where('D_E_L_E_T_', '<>', '*')
                             ->first();

            if (!$tipoObj) {
                throw new Exception('Tipo de auditoria não encontrado.');
            }

            // === BUSCA PERGUNTAS DO TIPO ===
            $perguntas = ZCJ010::where('ZCJ_DESCRI', '=', $tipo)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->orderBy('ZCJ_ETAPA')
                               ->load();

            if (empty($perguntas)) {
                throw new Exception('Nenhuma pergunta cadastrada para este tipo.');
            }

            TTransaction::close();

            // === MONTA FORMULÁRIO ===
            $this->montarFormulario($tipoObj, $filial, $tipo, $perguntas, []);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) TTransaction::rollback();
        }
    }

    /**
     * Carrega auditoria existente para visualização
     */
    public function onEdit($param)
    {
        try {
            $key = $param['key'] ?? null;
            if (!$key) throw new Exception('ID não informado.');

            TTransaction::open('auditoria');

            // Busca cabeçalho
            $auditoria = ZCK010::find($key);
            if (!$auditoria || $auditoria->D_E_L_E_T_ === '*') {
                throw new Exception('Auditoria não encontrada.');
            }

            $filial = $auditoria->ZCK_FILIAL;
            $tipo   = $auditoria->ZCK_TIPO;
            $doc    = $auditoria->ZCK_DOC;

            // Busca tipo
            $tipoObj = ZCK010::where('ZCK_TIPO', '=', $tipo)
                             ->where('D_E_L_E_T_', '<>', '*')
                             ->first();

            // Busca perguntas
            $perguntas = ZCJ010::where('ZCJ_DESCRI', '=', $tipo)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->orderBy('ZCJ_ETAPA')
                               ->load();

            // Busca respostas salvas (ZCN010)
            $respostas_salvas = [];
            $respostas = ZCN010::where('ZCN_FILIAL', '=', $filial)
                               ->where('ZCN_DOC', '=', $doc)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->load();

            foreach ($respostas as $r) {
                $respostas_salvas[$r->ZCN_ETAPA] = $r->ZCN_NAOCO;
            }

            TTransaction::close();

            // Monta formulário em modo visualização
            $this->montarFormulario($tipoObj, $filial, $tipo, $perguntas, $respostas_salvas, true, $doc);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) TTransaction::rollback();
        }
    }

    /**
     * Monta o formulário dinamicamente
     */
    private function montarFormulario($tipoObj, $filial, $tipo, $perguntas, $respostas_salvas, $readonly = false, $doc = null)
    {
        $this->form = new BootstrapFormBuilder('form_checklist');
        
        $titulo = $readonly 
            ? "Visualização: {$tipoObj->ZCK_DESCRI} - Doc: {$doc}" 
            : "CheckList: {$tipoObj->ZCK_DESCRI} - Filial: {$filial}";
        
        $this->form->setFormTitle($titulo);
        $this->form->setColumnClasses(2, ['col-sm-8', 'col-sm-4']);

        // Campos hidden
        $hidden_filial = new THidden('filial');
        $hidden_tipo = new THidden('tipo');
        $this->form->addFields([$hidden_filial, $hidden_tipo]);
        $this->form->setData((object)['filial' => $filial, 'tipo' => $tipo]);

        // Opções do combo
        $opcoes = [
            'C'  => 'Conforme',
            'NC' => 'Não Conforme',
            'OP' => 'Oportunidade de melhoria',
            'P'  => 'Parcialmente',
            'N'  => 'Não Aplicável'
        ];

        // Renderiza perguntas
        foreach ($perguntas as $p) {
            $etapa = $p->ZCJ_ETAPA;
            $desc  = $p->ZCJ_DESCRI;

            $combo = new TCombo("resposta_{$etapa}");
            $combo->addItems($opcoes);
            $combo->setSize('100%');
            
            // Define valor (salvo ou padrão)
            $valor = $respostas_salvas[$etapa] ?? 'C';
            $combo->setValue($valor);

            if ($readonly) {
                $combo->setEditable(false);
            }

            $this->form->addFields(
                [new TLabel("<b>Etapa {$etapa}:</b> {$desc}")],
                [$combo]
            );
        }

        // Botão salvar (apenas se não for readonly)
        if (!$readonly) {
            $btn = new TButton('salvar');
            $btn->setLabel('Finalizar Auditoria');
            $btn->setImage('fa:check green');
            $btn->setAction(new \Adianti\Control\TAction([$this, 'onSave']));
            $this->form->addFields([], [$btn]);
        }

        parent::add($this->form);
    }

    /**
     * Salva auditoria completa
     */
    public static function onSave($param)
    {
        try {
            $filial = $param['filial'] ?? null;
            $tipo   = $param['tipo'] ?? null;

            if (!$filial || !$tipo) {
                throw new Exception('Dados inválidos.');
            }

            TTransaction::open('auditoria');

            // === 1. CRIA CABEÇALHO (ZCK010) ===
            $zck = new ZCK010;
            
            // Gera número do documento
            $conn = TTransaction::get();
            $result = $conn->query("
                SELECT MAX(CAST(ZCK_DOC AS INT)) AS max_doc 
                FROM ZCK010 
                WHERE ZCK_FILIAL = '{$filial}' 
                  AND ISNUMERIC(ZCK_DOC) = 1
            ");
            $row = $result->fetch(PDO::FETCH_ASSOC);
            $max_doc = $row['max_doc'] ?? 0;
            $novo_doc = str_pad($max_doc + 1, 6, '0', STR_PAD_LEFT);

            $zck->ZCK_FILIAL = $filial;
            $zck->ZCK_TIPO   = $tipo;
            $zck->ZCK_DOC    = $novo_doc;
            $zck->ZCK_DATA   = date('Ymd');
            $zck->ZCK_HORA   = date('His');
            $zck->ZCK_USUGIR = TSession::getValue('userid') ?? 'SYSTEM';
            
            // Busca descrição do tipo
            $tipoObj = ZCK010::where('ZCK_TIPO', '=', $tipo)
                             ->where('D_E_L_E_T_', '<>', '*')
                             ->first();
            $zck->ZCK_DESCRI = $tipoObj ? $tipoObj->ZCK_DESCRI : 'N/A';
            
            $zck->store();

            // === 2. SALVA RESPOSTAS (ZCN010) ===
            $perguntas = ZCJ010::where('ZCJ_DESCRI', '=', $tipo)
                               ->where('D_E_L_E_T_', '<>', '*')
                               ->load();

            $total_perguntas = 0;
            $total_respondido = 0;

            foreach ($perguntas as $p) {
                $etapa = $p->ZCJ_ETAPA;
                $resposta = $param["resposta_{$etapa}"] ?? null;

                if ($resposta) {
                    $zcn = new ZCN010;
                    $zcn->ZCN_FILIAL = $filial;
                    $zcn->ZCN_DOC    = $novo_doc;
                    $zcn->ZCN_ETAPA  = $etapa;
                    $zcn->ZCN_NAOCO  = $resposta;
                    $zcn->ZCN_DATA   = date('Ymd');
                    $zcn->ZCN_HORA   = date('His');
                    $zcn->ZCN_SCORE  = ($resposta === 'C') ? 100 : 0; // Simplificado
                    $zcn->store();

                    $total_perguntas++;
                    if ($resposta !== 'N') {
                        $total_respondido++;
                    }
                }
            }

            TTransaction::close();

            if ($total_perguntas === 0) {
                throw new Exception('Nenhuma resposta foi selecionada.');
            }

            new TMessage('info', "Auditoria finalizada com sucesso!<br>Documento: {$novo_doc}");

            // Fecha modal e recarrega lista
            TScript::create("
                setTimeout(() => {
                    Adianti.currentWindow?.close();
                    __adianti_load_page('index.php?class=HistoricoList');
                }, 2000);
            ");

        } catch (Exception $e) {
            if (TTransaction::get()) TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    /**
     * Abre em modal
     */
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