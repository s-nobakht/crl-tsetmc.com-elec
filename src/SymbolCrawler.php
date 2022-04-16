<?php

//=======================================================================
// A crawler for getting electricity energy market data from tsetmc.com.
// Developed by Saeid.S.Nobakht
//=======================================================================

namespace NR\SymbolCrawler;

require __DIR__ . '/vendor/autoload.php';
require 'IntlDateTime.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use GuzzleHttp\Client;
use NR\IntlDateTime;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;


class SymbolCrawler{
    /**
     * @param mixed $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }
    /**
     * @return mixed
     */
    public function getLogger()
    {
        return $this->logger;
    }
    /**
     * @return string
     */
    public function getBazarType()
    {
        return $this->bazarType;
    }

    /**
     * @return string
     */
    public function getProductName()
    {
        return $this->productName;
    }

    /**
     * @return string
     */
    public function getProducerName()
    {
        return $this->producerName;
    }

    /**
     * @return string
     */
    public function getContractType()
    {
        return $this->contractType;
    }

    /**
     * @return string
     */
    public function getDeliveryPlace()
    {
        return $this->deliveryPlace;
    }
    /**
     * @return mixed
     */
    public function getLogPath()
    {
        return $this->logPath;
    }

    /**
     * @param mixed $logPath
     */
    public function setLogPath($logPath)
    {
        $this->logPath = $logPath;
    }

    /**
     * @return mixed
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * @param mixed $userAgent
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    /**
     * @return array
     */
    public function getLoadType()
    {
        return $this->loadType;
    }

    /**
     * @param array $loadType
     */
    public function setLoadType($loadType)
    {
        $this->loadType = $loadType;
    }

    /**
     * @return string
     */
    public function getDeliveryPeriod()
    {
        return $this->deliveryPeriod;
    }

    /**
     * @param string $deliveryPeriod
     */
    public function setDeliveryPeriod($deliveryPeriod)
    {
        $this->deliveryPeriod = $deliveryPeriod;
    }

    /**
     * @return string
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @param string $startDate
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;
    }

    /**
     * @return string
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param string $endDate
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;
    }

    protected $logger;
    protected $logPath;
    protected $userAgent;           // Crawlers' user agent
    protected $bazarType;           // 'D' => Domestic, 'I' => International
    protected $productName;         // 'AP' => Active Power, 'OI' => Crude Iol, ...
    protected $producerName;        // '00' => Electricity Bazar, Others will be assigned after accept
    protected $contractType;        // 'PF' => salaf e movazi, 'CA' => Cash
    protected $loadType;            // 'B' => Base, 'P' => Peak, 'M' => Medium, 'L' => Low
    protected $deliveryPlace;       // 'EX' => Ex-Work, ...
    protected $deliveryPeriod;      // 'D' => Day, 'W' => Week, 'M' => Month, 'S' => Season, 'Y' => Year

    protected $startDate;
    protected $endDate;

    public function __construct($logPath='/logs/crawl.log',
                                $userAgent="Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0",
                                $bazarType='D',
                                $productName='AP',
                                $producerName='00',
                                $contractType='PF',
                                $loadType=['Base'=>'B', 'Low'=>'L', 'Mid'=>'M', 'Peak'=>'P'],
                                $deliveryPlace='EX',
                                $deliveryPeriod='D',
                                $startDate='1399/01/20',
                                $endDate='1401/01/20'
                            )
    {
        $this->bazarType = $bazarType;
        $this->productName = $productName;
        $this->producerName = $producerName;
        $this->contractType = $contractType;
        $this->loadType = $loadType;
        $this->deliveryPlace = $deliveryPlace;
        $this->deliveryPeriod = $deliveryPeriod;
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        // Create the logger
        $this->setLogger( new Logger('crawler_log') );
        // Now add some handlers
        $this->geLogger()->pushHandler(new StreamHandler(__DIR__.$this->getLogPath(), Logger::DEBUG));
        $this->getLogger()->pushHandler(new FirePHPHandler());
        // You can now use your logger
    }

    public function startCrawl()
    {
        $this->getLogger()->addInfo('Program Started !');
        $startDate = new IntlDateTime($this->getStartDate(), 'Asia/Tehran', 'persian');
        $endDate = new IntlDateTime($this->getEndDate(), 'Asia/Tehran', 'persian');
        $dateCounter = clone $startDate;
        $afterEndDate = clone $endDate;
        $afterEndDate->modify('next day');

        $symbolIdPrefix = $this->getBazarType().$this->getProductName().$this->getProducerName().$this->getContractType();
        $symbolId = "";
        $symbolUrl = "";
        $allData = array();
        $baseUri['script'] = "/Loader.aspx";
        $baseUri['query'] = "?ParTree=15131S&i=";
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'http://www.tsetmc.com',
            // You can set any number of default request options.
            'timeout'  => 10.0,
        ]);
        $counter = 0;
        foreach ($this->getLoadType() as $key => $val) {
            $symbolIdPrefix .= $val.$this->getDeliveryPlace().$this->getDeliveryPeriod();
            $symbolUrl = $baseUri['query'];
            for($i=0; $dateCounter->format('yy/MM/dd') != $afterEndDate->format('yy/MM/dd'); $i++)
            {
                $symbolId = $symbolIdPrefix.$dateCounter->format('yyMMdd');
                $symbolUrl .= $symbolId;
                $counter++;
                $this->getLogger()->info($counter.",".$key.",".$symbolId);
                try {
                    $response = $client->request('GET', $baseUri['script'], ['headers' => ['user-agent' => $this->getUserAgent()], 'query' => ['ParTree'=>'15131S','i'=>$symbolId]]);
                    //echo $response->getBody();
//                    file_put_contents("./dumps/test.html", $response->getBody());
                    $symbolData = $this->extractSymbolData($response->getBody());
                    array_push($allData, $symbolData);
                    //die;

                } catch (RequestException $e) {
                    $this->getLogger()->error(Psr7\str($e->getRequest()));
                    //echo Psr7\str($e->getRequest());
                    if ($e->hasResponse()) {
                        $this->getLogger()->error(Psr7\str($e->getResponse()));
                        echo Psr7\str($e->getResponse());
                    }
                }
            }
            $symbolIdPrefix = $this->getBazarType().$this->getProducerName().$this->getProducerName().$this->getContractType();
            $symbolId = "";
        }
        return $allData;
    }

    /**
     * @param $pageContent
     */
    private function extractSymbolData($pageContent)
    {
        $symbolData = array();
        $pattern = "/StartDate='([0-9]+)'.*?EndDate='([0-9]+)'.*?ExpireDate='(.*?)'.*?StuffCode='([0-9]+)'.*?LoadType='([0-9]+)'.*?Title='(.*?)'.*?Hours='([0-9]+)'.*?EnergySymbol='(.*?)'.*?StartValidity='([0-9]+)'.*?EndValidity='([0-9]+)'.*?StartValidityShamsi\s*=\s*'([0-9]+)'.*?EndValidityShamsi\s*=\s*'([0-9]+)'.*?InsCode\s*=\s*'([0-9]+)'.*?OpenSymbol\s*=\s*'([0-9]+)';/is";
        preg_match_all($pattern, $pageContent, $matches);
        $symbolData['startDate'] = $matches[1][0];
        $symbolData['endDate'] = $matches[2][0];
        $symbolData['expireDate'] = $matches[3][0];
        $symbolData['stuffCode'] = $matches[4][0];
        $symbolData['loadType'] = $matches[5][0];
        $symbolData['title'] = $matches[6][0];
        $symbolData['hours'] = $matches[7][0];
        $symbolData['energySymbol'] = $matches[8][0];
        $symbolData['startValidity'] = $matches[9][0];
        $symbolData['endValidity'] = $matches[10][0];
        $symbolData['startValidityShamsi'] = $matches[11][0];
        $symbolData['endValidityShamsi'] = $matches[12][0];
        $symbolData['insCode'] = $matches[13][0];
        $symbolData['openSymbol'] = $matches[14][0];
        $symbolData['trades'] = array();

        $tradesPattern = "/<tr><td>(.*?)<\/td>.*?<td>(.*?)<\/td>.*?<td>(.*?)<\/td>.*?<td>(.*?)<\/td>.*?<td>(.*?)<\/td>.*?<td>(.*?)<\/td>.*?<td><div\s+class='ltr'\s+title=\"(.*?)\">(.*?)<\/div><\/td><\/tr>/is";
        preg_match_all($tradesPattern, $pageContent, $matches);
        $dataRow = array();
        if(empty($matches[1])){
            ;
        }
        else{
            foreach ($matches[1] as $key => $val){
                $dataRow['date'] = $val;
                $dataRow['end'] = $matches[2][$key];
                $dataRow['lowest'] = $matches[3][$key];
                $dataRow['highest'] = $matches[4][$key];
                $dataRow['amount'] = $matches[5][$key];
                $dataRow['volume'] = $matches[6][$key];
                $dataRow['cost'] = $matches[7][$key];
                array_push($symbolData['trades'], $dataRow);
            }
        }
        return $symbolData;
    }



}









//echo $response;


