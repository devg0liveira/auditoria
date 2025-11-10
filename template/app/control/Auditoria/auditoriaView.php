
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Wrapper\TQuickGrid;

class auditoriaView extends TPage
{
    public function onReload($param)
    {
        try {
            $doc = $param['key'] ?? null;
            if (!$doc) {
                throw new Exception('Documento não informado.');
            }

            TTransaction::open('auditoria');

            // 1️⃣ Cabeçalho
            $cabecalho = new ZCM010($doc);

            // 2️⃣ Detalhes
            $repo = new TRepository('ZCN010');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('ZCN_DOC', '=', $doc));
            $respostas = $repo->load($criteria);

            TTransaction::close();

            // 3️⃣ Exibir
            $this->showData($cabecalho, $respostas);

        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }
    }

    private function showData($cabecalho, $respostas)
    {
        $panel = new TPanelGroup("Visualização da Auditoria {$cabecalho->ZCM_DOC}");
        $vbox  = new TVBox;
        $vbox->style = 'width: 100%';

        $vbox->add(new TLabel("Filial: {$cabecalho->ZCM_FILIAL}"));
        $vbox->add(new TLabel("Tipo: {$cabecalho->ZCM_TIPO}"));
        $vbox->add(new TLabel("Data/Hora: {$cabecalho->ZCM_DATA} {$cabecalho->ZCM_HORA}"));
        $vbox->add(new TLabel("Usuário: {$cabecalho->ZCM_USER}"));
        $vbox->add(new TLabel("Observação: {$cabecalho->ZCM_OBS}"));

        $grid = new TQuickGrid;
        $grid->addQuickColumn('Etapa', 'ZCN_ETAPA', 'center');
        $grid->addQuickColumn('Resposta', 'ZCN_RESPOSTA', 'center');
        $grid->addQuickColumn('Observação', 'ZCN_OBS', 'left');
        $grid->createModel();
        $grid->addItems($respostas);

        $vbox->add($grid);
        $panel->add($vbox);

        parent::add($panel);
    }
}





document.querySelector('#resposta_{$etapa}').addEventListener('change', function() {
                        var obsField = document.querySelector('#obs_{$etapa}').closest('.form-group');
                        if (this.value !== 'C') {
                            obsField.style.display = 'block';
                        } else {
                            obsField.style.display = 'none';
                        }
                    });
                    // Inicial
                    var initialValue = document.querySelector('#resposta_{$etapa}').value;
                    var obsFieldInit = document.querySelector('#obs_{$etapa}').closest('.form-group');
                    if (initialValue !== 'C') {
                        obsFieldInit.style.display = 'block';
                    } else {
                        obsFieldInit.style.display = 'none';
                    }
                ";
            } else {
                // Em view, mostrar sempre se há valor
                if (!empty($obs_salvas[$etapa])) {
                    $js_scripts[] = "
                        document.querySelector('#obs_{$etapa}').closest('.form-group').style.display = 'block';
                    ";
                } else {
                    $js_scripts[] = "
                        document.querySelector('#obs_{$etapa}').closest('.form-group').style.display = 'none';
                    ";
                }
            }
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

        // Adiciona o JS ao final
        if (!empty($js_scripts)) {
            TScript::create(implode("\n", $js_scripts));
        }
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

            $obs     = $param['observacao'] ?? ''; // Obs global, se existir

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
                $obs_etapa = $param["obs_{$etapa}"] ?? ''; // Observação por etapa

                if ($resposta) {
                    $zcn = new ZCN010;
                    $zcn->ZCN_DOC      = $documento;
                    $zcn->ZCN_ETAPA    = $etapa;
                    $zcn->ZCN_PERGUNTA = $pergunta;
                    $zcn->ZCN_RESPOSTA = $resposta;
                    $zcn->ZCN_OBS      = $obs_etapa; // Salva a obs
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
