<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Database\TTransaction;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Validator\TMinLengthValidator;
use Adianti\Validator\TRequiredValidator;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Base\TScript;

class IniciativaForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();
        
        $this->form = new BootstrapFormBuilder('form_iniciativa');
        $this->form->setFormTitle('📋 Plano de Ação - Iniciativas de Melhoria');
        
        // Estilo responsivo
        $this->form->style = '
            padding: 20px; 
            max-width: 1200px; 
            margin: 0 auto; 
            background: #fff; 
            border-radius: 10px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        ';
        
        // CSS responsivo adicional
        TScript::create("
            <style>
                @media (max-width: 768px) {
                    #form_iniciativa {
                        padding: 10px !important;
                    }
                    
                    .nc-card {
                        margin: 10px 0 !important;
                        padding: 15px !important;
                    }
                    
                    .nc-header {
                        font-size: 14px !important;
                    }
                    
                    .form-group {
                        margin-bottom: 10px !important;
                    }
                    
                    textarea, input[type='text'], input[type='date'], select {
                        font-size: 14px !important;
                    }
                }
                
                .nc-card {
                    background: #f8f9fa;
                    border-left: 4px solid #007bff;
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                    transition: all 0.3s ease;
                }
                
                .nc-card:hover {
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                    transform: translateY(-2px);
                }
                
                .nc-card.nc-type-NC {
                    border-left-color: #dc3545;
                }
                
                .nc-card.nc-type-P {
                    border-left-color: #fd7e14;
                }
                
                .nc-card.nc-type-OP {
                    border-left-color: #ffc107;
                }
                
                .nc-header {
                    font-size: 16px;
                    font-weight: bold;
                    color: #003366;
                    margin-bottom: 10px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #e0e0e0;
                }
                
                .nc-tipo-badge {
                    display: inline-block;
                    padding: 5px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: bold;
                    margin-top: 5px;
                }
                
                .nc-tipo-NC {
                    background: #dc3545;
                    color: white;
                }
                
                .nc-tipo-P {
                    background: #fd7e14;
                    color: white;
                }
                
                .nc-tipo-OP {
                    background: #ffc107;
                    color: #333;
                }
                
                .status-badge {
                    display: inline-block;
                    padding: 3px 10px;
                    border-radius: 15px;
                    font-size: 11px;
                    font-weight: bold;
                }
                
                .status-A {
                    background: #17a2b8;
                    color: white;
                }
                
                .status-C {
                    background: #28a745;
                    color: white;
                }
                
                .campo-obrigatorio {
                    color: #dc3545;
                    font-weight: bold;
                }
                
                .campo-info {
                    font-size: 12px;
                    color: #6c757d;
                    font-style: italic;
                    margin-top: 3px;
                }
            </style>
        ");
        
        $this->form->addHeaderAction('Voltar', new TAction(['HistoricoList', 'onReload']), 'fa:arrow-left blue');
        $this->form->addHeaderAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
    }

    public function onEdit(array $param)
    {
        try {
            $doc = $param['doc'] ?? null;
            if (!$doc || trim($doc) === '') {
                throw new Exception('Documento da auditoria não informado.');
            }

            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            // Buscar não conformidades com informações completas
            $sql = "
                SELECT 
                    cn.ZCN_ETAPA,
                    ISNULL(cn.ZCN_SEQ, '001') AS ZCN_SEQ,
                    ISNULL(cj.ZCJ_DESCRI, 'Sem descrição') AS ZCJ_DESCRI,
                    cn.ZCN_NAOCO,
                    ISNULL(cn.ZCN_ACAO, '') AS ZCN_ACAO,
                    ISNULL(cn.ZCN_RESP, '') AS ZCN_RESP,
                    cn.ZCN_PRAZO,
                    cn.ZCN_DATA_EXEC,
                    ISNULL(cn.ZCN_STATUS, 'A') AS ZCN_STATUS,
                    ISNULL(cn.ZCN_OBS, '') AS ZCN_OBS,
                    ISNULL(cl.ZCL_SCORE, 0) AS SCORE_PERDIDO
                FROM ZCN010 cn
                INNER JOIN ZCJ010 cj 
                    ON cj.ZCJ_ETAPA = cn.ZCN_ETAPA 
                    AND cj.D_E_L_E_T_ <> '*'
                LEFT JOIN ZCL010 cl 
                    ON cl.ZCL_ETAPA = cn.ZCN_ETAPA 
                    AND cl.D_E_L_E_T_ <> '*'
                WHERE cn.ZCN_DOC = :doc 
                  AND cn.ZCN_NAOCO IN ('NC', 'P', 'OP')
                  AND cn.D_E_L_E_T_ <> '*'
                ORDER BY cn.ZCN_ETAPA, cn.ZCN_SEQ
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':doc' => $doc]);
            $ncs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($ncs)) {
                TTransaction::close();
                new TMessage('info', 'Nenhuma não conformidade encontrada para plano de ação.');
                AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');
                return;
            }

            // Verificar se há planos concluídos
            $tem_concluido = false;
            foreach ($ncs as $nc) {
                if ($nc['ZCN_STATUS'] === 'C') {
                    $tem_concluido = true;
                    break;
                }
            }

            $this->form->clear();
            
            // Cabeçalho com informações
            $header_html = "
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                            padding: 20px; 
                            border-radius: 8px; 
                            margin-bottom: 20px; 
                            color: white;
                            text-align: center;'>
                    <h3 style='margin: 0; font-size: 24px;'>
                        📄 Auditoria: <b>{$doc}</b>
                    </h3>
                    <p style='margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;'>
                        Total de não conformidades: <b>" . count($ncs) . "</b>
                    </p>
            ";
            
            if ($tem_concluido) {
                $header_html .= "
                    <div style='background: rgba(255,255,255,0.2); 
                                padding: 10px; 
                                border-radius: 5px; 
                                margin-top: 10px;'>
                        ⚠️ Este plano contém itens já concluídos
                    </div>
                ";
            }
            
            $header_html .= "</div>";
            
            $this->form->addContent([$header_html]);

            // Campo hidden com o documento
            $hiddenDoc = new THidden('doc');
            $hiddenDoc->setValue($doc);
            $this->form->addFields([$hiddenDoc]);

            // Renderizar cada não conformidade
            $contador = 1;
            foreach ($ncs as $nc) {
                $this->renderNaoConformidade($nc, $contador, $tem_concluido);
                $contador++;
            }

            // Resumo final
            $total_pendentes = 0;
            $total_concluidos = 0;
            foreach ($ncs as $nc) {
                if ($nc['ZCN_STATUS'] === 'C') {
                    $total_concluidos++;
                } else {
                    $total_pendentes++;
                }
            }

            $resumo_html = "
                <div style='background: #e9ecef; 
                            padding: 15px; 
                            border-radius: 8px; 
                            margin-top: 20px;'>
                    <h4 style='margin: 0 0 10px 0; color: #495057;'>📊 Resumo do Plano</h4>
                    <div style='display: flex; gap: 20px; flex-wrap: wrap;'>
                        <div style='flex: 1; min-width: 150px;'>
                            <strong>Total:</strong> " . count($ncs) . " itens
                        </div>
                        <div style='flex: 1; min-width: 150px;'>
                            <strong style='color: #17a2b8;'>Em Andamento:</strong> {$total_pendentes}
                        </div>
                        <div style='flex: 1; min-width: 150px;'>
                            <strong style='color: #28a745;'>Concluídos:</strong> {$total_concluidos}
                        </div>
                    </div>
                </div>
            ";
            
            $this->form->addContent([$resumo_html]);

            TTransaction::close();
            parent::add($this->form);
            
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar: ' . $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    private function renderNaoConformidade($nc, $numero, $readonly = false)
    {
        $etapa = trim($nc['ZCN_ETAPA']);
        $seq   = trim($nc['ZCN_SEQ']);
        $key   = "{$etapa}_{$seq}";
        $tipo  = $nc['ZCN_NAOCO'];
        $status = $nc['ZCN_STATUS'];
        $score = $nc['SCORE_PERDIDO'] ?? 0;

        // Determinar nomes dos tipos
        $tipo_nome = match($tipo) {
            'NC' => 'Não Conformidade',
            'P'  => 'Parcial',
            'OP' => 'Oportunidade de Melhoria',
            default => $tipo
        };

        // Card da NC
        $card_inicio = "
            <div class='nc-card nc-type-{$tipo}' id='nc-card-{$key}'>
                <div class='nc-header'>
                    <span style='color: #666;'>#{$numero}</span> 
                    Etapa {$etapa} - " . htmlspecialchars($nc['ZCJ_DESCRI']) . "
                </div>
                <div style='margin-bottom: 15px;'>
                    <span class='nc-tipo-badge nc-tipo-{$tipo}'>{$tipo_nome}</span>
        ";
        
        if ($status === 'C') {
            $card_inicio .= " <span class='status-badge status-C'>✓ Concluído</span>";
        } else {
            $card_inicio .= " <span class='status-badge status-A'>⏳ Em Andamento</span>";
        }
        
        if ($score > 0) {
            $card_inicio .= " <span style='background: #dc3545; color: white; padding: 3px 10px; border-radius: 15px; font-size: 11px; margin-left: 5px;'>
                               -{$score} pontos
                             </span>";
        }
        
        $card_inicio .= "
                </div>
        ";
        
        $this->form->addContent([$card_inicio]);

        // Campos do formulário
        $acao = new TText("acao_{$key}");
        $acao->setSize('100%', 100);
        $acao->placeholder = 'Descreva a ação corretiva a ser implementada...';
        if (!$readonly) {
            $acao->addValidation('Ação corretiva', new TRequiredValidator);
            $acao->addValidation('Ação corretiva', new TMinLengthValidator, [10]);
        }
        $acao->setValue($nc['ZCN_ACAO']);
        $acao->setEditable(!$readonly && $status !== 'C');

        $resp = new TEntry("resp_{$key}");
        $resp->setSize('100%');
        $resp->placeholder = 'Nome do responsável';
        if (!$readonly) {
            $resp->addValidation('Responsável', new TRequiredValidator);
            $resp->addValidation('Responsável', new TMinLengthValidator, [3]);
        }
        $resp->setValue($nc['ZCN_RESP']);
        $resp->setEditable(!$readonly && $status !== 'C');
        
        $prazo = new TDate("prazo_{$key}");
        $prazo->setMask('dd/mm/yyyy');
        $prazo->setSize('100%');
        if (!$readonly) {
            $prazo->addValidation('Prazo', new TRequiredValidator);
        }
        $prazo->setValue($this->formatDate($nc['ZCN_PRAZO']));
        $prazo->setEditable(!$readonly && $status !== 'C');

        $exec = new TDate("exec_{$key}");
        $exec->setMask('dd/mm/yyyy');
        $exec->setSize('100%');
        $exec->setValue($this->formatDate($nc['ZCN_DATA_EXEC']));
        $exec->setEditable(!$readonly && $status !== 'C');

        $status_combo = new TCombo("status_{$key}");
        $status_combo->addItems([
            'A' => '⏳ Em Andamento', 
            'C' => '✓ Concluído'
        ]);
        $status_combo->setSize('100%');
        $status_combo->setValue($nc['ZCN_STATUS']);
        $status_combo->setEditable(!$readonly && $status !== 'C');

        $obs = new TText("obs_{$key}");
        $obs->setSize('100%', 80);
        $obs->setValue($nc['ZCN_OBS']);
        $obs->setEditable(false);

        // Labels com ícones
        $label_acao = new TLabel('🎯 Ação Corretiva <span class="campo-obrigatorio">*</span>');
        $label_resp = new TLabel('👤 Responsável <span class="campo-obrigatorio">*</span>');
        $label_prazo = new TLabel('📅 Prazo <span class="campo-obrigatorio">*</span>');
        $label_exec = new TLabel('✓ Data de Execução');
        $label_status = new TLabel('📊 Status');
        $label_obs = new TLabel('📝 Observações do Auditor');

        // Adicionar campos ao formulário
        $this->form->addFields([$label_acao], [$acao]);
        
        // Info adicional para ação
        $this->form->addContent([
            "<div class='campo-info' style='margin-top: -10px; margin-bottom: 10px;'>
                Mínimo de 10 caracteres. Seja específico e detalhado.
            </div>"
        ]);
        
        $this->form->addFields([$label_resp], [$resp], [$label_prazo], [$prazo]);
        $this->form->addFields([$label_exec], [$exec], [$label_status], [$status_combo]);
        $this->form->addFields([$label_obs], [$obs]);

        // Fechar card
        $this->form->addContent(["</div>"]);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('auditoria');
            $conn = TTransaction::get();

            $doc = $param['doc'] ?? null;

            if (!$doc) {
                throw new Exception('Documento não informado.');
            }

            // Verificar se já está concluído
            $stmt = $conn->prepare("
                SELECT ZCN_STATUS, ZCN_ETAPA 
                FROM ZCN010 
                WHERE ZCN_DOC = ? 
                  AND ZCN_NAOCO IN ('NC', 'P', 'OP')
                  AND D_E_L_E_T_ <> '*'
            ");
            $stmt->execute([$doc]);
            $ncs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_concluidos = 0;
            foreach ($ncs as $nc) {
                if ($nc['ZCN_STATUS'] == 'C') {
                    $total_concluidos++;
                }
            }

            // Se todos já estão concluídos, bloquear
            if ($total_concluidos === count($ncs) && count($ncs) > 0) {
                new TMessage('warning', 'Todos os itens do plano de ação já estão concluídos.');
                TTransaction::close();
                AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');
                return;
            }

            $erros = [];
            $salvos = 0;

            // Processar cada ação
            foreach ($param as $key => $value) {
                if (strpos($key, 'acao_') === 0) {
                    $parts = explode('_', $key);
                    $etapa = $parts[1] ?? null;
                    $seq   = $parts[2] ?? '001';

                    if (!$etapa) continue;

                    // Buscar o registro
                    $zcn = ZCN010::where('ZCN_DOC', '=', $doc)
                        ->where('ZCN_ETAPA', '=', $etapa)
                        ->where('ZCN_SEQ', '=', $seq)
                        ->first();

                    if (!$zcn) continue;

                    // Se já está concluído, pular
                    if ($zcn->ZCN_STATUS === 'C') {
                        continue;
                    }

                    // Validar campos
                    $acao = trim($param["acao_{$etapa}_{$seq}"] ?? '');
                    $resp = trim($param["resp_{$etapa}_{$seq}"] ?? '');
                    $prazo = trim($param["prazo_{$etapa}_{$seq}"] ?? '');

                    if (empty($acao)) {
                        $erros[] = "Etapa {$etapa}: Ação corretiva é obrigatória.";
                        continue;
                    }
                    
                    if (strlen($acao) < 10) {
                        $erros[] = "Etapa {$etapa}: Ação corretiva deve ter no mínimo 10 caracteres.";
                        continue;
                    }

                    if (empty($resp)) {
                        $erros[] = "Etapa {$etapa}: Responsável é obrigatório.";
                        continue;
                    }
                    
                    if (strlen($resp) < 3) {
                        $erros[] = "Etapa {$etapa}: Responsável deve ter no mínimo 3 caracteres.";
                        continue;
                    }

                    if (empty($prazo)) {
                        $erros[] = "Etapa {$etapa}: Prazo é obrigatório.";
                        continue;
                    }

                    // Atualizar registro
                    $zcn->ZCN_ACAO      = $acao;
                    $zcn->ZCN_RESP      = $resp;
                    $zcn->ZCN_PRAZO     = self::toDbDate($prazo);
                    $zcn->ZCN_DATA_EXEC = self::toDbDate(trim($param["exec_{$etapa}_{$seq}"] ?? ''));
                    $zcn->ZCN_STATUS    = $param["status_{$etapa}_{$seq}"] ?? 'A';
                    $zcn->store();
                    
                    $salvos++;
                }
            }

            if (!empty($erros)) {
                TTransaction::rollback();
                $msg_erro = "❌ <b>Erros encontrados:</b><br><br>" . implode("<br>", $erros);
                new TMessage('error', $msg_erro);
                return;
            }

            if ($salvos === 0) {
                TTransaction::rollback();
                new TMessage('warning', 'Nenhum dado foi modificado.');
                return;
            }

            TTransaction::close();

            $msg_sucesso = "✅ <b>Plano de ação salvo com sucesso!</b><br><br>";
            $msg_sucesso .= "📝 {$salvos} " . ($salvos === 1 ? 'item atualizado' : 'itens atualizados');
            
            new TMessage('info', $msg_sucesso);
            AdiantiCoreApplication::loadPage('HistoricoList', 'onReload');
            
        } catch (Exception $e) {
            new TMessage('error', '❌ Erro ao salvar: ' . $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    private static function toDbDate($date)
    {
        $date = trim($date ?? '');
        if ($date === '' || $date === null) {
            return null;
        }

        $parts = explode('/', $date);
        if (count($parts) === 3) {
            [$day, $month, $year] = $parts;
            if (checkdate((int)$month, (int)$day, (int)$year)) {
                return sprintf('%04d%02d%02d', $year, $month, $day);
            }
        }

        return null;
    }

    private function formatDate($date)
    {
        $date = trim($date ?? '');
        if (strlen($date) === 8 && ctype_digit($date)) {
            return substr($date, 6, 2) . '/' . substr($date, 4, 2) . '/' . substr($date, 0, 4);
        }
        return '';
    }
}