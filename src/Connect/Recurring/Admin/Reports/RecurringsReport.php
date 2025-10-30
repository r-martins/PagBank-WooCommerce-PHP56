<?php

namespace RM_PagBank\Connect\Recurring\Admin\Reports;

use RM_PagBank\Connect\Recurring\Admin\Reports\Block\RecurringsReport as BlockRecurrings;

class RecurringsReport
{

    public static function reportsFilter($reports)
    {
        $reports['pagbank'] = [
            'title' => __('Assinaturas PagBank', 'pagbank-connect-php56'),
            'reports' => [
                'recorrencias' => [
                    'title'       => __('Pedidos Recorrentes', 'pagbank-connect-php56'),
                    'description' => __('Pedidos com cobrança recorrente via PagBank.', 'pagbank-connect-php56'),
                    'hide_title'  => true,
                    'callback'    => [BlockRecurrings::class, 'output']
                ]
            ]
        ];

        return $reports;
    }
   
}

