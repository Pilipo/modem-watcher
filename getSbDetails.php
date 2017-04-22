<?php

// Assuming you installed from Composer:
require "vendor/autoload.php";
use PHPHtmlParser\Dom;
openlog('modem-watcher', LOG_PID | LOG_PERROR, LOG_LOCAL0);

syslog(LOG_INFO, "INFO >> Parse of surfboard starting.");

$statusDom = new Dom;
$signalDom = new Dom;
$dstDom = new Dom;
$ustDom = new Dom;
//**************************************
//
//	DEFINABLE SEARCH VALUES
//
//	Feel free to adjust the "expected" values and ranges below. 
//	They are set to common standards, but you may want to tweak it.	
//
//**************************************

$statusSearchStrings = [
	'Cable Modem Status'	=>	'Operational',
	'System Up Time'		=>	null,
];

$signalSearchStrings = [
	'downstream'		=>	[
		'Signal to Noise Ratio'		=>	[32, 100,], // values are [low, high]
		'Power Level'				=>	[-8, 8,], 	// values are [low, high]
		//'Downstream Modulation'		=>	"QAM256",
	],
	'upstream'			=>	[
		'Power Level'				=>	[40, 51,],	// values are [low, high]
		'Ranging Status'			=>	"Success",
	],
];

//SEARCH THE STATUS PAGE

$statusDom->loadFromUrl('http://192.168.100.1/indexData.htm');

$statusBlob = $statusDom->find('td');

foreach ($statusBlob as $k=>$v) {
	foreach($statusSearchStrings as $stringKey=>$stringVal) {
		if($v->innerHtml == $stringKey) {
			$surfValue = $statusBlob[$k+1]->innerHtml;
			if($stringVal != null && $surfValue != $stringVal)
				syslog(LOG_WARNING, "WARNING >> " . $stringKey . " has an unexpected value of " . $surfValue);
			else
				syslog(LOG_INFO, "INFO >> " . $stringKey . " reports: " . $surfValue);
		}
	}
}

//SEARCH THE ADDRESSES PAGE

$signalDom->loadFromUrl('http://192.168.100.1/cmSignalData.htm');

// Set search fields. If arrays are found, we assume first value is min, second value is max, and third value is unit. This should be wrapped in a class at some point.


// Start with downstream values

$downstreamTable = $signalDom->find('center')[0]->innerHtml;
$dstBondingChannelCount = 0;

$dstDom->loadStr($downstreamTable, []);
$dstTableHeads = $dstDom->find('th');

foreach($dstTableHeads as $dstHeadVal) {
	$columnTitle = $dstHeadVal->find('font')->innerHtml;
	if($columnTitle == "Bonding Channel Value")
		$dstBondingChannelCount = $dstHeadVal->getAttribute('colspan');
}

$tableRows = $dstDom->find('tr');

foreach($tableRows as $row) {
	
	$firstCell = $currentCell = $row->firstChild();

	if (ctype_space($firstCell->innerHtml))
		continue;
	
	$contents = $firstCell->firstChild();
	
	foreach ($signalSearchStrings['downstream'] as $dstRow=>$dstVal) {
		if(is_array($dstVal)) {
			$minVal = $dstVal[0];
			$maxVal = $dstVal[1];
		}
		
		$logType = "INFO";
		
		$currentCellValue = $contents->innerHtml;
		
		$bondingChannelArray = [];

		if($currentCellValue == $dstRow) {		
			for($i = 0; $i < $dstBondingChannelCount+1; $i++) {
				$currentCell = $currentCell->nextSibling();
				if (ctype_space($currentCell->innerHtml))
					continue;
				
				$filteredVal = filter_var($currentCell->innerHtml, FILTER_SANITIZE_NUMBER_INT);
				if(is_array($dstVal) && ($filteredVal < $dstVal[0] || $filteredVal > $dstVal[1]))
					$logType = "WARNING";
				
				array_push($bondingChannelArray, trim(preg_replace("/&#?[a-z0-9]+;/i","",$currentCell->innerHtml)));
			}
		}
		if($bondingChannelArray) {
			if($logType == "INFO")
				syslog(LOG_INFO, $logType . " >> Downstream " . $currentCellValue . " reports: " . implode(', ', $bondingChannelArray));
			else
				syslog(LOG_WARNING, $logType . " >> Downstream " . $currentCellValue . " reports: " . implode(', ', $bondingChannelArray));
		}
	}
}


//Start with upstream values

$upstreamTable = $signalDom->find('center')[1]->innerHtml;

$ustDom->loadStr($upstreamTable, []);
$ustTableHeads = $ustDom->find('th');

foreach($ustTableHeads as $ustHeadVal) {
	$columnTitle = $ustHeadVal->find('font')->innerHtml;
	if($columnTitle == "Bonding Channel Value")
		$ustBondingChannelCount = $ustHeadVal->getAttribute('colspan');
}

$tableRows = $ustDom->find('tr');

foreach($tableRows as $row) {
	
	$firstCell = $currentCell = $row->firstChild();

	if (ctype_space($firstCell->innerHtml))
		continue;
	
	$contents = $firstCell->firstChild();
	
	foreach ($signalSearchStrings['upstream'] as $ustRow=>$ustVal) {
		if(is_array($ustVal)) {
			$minVal = $ustVal[0];
			$maxVal = $ustVal[1];
		}
		
		$logType = "INFO";
		
		$currentCellValue = $contents->innerHtml;
		
		$bondingChannelArray = [];

		if($currentCellValue == $ustRow) {		
			for($i = 0; $i < $ustBondingChannelCount+1; $i++) {
				$currentCell = $currentCell->nextSibling();
				if (ctype_space($currentCell->innerHtml))
					continue;
				
				$filteredVal = filter_var($currentCell->innerHtml, FILTER_SANITIZE_NUMBER_INT);
				if(is_array($ustVal) && ($filteredVal < $ustVal[0] || $filteredVal > $ustVal[1]))
					$logType = "WARNING";
				
				array_push($bondingChannelArray, trim(preg_replace("/&#?[a-z0-9]+;/i","",$currentCell->innerHtml)));
			}
		}
		if($bondingChannelArray) {
			if($logType == "INFO")
				syslog(LOG_INFO, $logType . " >> Upstream " . $currentCellValue . " reports: " . implode(', ', $bondingChannelArray));
			else
				syslog(LOG_WARNING, $logType . " >> Upstream " . $currentCellValue . " reports: " . implode(', ', $bondingChannelArray));
		}
	}
}
?>