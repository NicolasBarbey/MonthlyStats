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

use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Tools\URL;

class HookManager extends BaseHook
{
    public function onMainTopMenuTools(HookRenderBlockEvent $event)
    {
        $event->add(
            [
                'id' => 'tools_menu_ca_mensuel',
                'class' => '',
                'url' => URL::getInstance()->absoluteUrl('/admin/ca-mensuel'),
                'title' => "C.A. Mensuel"
            ])
            ->add(
                [
                    'id' => 'tools_menu_ca_par_rubrique',
                    'class' => '',
                    'url' => URL::getInstance()->absoluteUrl('/admin/ca-par-rubrique', [
                        'mois_debut' => date('m'),
                        'mois_fin' => date('m'),
                        'annee_debut' => date('Y'),
                        'annee_fin' => date('Y')

                    ]),
                    'title' => "C.A. par catégorie"
                ]
            );
    }
}
