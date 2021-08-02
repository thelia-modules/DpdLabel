<?php


namespace DpdLabel\Export;


use DpdLabel\Model\DpdlabelLabelsQuery;
use DpdLabel\Model\Map\DpdlabelLabelsTableMap;
use Thelia\ImportExport\Export\AbstractExport;
use Thelia\Model\Map\CountryI18nTableMap;
use Thelia\Model\Map\CountryTableMap;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\Map\OrderTableMap;

class DeliveryExport extends AbstractExport
{
    const FILE_NAME = 'Dpd_delivery';

    protected $orderAndAliases = [
        'order_REF' => 'Order Ref',
        DpdlabelLabelsTableMap::LABEL_NUMBER => 'Label Number',
        DpdlabelLabelsTableMap::UPDATED_AT => 'Date',
        'delivery_name' => 'Name',
        'delivery_company' => 'Company',
        'delivery_address' => 'Address',
        'delivery_zipcode' => 'Zip code',
        'delivery_city' => 'City',
        'delivery_country' => 'Country'
    ];

    protected $date;

    /**
     * DeliveryExport constructor.
     * @param $date
     */
    public function __construct()
    {
        $this->date = new \DateTime('2021-07-29');
    }

    protected function getData()
    {
        return DpdlabelLabelsQuery::create()
            ->filterByUpdatedAt([
                'min' => $this->date->format('Y-m-d').'00:00:00',
                'max' => $this->date->format('Y-m-d').'23:59:59'
            ])
            ;
    }

    public function current()
    {
        $dpdLabel = parent::current();

        return DpdlabelLabelsQuery::create()
            ->filterById($dpdLabel[DpdlabelLabelsTableMap::ID])
            ->useOrderQuery()
                ->addAsColumn('order_REF',OrderTableMap::REF)
                ->useOrderAddressRelatedByDeliveryOrderAddressIdQuery()
                    ->addAsColumn('delivery_name','CONCAT('.OrderAddressTableMap::FIRSTNAME.', " ",'.OrderAddressTableMap::LASTNAME.')')
                    ->addAsColumn('delivery_address',OrderAddressTableMap::ADDRESS1)
                    ->addAsColumn('delivery_zipcode',OrderAddressTableMap::ZIPCODE)
                    ->addAsColumn('delivery_city',OrderAddressTableMap::CITY)
                    ->addAsColumn('delivery_company',OrderAddressTableMap::COMPANY)
                    ->useCountryQuery()
                        ->useI18nQuery($this->getLang()->getLocale())
                            ->addAsColumn('delivery_country', CountryI18nTableMap::TITLE)
                        ->endUse()
                    ->endUse()
                ->endUse()
            ->endUse()
            ->select([
                'order_REF',
                DpdlabelLabelsTableMap::LABEL_NUMBER,
                DpdlabelLabelsTableMap::UPDATED_AT,
                'delivery_name',
                'delivery_address',
                'delivery_zipcode',
                'delivery_city',
                'delivery_country',
                'delivery_company'
            ])
            ->findOne()
            ;
    }

    public function getFileName()
    {
        return self::FILE_NAME.'_'.$this->date->format('Y-m-d');
    }

}