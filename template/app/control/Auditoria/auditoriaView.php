
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
