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
 * Date: 24/01/2019 11:49
 */
namespace MonthlyStats\Controller;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\PdoConnection;
use Propel\Runtime\Propel;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\ProductCategoryTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;

class AdminCaController extends BaseAdminController
{
    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function caParRubrique()
    {
        $moisDebut = $this->getRequest()->get('mois_debut');
        $moisFin = $this->getRequest()->get('mois_fin');

        $anneeDebut = $this->getRequest()->get('annee_debut');
        $anneeFin = $this->getRequest()->get('annee_fin');

        $dateDebut = new \DateTime(sprintf("%04d-%02d-01 00:00:00", $anneeDebut, $moisDebut));
        $dateFin   = new \DateTime(sprintf("%04d-%02d-31 23:59:59", $anneeFin, $moisFin));

        /** @var PdoConnection $con */
        $con = Propel::getConnection();

        $query = "
            SELECT 
                SUM(".OrderProductTableMap::QUANTITY." * IF(".OrderProductTableMap::WAS_IN_PROMO.",".OrderProductTableMap::PROMO_PRICE.",".OrderProductTableMap::PRICE.")) as total_ht,
                SUM(".OrderProductTableMap::QUANTITY." * IF(".OrderProductTableMap::WAS_IN_PROMO.",".OrderProductTaxTableMap::PROMO_AMOUNT.",".OrderProductTaxTableMap::AMOUNT.")) as total_tva,
                ".ProductCategoryTableMap::CATEGORY_ID." as cat_id,
                ".CategoryTableMap::PARENT." as cat_parent
            FROM
                ".OrderProductTableMap::TABLE_NAME."
            LEFT JOIN
                ".OrderTableMap::TABLE_NAME." on ".OrderTableMap::ID." = ".OrderProductTableMap::ORDER_ID."
            LEFT JOIN
                ".ProductTableMap::TABLE_NAME." on ".ProductTableMap::REF." = ".OrderProductTableMap::PRODUCT_REF."
            LEFT JOIN
                ".OrderProductTaxTableMap::TABLE_NAME." on ".OrderProductTaxTableMap::ORDER_PRODUCT_ID." = ".OrderProductTableMap::ID."
            LEFT JOIN
                ".ProductCategoryTableMap::TABLE_NAME." on ".ProductCategoryTableMap::PRODUCT_ID." = ".ProductTableMap::ID." and ".ProductCategoryTableMap::DEFAULT_CATEGORY." = 1
            LEFT JOIN
                ".CategoryTableMap::TABLE_NAME." on ".CategoryTableMap::ID." = ".ProductCategoryTableMap::CATEGORY_ID."
            WHERE
                ".OrderTableMap::INVOICE_DATE." >= ?    
            AND
                ".OrderTableMap::INVOICE_DATE." <= ?    
            AND
                ".OrderTableMap::STATUS_ID." not in (?, ?)
            GROUP BY  
                ".ProductCategoryTableMap::CATEGORY_ID."
            ORDER BY
                total_ht desc
        ";

        $query = preg_replace("/order([^_])/", "`order`$1", $query);

        $stmt = $con->prepare($query);

        $res = $stmt->execute([
            $dateDebut->format("Y-m-d H:i:s"),
            $dateFin->format("Y-m-d H:i:s"),
            OrderStatusQuery::getNotPaidStatus()->getId(),
            OrderStatusQuery::getCancelledStatus()->getId()
        ]);

        $catData = $topLevelData = [];

        while ($res && $result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $catDatatId = intval($result['cat_id']);

            if ($catDatatId > 0) {
                $tree = CategoryQuery::getPathToCategory($catDatatId);

                $label = '';
                $locale = $this->getSession()->getLang()->getLocale();

                /** @var Category $cat */
                foreach ($tree as $cat) {
                    $title = $cat->setLocale($locale)->getTitle();

                    $label .= $title . " &gt; ";

                    if ($cat->getParent() === 0) {
                        $this->addOrder($title, $result, $topLevelData);
                    }
                }

                $label = substr($label, 0, -6);
            } else {
                $label = "Catégorie inexistante (probablement supprimée)";

                $this->addOrder($label, $result, $topLevelData);
            }

            $catData[] = [
                'total_ht' => $result['total_ht'],
                'total_tva' => $result['total_tva'],
                'total_ttc' => $result['total_ht'] + $result['total_tva'],
                'cat_label' => $label
            ];
        }

        //var_dump($topLevelData);

        return $this->render("ca-par-rubrique", [
            'catData' => $catData,
            'topLevelData' => $topLevelData,
            'mois_debut'   => $dateDebut->format("n"),
            'mois_fin'     => $dateDebut->format("n"),
            'annee_debut'  => $dateDebut->format("Y"),
            'annee_fin'    => $dateDebut->format("Y"),
        ]);
    }

    /**
     * @param $query
     * @param \DateTime $dateDebut
     * @param \DateTime $dateFin
     * @return \PDOStatement|null
     */
    protected function executeOrderRequest($query, \DateTime $dateDebut, \DateTime $dateFin)
    {
        /** @var PdoConnection $con */
        $con = Propel::getConnection();

        $query = preg_replace("/order([^_])/", "`order`$1", $query);

        $stmt = $con->prepare($query);

        $res = $stmt->execute([
            $dateDebut->format("Y-m-d H:i:s"),
            $dateFin->format("Y-m-d H:i:s")
        ]);

        return $res ? $stmt : null;
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     * @throws \Exception
     */
    public function caMensuel()
    {
        $data = [];

        $firstPaidOrder = OrderQuery::create()
            ->orderByInvoiceDate(Criteria::ASC)
            ->filterByStatusId(OrderStatusQuery::getPaidStatusIdList(), Criteria::IN)
            ->findOne();

        if (null !== $firstPaidOrder) {
            $anneeDebut = $this->getRequest()->get('annee_debut', $firstPaidOrder->getInvoiceDate('Y'));
            $anneeFin = $this->getRequest()->get('annee_fin', date('Y'));

            $dateDebut = new \DateTime(sprintf("%04d-01-01 00:00:00", $anneeDebut));
            $dateFin = new \DateTime(sprintf("%04d-12-31 23:59:59", $anneeFin));

            // Get monthly discount total
            $query = "
                SELECT 
                    SUM(" . OrderTableMap::DISCOUNT.") as discount,
                    SUM(" . OrderTableMap::POSTAGE.") as postage,
                    SUM(" . OrderTableMap::POSTAGE_TAX.") as postage_tax,
                    " . OrderTableMap::CREATED_AT . " as invoice_date,
                    MONTH(" . OrderTableMap::INVOICE_DATE . ") as mois,
                    YEAR(" . OrderTableMap::INVOICE_DATE . ") as annee                    
                FROM
                    " . OrderTableMap::TABLE_NAME . "
                WHERE
                    " . OrderTableMap::INVOICE_DATE . " >= ?    
                AND
                    " . OrderTableMap::INVOICE_DATE . " <= ?    
                AND
                    " . OrderTableMap::STATUS_ID . " in (".implode(',', OrderStatusQuery::getPaidStatusIdList()).")
                GROUP BY
                    annee, mois
                ORDER BY
                    invoice_date desc
            ";

            $orderData = [];

            $stmt = $this->executeOrderRequest($query, $dateDebut, $dateFin);

            while ($stmt !== null && $result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $year = $result['annee'];
                $month = $result['mois'];

                if (!isset($orderData[$year])) {
                    $orderData[$year] = [];
                }

                $orderData[$year][$month] = [
                    'discount' => $result['discount'],
                    'postage' => $result['postage'],
                    'postage_tax' => $result['postage_tax']
                ];
            }

            // Get monthly sales and taxes
            $query = "
                SELECT 
                    SUM(" . OrderProductTableMap::QUANTITY . " * IF(" . OrderProductTableMap::WAS_IN_PROMO . "," . OrderProductTableMap::PROMO_PRICE . "," . OrderProductTableMap::PRICE . ")) as total_ht,
                    SUM(" . OrderProductTableMap::QUANTITY . " * IF(" . OrderProductTableMap::WAS_IN_PROMO . "," . OrderProductTaxTableMap::PROMO_AMOUNT . "," . OrderProductTaxTableMap::AMOUNT . ")) as total_tva,
                    " . OrderTableMap::INVOICE_DATE . " as invoice_date,
                    MONTH(" . OrderTableMap::INVOICE_DATE . ") as mois,
                    YEAR(" . OrderTableMap::INVOICE_DATE . ") as annee
                FROM
                    " . OrderProductTableMap::TABLE_NAME . "
                LEFT JOIN
                    " . OrderTableMap::TABLE_NAME . " on " . OrderTableMap::ID . " = " . OrderProductTableMap::ORDER_ID . "
                LEFT JOIN
                    " . ProductTableMap::TABLE_NAME . " on " . ProductTableMap::REF . " = " . OrderProductTableMap::PRODUCT_REF . "
                LEFT JOIN
                    " . OrderProductTaxTableMap::TABLE_NAME . " on " . OrderProductTaxTableMap::ORDER_PRODUCT_ID . " = " . OrderProductTableMap::ID . "
                LEFT JOIN
                    " . ProductCategoryTableMap::TABLE_NAME . " on " . ProductCategoryTableMap::PRODUCT_ID . " = " . ProductTableMap::ID . " and " . ProductCategoryTableMap::DEFAULT_CATEGORY . " = 1
                WHERE
                    " . OrderTableMap::INVOICE_DATE . " >= ?    
                AND
                    " . OrderTableMap::INVOICE_DATE . " <= ?    
                AND
                    " . OrderTableMap::STATUS_ID . " in (".implode(',', OrderStatusQuery::getPaidStatusIdList()).")
                GROUP BY
                    annee, mois
                ORDER BY
                    invoice_date desc
            ";

            $stmt = $this->executeOrderRequest($query, $dateDebut, $dateFin);

            while ($stmt !== null && $result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $year = $result['annee'];
                $month = $result['mois'];

                if (!isset($data[$year])) {
                    $data[$year] = [];
                }

                $totalHt = $result['total_ht'];

                if (isset($orderData[$year][$month])) {
                    $totalHt -= $orderData[$year][$month]['discount'];
                    $postage = $orderData[$year][$month]['postage'];
                    $postageTax = $orderData[$year][$month]['postage_tax'];
                } else {
                    $postage = $postageTax = 0;
                }

                $data[$year][$month] = [
                    'total_ht' => $totalHt,
                    'total_tva' => $result['total_tva'],
                    'total_ttc' => $totalHt + $result['total_tva'],
                    'postage' => $postage,
                    'postage_tax' => $postageTax
                ];
            }
        } else {
            $anneeDebut = date('Y');
        }

        return $this->render("ca-mensuel", [
            'data'         => $data,
            'annee_debut'  => $anneeDebut,
            'annee_fin'    => $anneeFin
        ]);
    }

    protected function addOrder($label, $result, &$topLevelData)
    {
        if (!isset($topLevelData[$label])) {
            $topLevelData[$label] = [
                'total_ht' => 0,
                'total_tva' => 0,
                'total_ttc' => 0,
            ];
        }

        $topLevelData[$label]['total_ht'] += $result['total_ht'];
        $topLevelData[$label]['total_tva'] += $result['total_tva'];
        $topLevelData[$label]['total_ttc'] += $result['total_ht'] + $result['total_tva'];
    }
}
