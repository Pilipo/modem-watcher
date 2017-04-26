<?php

namespace Pilipo\HomeTools;

require "vendor/autoload.php";

use PHPHtmlParser\Dom;

openlog('modem-watcher', LOG_PID | LOG_PERROR, LOG_LOCAL0);

class ModemWatcher
{
    private $ip;
    private $statusSearch;
    private $signalSearch;
    private $statusDom;
    private $signalDom;
    
    public function __construct()
    {
        $this->statusSearch = [
            'Cable Modem Status'    =>  'Operational',
            'System Up Time'        =>  null,
        ];
        
        $this->signalSearch = [
            'downstream' => [
                'Signal to Noise Ratio' => [32, 100,], // values are [low, high]
                'Power Level'           => [-8, 8,],   // values are [low, high]
            ],
            'upstream' => [
                'Power Level'    =>  [40, 51,],  // values are [low, high]
                'Ranging Status' =>  "Success",
            ],
        ];

        $this->SetIP('192.168.100.1');
    }

    public function __get($prop)
    {
        switch ($prop) {
            case 'status':
                $return = '';
                foreach ($this->getStatus() as $k => $v) {
                    $return .= $k . "=" . $v . ",";
                }
                syslog(LOG_INFO, $return);
                return $return;
                break;
            case 'signal':
                $return = '';
                foreach ($this->getSignal() as $stream => $collection) {
                    foreach ($collection as $k => $v) {
                        if (is_array($v)) {
                            $return .= $stream . ": " . $k . "=" . implode(', ', $v) . ",";
                        } else {
                            $return .= $stream . ": " . $k . "=" . $v . ",";
                        }
                    }
                }
                syslog(LOG_INFO, $return);
                return $return;
                break;
            case 'ip':
                return $this->ip;
                break;
        }
    }
    
    public function __set($prop, $value)
    {
        switch ($prop) {
            case 'ip':
                $this->setIP($value);
                break;
        }
    }
    
    private function getStatus()
    {
        $this->parseModemInterfaces();
        
        $returnArray = array();
        $td = $this->statusDom->find('td');
        
        foreach ($td as $tdKey => $tdValue) {
            foreach ($this->statusSearch as $searchKey => $searchValue) {
                if ($tdValue->innerHtml == $searchKey) {
                    $returnArray[$searchKey] = $td[$tdKey + 1]->innerHtml;
                    if ($tdKey != null && $returnArray[$searchKey] != $searchValue) {
                        //This is an unexpected value
                        //LOG A WARNING
                    } else {
                        //This is an expected value
                        //LOG INFO
                    }
                }
            }
        }
        return $returnArray;
    }
    
    private function getSignal()
    {
        $this->parseModemInterfaces();

        $returnArray = array();
        $signalRowsCollection = array();
        
        $downstreamTable = $this->signalDom->find('table')[0];
        $signalRowsCollection['downstream'] = $downstreamTable->find('tr');
        $channelCount['downstream'] = $this->getChannelCount($downstreamTable->find('th'));
        
        $upstreamTable = $this->signalDom->find('table')[1];
        $signalRowsCollection['upstream'] = $upstreamTable->find('tr');
        $channelCount['upstream'] = $this->getChannelCount($upstreamTable->find('th'));
        
        foreach ($signalRowsCollection as $key => $rows) {
            foreach ($rows as $row) {
                $contents = $row->firstChild();
                if (ctype_space($contents->innerHtml)) {
                    continue;
                }

                foreach ($this->signalSearch[$key] as $searchRowName => $searchValue) {
                    if (is_array($searchValue)) {
                        $minVal = $searchValue[0];
                        $maxVal = $searchValue[1];
                    }
                    
                    $currentRowName = $contents->firstChild()->innerHtml;
                    $bondingChannelArray = [];
                    if ($currentRowName == $searchRowName) {
                        for ($i = 0; $i < $channelCount[$key] + 1; $i++) {
                            $contents = $contents->nextSibling();
                            if (ctype_space($contents->innerHtml)) {
                                continue;
                            }
                            
                            $filteredCellContents = filter_var($contents->innerHtml, FILTER_SANITIZE_NUMBER_INT);
                            if ($filteredCellContents < $minVal && $filteredCellContents > $maxVal) {
                                //log warning
                            }

                            array_push($bondingChannelArray, trim(preg_replace("/&#?[a-z0-9]+;/i", "", $filteredCellContents)));
                        }
                    }
                    
                    if ($bondingChannelArray) {
                        $returnArray[$key][$currentRowName] = $bondingChannelArray;
                    }
                }
            }
        }
        return $returnArray;
    }
    
    private function setIP($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->ip = $ip;
        } else {
            throw new \InvalidArgumentException('IP address provided was not valid. Input was: ' . $ip);
        }
    }
    
    private function parseModemInterfaces()
    {
        $this->statusDom = new Dom;
        $this->signalDom = new Dom;
        $this->statusDom->loadFromUrl("http://$this->ip/indexData.htm");
        $this->signalDom->loadFromUrl("http://$this->ip/cmSignalData.htm");
    }
    
    private function getChannelCount($tableHeaders)
    {
        $returnCount = 0;
        foreach ($tableHeaders as $header) {
            $columnTitle = $header->firstChild()->innerHtml;
            if ($columnTitle == "Bonding Channel Value") {
                $returnCount = $header->getAttribute('colspan');
            }
        }
        return $returnCount;
    }
}
