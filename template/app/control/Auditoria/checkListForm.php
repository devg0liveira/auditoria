<?php
// app/control/CheckListForm.php

use Adianti\Control\TPage;
use Adianti\Core\AdiantiCoreApplication;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Dialog\TMessage;

class CheckListForm extends TPage
{
    // ... outros métodos ...

    /**
     * Visualiza uma auditoria existente
     */
    public static function onView($param)
    {
        try {
            TTransaction::open('auditoria');
            $key = $param['key'];
            $auditoria = new ZCM010($key);

            // Carrega dados e abre em modo visualização
            TSession::setValue('auditoria_key', $key);
            TSession::setValue('view_mode', true);

            AdiantiCoreApplication::loadPage('CheckListForm', 'onEdit', $param);
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }
}