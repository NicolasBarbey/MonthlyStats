<?php
/*************************************************************************************/
/*      Copyright (c) Franck Allimant, CQFDev                                        */
/*      email : thelia@cqfdev.fr                                                     */
/*      web : http://www.cqfdev.fr                                                   */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Franck Allimant, CQFDev <franck@cqfdev.fr>
 * Date: 24/01/2019 11:47
 */

namespace MonthlyStats\Hook;

use MonthlyStats\MonthlyStats;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Tools\URL;

class HookManager extends BaseHook
{
    public function onMainTopMenuTools(HookRenderBlockEvent $event)
    {
        $event->add(
            [
                'id' => 'tools_menu_ca_monthly',
                'class' => '',
                'url' => URL::getInstance()->absoluteUrl('/admin/ca-monthly'),
                'title' => $this->translator->trans("C.A. Mensuel", [], MonthlyStats::DOMAIN_NAME),
            ])
            ->add(
                [
                    'id' => 'tools_menu_ca_by_category',
                    'class' => '',
                    'url' => URL::getInstance()->absoluteUrl('/admin/ca-by-category', [
                        'month_start' => date('m'),
                        'month_end' => date('m'),
                        'year_start' => date('Y'),
                        'year_end' => date('Y')

                    ]),
                    'title' => $this->translator->trans("C.A. par catégorie", [], MonthlyStats::DOMAIN_NAME),
                ]
            );
    }
}
