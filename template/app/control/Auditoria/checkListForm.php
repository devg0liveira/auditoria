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

class checkListForm extends TPage
{
    protected $form;

    public function __construct()
    {
        parent::__construct();
        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setFormTitle('CheckList de Auditoria');

        // parent::add($this->form);
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

            // === BUSCA O TIPO (ZCK010) ===
            $tipoObj = ZCK010::where('ZCK_TIPO', '=', $tipo)
                ->where('D_E_L_E_T_', '<>', '*')
                ->first();
            if (!$tipoObj) {
                throw new Exception('Tipo não encontrado.');
            }

            // === BUSCA AS ETAPAS QUE PERTENCEM AO TIPO NA ZCL010 ===
            $etapas_tipo = ZCL010::where('ZCL_TIPO', '=', $tipo)
                ->where('D_E_L_E_T_', '<>', '*')
                ->getIndexedArray('ZCL_ETAPA', 'ZCL_ETAPA');

            if (empty($etapas_tipo)) {
                TTransaction::close();
                throw new Exception("Nenhuma etapa vinculada ao tipo {$tipo} encontrada em ZCL010.");
            }

            // === BUSCA AS PERGUNTAS (ZCJ010) SOMENTE DAS ETAPAS VINCULADAS ===
            $criteria = new TCriteria;
            $criteria->add(new TFilter('D_E_L_E_T_', '<>', '*'));
            $criteria->add(new TFilter('ZCJ_ETAPA', 'IN', array_keys($etapas_tipo)));
            $criteria->setProperty('order', 'ZCJ_ETAPA');

            $repo = new TRepository('ZCJ010');
            $perguntas = $repo->load($criteria);

            $perguntas = is_array($perguntas) ? $perguntas : [];

            if (empty($perguntas)) {
                TTransaction::close();
                throw new Exception("Nenhuma pergunta encontrada para o tipo {$tipo}.");
            }

            TTransaction::close();

            $respostas_salvas = [];

            // === SE ESTIVER EM MODO DE VISUALIZAÇÃO ===
            if ($view_mode && $view_data) {
                $respostas = ZCL010::where('ZCL_TIPO', '=', $tipo)
                    ->where('ZCL_DATA', '=', $view_data['data'])
                    ->where('ZCL_HORA', '=', $view_data['hora'])
                    ->where('ZCL_USUARIO', '=', $view_data['usuario'])
                    ->where('D_E_L_E_T_', '<>', '*')
                    ->load();

                foreach ($respostas as $r) {
                    $respostas_salvas[$r->ZCL_ETAPA] = $r->ZCL_RESPOSTA;
                }
            }

            TTransaction::close();

            // === MONTA O FORMULÁRIO ===
            $this->montarFormulario($tipoObj, $tipo, $perguntas, $respostas_salvas, $view_mode, $view_data);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) TTransaction::rollback();
        }
    }


    private function montarFormulario($tipoObj, $tipo, $perguntas, $respostas_salvas, $readonly = false, $view_data = null)
    {
        $this->form = new BootstrapFormBuilder('form_checklist');
        $this->form->setColumnClasses(2, ['col-sm-8', 'col-sm-4']);

        $titulo = $readonly
            ? "Visualização: {$tipoObj->ZCK_DESCRI} - " . $this->formatarData($view_data['data']) . ' ' . $this->formatarHora($view_data['hora'])
            : "CheckList: {$tipoObj->ZCK_DESCRI}";

        $this->form->setFormTitle($titulo);

        // Hidden
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

            // 🔹 Aqui você puxa o score da ZCL010 (onde ficam as etapas)
            $etapa_info = ZCL010::where('ZCL_ETAPA', '=', $etapa)
                ->where('D_E_L_E_T_', '<>', '*')
                ->first();
            $score = $etapa_info->ZCL_SCORE ?? 0;

            $combo = new TCombo("resposta_{$etapa}");
            $combo->addItems($opcoes);
            $combo->setSize('100%');
            $combo->setValue($respostas_salvas[$etapa] ?? 'C');

            if ($readonly) {
                $combo->setEditable(false);
            }

            // 🔸 Cria o rótulo do score
            $score_label = new TLabel("<b>Score:</b> {$score}");
            $score_label->setFontColor('#666');

            // 🔸 Adiciona ao formulário: pergunta + combo + score
            $this->form->addFields(
                [new TLabel("<b>Etapa {$etapa}:</b> {$desc}")],
                [$combo, $score_label]
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
            if (!$tipo) {
                throw new Exception('Tipo não informado.');
            }

            TTransaction::open('auditoria');

            // === 1️⃣ DADOS BÁSICOS ===
            $data    = date('Ymd');
            $hora    = date('Hi');
            $usuario = TSession::getValue('userid') ?? 'SYSTEM';
            $filial  = $param['filial'] ?? '1';

            /*
            $filial  = $param['filial'] ?? null;
        if (!$filial) {
          throw new Exception('Filial não informada. Selecione uma filial antes de salvar.');
        }

        */
            $obs     = $param['observacao'] ?? '';

            // === 2️⃣ CRIA O REGISTRO PRINCIPAL (ZCM010) ===
            // === 2️⃣ CRIA O REGISTRO PRINCIPAL (ZCM010) ===
            $zcm = new ZCM010;

            // Gera DOC sequencial manualmente (varchar 6)
            $ultimo = ZCM010::orderBy('ZCM_DOC', 'desc')->first();
            $novoDoc = $ultimo ? str_pad(((int) $ultimo->ZCM_DOC) + 1, 6, '0', STR_PAD_LEFT) : '000001';
            $zcm->ZCM_DOC = $novoDoc;

            $zcm->ZCM_FILIAL   = $filial;
            $zcm->ZCM_TIPO     = $tipo;
            $zcm->ZCM_DATA     = $data;
            $zcm->ZCM_HORA     = $hora;
            $zcm->ZCM_USUARIO  = $usuario;
            $zcm->ZCM_OBS      = $obs;
            $zcm->store();

            $documento = $zcm->ZCM_DOC;


            // === 3️⃣ BUSCA AS ETAPAS VINCULADAS AO TIPO (ZCL010) ===
            $etapas_tipo = ZCL010::where('ZCL_TIPO', '=', $tipo)
                ->where('D_E_L_E_T_', '<>', '*')
                ->getIndexedArray('ZCL_ETAPA', 'ZCL_ETAPA');

            if (empty($etapas_tipo)) {
                throw new Exception("Nenhuma etapa vinculada ao tipo {$tipo} encontrada em ZCL010.");
            }

            // === 4️⃣ BUSCA AS PERGUNTAS CORRESPONDENTES (ZCJ010) ===
            $criteria = new TCriteria;
            $criteria->add(new TFilter('D_E_L_E_T_', '<>', '*'));
            $criteria->add(new TFilter('ZCJ_ETAPA', 'IN', array_keys($etapas_tipo)));
            $criteria->setProperty('order', 'ZCJ_ETAPA');

            $repo = new TRepository('ZCJ010');
            $perguntas = $repo->load($criteria);

            // === 5️⃣ SALVA AS RESPOSTAS NA ZCN010 ===
            $salvo = false;

            foreach ($perguntas as $p) {
                $etapa    = $p->ZCJ_ETAPA;
                $pergunta = $p->ZCJ_DESCRI ?? null;
                $resposta = $param["resposta_{$etapa}"] ?? null;

                if ($resposta) {
                    $zcn = new ZCN010;
                    $zcn->ZCN_DOC      = $documento;
                    $zcn->ZCN_ETAPA    = $etapa;
                    $zcn->ZCN_PERGUNTA = $pergunta;
                    $zcn->ZCN_RESPOSTA = $resposta;
                    $zcn->ZCN_DATA     = $data;
                    $zcn->ZCN_HORA     = $hora;
                    $zcn->ZCN_USUARIO  = $usuario;
                    $zcn->store();

                    $salvo = true;
                }
            }

            if (!$salvo) {
                throw new Exception('Nenhuma resposta selecionada.');
            }

            TTransaction::close();

            // ✅ Mostra mensagem com o número do documento
            new TMessage('info', "Auditoria nº {$documento} finalizada com sucesso!");

            // ✅ Redireciona para HistoricoList passando o documento como parâmetro
            TScript::create("
            setTimeout(() => {
                Adianti.currentWindow?.close();
                __adianti_load_page('index.php?class=HistoricoList&doc={$documento}');
            }, 1500);
        ");
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
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
