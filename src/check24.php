<?php

namespace macropage\laravel_check24;

use Arr;
use Cache;
use Exception;
use Gaarf\XmlToPhp\Convertor;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use League\Csv\CannotInsertRecord;
use League\Csv\EncloseField;
use League\Csv\Exception as CsVException;
use League\Csv\Writer;
use League\Flysystem\ConnectionRuntimeException;
use RuntimeException;
use Storage;
use XMLReader;

class check24 {

    private array            $customerConfig;
    private string           $customer;
    private XMLReader        $xmlReader;


    public function __construct() {
        $this->xmlReader = new XMLReader();
    }

    public function setCustomerConfig(string $customer): void {
        if (!config()->has('check24.accounts.' . $customer)) {
            throw new RuntimeException('missing customer "' . $customer . '" in your darpato config');
        }
        $this->customer       = $customer;
        $this->customerConfig = config('check24.accounts.' . $customer);
        config(['filesystems.disks.ftp.' . $customer . '.orders' => $this->customerConfig['orders']['ftp']]);
        config(['filesystems.disks.ftp.' . $customer . '.shippinginfo' => $this->customerConfig['shippinginfo']['ftp']]);
    }

    public function getDistinctShippingDescr($Orders): array {
        $existingShippings = [];
        foreach ($Orders as $order) {
            $shipping                                                        = Arr::first(data_get($order, 'ORDER_ITEM_LIST.ORDER_ITEM'), fn($value, $key) => ($value['LINE_ITEM_ID'] === 'shipping'));
            $existingShippings[$shipping['ARTICLE_ID']['DESCRIPTION_SHORT']] = 1;
        }

        return $existingShippings;
    }

    public function getDistinctPaymentDescr($Orders): array {
        $existingPayments = [];
        foreach ($Orders as $order) {
            $shipping                                                       = Arr::first(data_get($order, 'ORDER_ITEM_LIST.ORDER_ITEM'), fn($value, $key) => ($value['LINE_ITEM_ID'] === 'payment'));
            $existingPayments[$shipping['ARTICLE_ID']['DESCRIPTION_SHORT']] = 1;
        }

        return $existingPayments;
    }

    /**
     * @return array
     */
    public function getCustomerConfig(): array {
        return $this->customerConfig;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getXMLOrdersCached(): array {
        $onlyFiles = cache()->tags(['check24.' . $this->customer])->get('check24.orders.' . $this->customer);
        $xmlOrders = [];
        foreach ($onlyFiles as $file) {
            $xmlOrders[$file['basename']] = cache()->tags(['check24.' . $this->customer])->get('check24.orders.' . $this->customer . '.' . $file['basename']);
        }

        return $xmlOrders;
    }

    public function setDone($orderIdXML) {
        return Storage::disk('ftp.' . $this->customer . '.orders')->move('outbound/'.$orderIdXML, 'outbound/done/'.$orderIdXML);
    }

    /**
     * @param      $orderId
     *
     * @param bool $useCache
     *
     * @return array|bool|mixed[]
     * @throws FileNotFoundException
     */
    public
    function getSingleXMLOrder($orderId, $useCache = false) {
        $OrderFileName = 'outbound/' . $orderId . '.xml';

        if ($useCache) {
            d('check24.orders.' . $this->customer . '.' . $OrderFileName);
            if (!Cache::tags(['check24.' . $this->customer])->has('check24.orders.' . $this->customer . '.' . $OrderFileName)) {
                return ['state' => false, 'msg' => 'order not found in cache'];
            }

            return ['state' => true, 'order' => Cache::tags(['check24.' . $this->customer])->get('check24.orders.' . $this->customer . '.' . $OrderFileName)];
        }

        if (!Storage::disk('ftp.' . $this->customer . '.orders')->exists($OrderFileName)) {
            return ['state' => false, 'msg' => 'file not found on server: ' . $OrderFileName];
        }
        $file_contents = Storage::disk('ftp.' . $this->customer . '.orders')->get($OrderFileName);

        $OrderArray = $this->xml2array($file_contents, str_replace('outbound/','',$OrderFileName));
        Cache::tags(['check24.' . $this->customer])->put('check24.orders.' . $this->customer . '.' . $OrderFileName, $OrderArray);

        return ['state' => true, 'order' => $OrderArray];
    }

    /**
     * @param $order_number
     * @param $carrier
     * @param $shipping_number
     *
     * @return bool|string
     * @throws CannotInsertRecord
     * @throws CsVException
     */
    public function uploadShippingData($order_number, $carrier, $shipping_number) {
        $csv = Writer::createFromString('');
        $csv->setDelimiter(';');
        EncloseField::addTo($csv, "\t\x1f");
        $csv->insertOne(['order_number', 'delivery_type', 'tracking_number']);
        $csv->insertOne([$order_number, $carrier, $shipping_number]);
        return Storage::disk('ftp.' . $this->customer . '.shippinginfo')->put($order_number.'.csv',$csv->getContent());
    }

    /**
     * @param bool $useCache
     *
     * @return array
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function getXMLOrders(bool $useCache = false): array {

        if ($useCache) {
            return $this->getXMLOrdersCached();
        }

        Cache::tags('check24.' . $this->customer)->flush();

        /**
         * Get Files if not existent in cache
         */
        $adapter = Storage::disk('ftp.' . $this->customer . '.orders');
        $adapter->getDriver()->getAdapter()->setEnableTimestampsOnUnixListings(true);

        $redo = 0;
        while ($redo<10) {
            try {
                $remoteContents = $adapter->listContents('outbound');
                $redo=10;
            } catch (ConnectionRuntimeException $e) {
                $redo++;
                if ($redo>10) {
                    throw new ConnectionRuntimeException($e);
                }

                echo 'connect failed: '.$this->customerConfig['orders']['ftp']['host'].' retry again in 10 seconds....'."\n";
                sleep(10);
            }
        }



        // filter only files and only take order from within this year
        $onlyFiles = array_filter($remoteContents, fn($var) => ($var['type'] === 'file'));
        // https://github.com/thephpleague/flysystem/issues/1161
        foreach ($onlyFiles as $i => $file) {
            if (array_key_exists('timestamp',$file)) {
                $timestamp = $file['timestamp'];
            } else {
                $timestamp = Storage::disk('ftp.' . $this->customer . '.orders')->getTimestamp($file['path']);
            }
            if ($timestamp) {
                $onlyFiles[$i]['timestamp'] = $timestamp;
            } else {
                throw new RuntimeException('unable to get timestamp of: ' . $file['path']);
            }
        }
        usort($onlyFiles, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        Cache::tags(['check24.' . $this->customer])->put('check24.orders.' . $this->customer, $onlyFiles);

        /**
         * Parse Each XML to Array
         */
        foreach ($onlyFiles as $file) {
            $file_contents = Storage::disk('ftp.' . $this->customer . '.orders')->get($file['path']);
            $OrderArray    = $this->xml2array($file_contents, $file['basename']);
            Cache::tags(['check24.' . $this->customer])->put('check24.orders.' . $this->customer . '.' . $file['basename'], $OrderArray);
        }

        $xmlOrders = [];

        foreach ($onlyFiles as $file) {
            $xmlOrders[$file['basename']] = cache()->tags(['check24.' . $this->customer])->get('check24.orders.' . $this->customer . '.' . $file['basename']);
        }

        return $xmlOrders;

    }

    private function checkXMLisValid(string $string) {
        $this->xmlReader->XML($string);
        $this->xmlReader->setParserProperty(XMLReader::VALIDATE, true);

        return $this->xmlReader->isValid();
    }

    /**
     * @param $xml
     * @param $filename
     *
     * @return array
     */
    private function xml2array($xml, $filename): array {
        if (!$this->checkXMLisValid($xml)) {
            throw new RuntimeException('xml invalid: ' . $filename);
        }
        $OrderArray = Convertor::covertToArray($xml);
        if (!is_array($OrderArray)) {
            throw new RuntimeException('unable to convert to array: ' . $filename);
        }

        $OrderArray['source'] = $filename;

        if (data_get($OrderArray,'ORDER_ITEM_LIST.ORDER_ITEM.LINE_ITEM_ID')!==null) {
            $OrderItem = data_get($OrderArray,'ORDER_ITEM_LIST.ORDER_ITEM');
            Arr::forget($OrderArray,'ORDER_ITEM_LIST.ORDER_ITEM');
            $OrderArray['ORDER_ITEM_LIST']['ORDER_ITEM'][] = $OrderItem;
        }

        return $OrderArray;
    }

}
