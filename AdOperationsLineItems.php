<?php
/**
 * This example gets all line items. To create line items, run
 * CreateLineItems.php.
 *
 * PHP version 5
 *
 * Copyright 2014, Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package    GoogleApiAdsDfp
 * @subpackage v201508
 * @category   WebServices
 * @copyright  2014, Google Inc. All Rights Reserved.
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache License,
 *             Version 2.0
 */
error_reporting(E_STRICT | E_ALL);

// You can set the include path to src directory or reference
// DfpUser.php directly via require_once.
// $path = '/path/to/dfp_api_php_lib/src';
$path = dirname(__FILE__) . '/lib';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

require_once 'Google/Api/Ads/Dfp/Lib/DfpUser.php';
require_once 'Google/Api/Ads/Dfp/Util/v201508/StatementBuilder.php';
require_once 'Google/Api/Ads/Dfp/Util/v201508/DateTimeUtils.php';
require_once dirname(__FILE__) . '/examples/Common/ExampleUtils.php';

try {
  // Get DfpUser from credentials in "../auth.ini"
  // relative to the DfpUser.php file's directory.
  $user = new DfpUser();

  // Log SOAP XML request and response.
  $user->LogDefaults();

  // Get DateUtils
  $dateTimeUtils = new DateTimeUtils();

  // Get the LineItemService.
  $lineItemService = $user->GetService('LineItemService', 'v201508');

  // Create a statement to select all line items.
  $statementBuilder = new StatementBuilder();
  $statementBuilder->OrderBy('id DESC')
      ->Limit(StatementBuilder::SUGGESTED_PAGE_LIMIT);

  $statementBuilder->Where("EndDateTime >= '" . date('Y-m-d', time() - 86400 * 7) . "'");
  //$statementBuilder->Limit("100");

  // Default for total result set size.
  $totalResultSetSize = 0;

  printf("Downloading and saving results to file\n");

  $fn = "./line-items.csv";
  $fp = fopen($fn, "a");

  fwrite($fp, implode(',', array(
	'orderId',
	'orderName',
	'lineItemId',
	'lineItemName',
	'externalId',
	'creationDateTime',
	'startDateTime',
	'endDateTime',
	'priority',
	'costType',
	'lineItemType',
	'impressionsDelivered',
	'clicksDelivered',
	'expectedDeliveryPercentage',
	'actualDeliveryPercentage',
	'status',
	'notes',
	'isMissingCreatives',
	'primaryGoalType',
	'primaryGoalUnitType',
	'primaryGoalUnits'
  )) . "\n");

  do {
    // Get line items by statement.
    $page = $lineItemService->getLineItemsByStatement(
        $statementBuilder->ToStatement());

    // Save results.


    if (isset($page->results)) {

      $totalResultSetSize = $page->totalResultSetSize;
      $i = $page->startIndex;
      foreach ($page->results as $lineItem) {
	//print_r(get_object_vars($lineItem)); exit;
	//print_r($lineItem->targeting);

	if (is_null($lineItem->creationDateTime))
		$creationDateTimeString = null;
	else {
		$creationDateTime = $dateTimeUtils->FromDfpDateTime($lineItem->creationDateTime);
		$creationDateTime->setTimezone(new DateTimeZone('Europe/Amsterdam'));
		$creationDateTimeString = $creationDateTime->format('Y-m-d H:i:s');
	}

	if (is_null($lineItem->startDateTime))
		$startDateTimeString = null;
	else {
		$startDateTime = $dateTimeUtils->FromDfpDateTime($lineItem->startDateTime);
		$startDateTime->setTimezone(new DateTimeZone('Europe/Amsterdam'));
		$startDateTimeString = $startDateTime->format('Y-m-d H:i:s');
	}

	if (is_null($lineItem->endDateTime))
		$endDateTimeString = null;
	else {
		$endDateTime = $dateTimeUtils->FromDfpDateTime($lineItem->endDateTime);
		$endDateTime->setTimezone(new DateTimeZone('Europe/Amsterdam'));
		$endDateTimeString = $endDateTime->format('Y-m-d H:i:s');
	}

	$columns = array(
		$lineItem->orderId,
		'"' . str_replace('"', '""', $lineItem->orderName) . '"',
		$lineItem->id,
		'"' . str_replace('"', '""', $lineItem->name) . '"',
		$lineItem->externalId,
		$creationDateTimeString,
		$startDateTimeString,
		$endDateTimeString,
		$lineItem->priority,
		$lineItem->costType,
		$lineItem->lineItemType,
		is_null($lineItem->stats) ? 0 : $lineItem->stats->impressionsDelivered,
		is_null($lineItem->stats) ? 0 : $lineItem->stats->clicksDelivered,
		is_null($lineItem->deliveryIndicator) ? null : $lineItem->deliveryIndicator->expectedDeliveryPercentage,
		is_null($lineItem->deliveryIndicator) ? null : $lineItem->deliveryIndicator->actualDeliveryPercentage,
		$lineItem->status,
		is_null($lineItem->notes) ? null : '"' . str_replace('"', '""', preg_replace("/[\n\r]/", " ", $lineItem->notes)) . '"',
		$lineItem->isMissingCreatives,
		$lineItem->primaryGoal->goalType,
		$lineItem->primaryGoal->unitType,
		$lineItem->primaryGoal->units
        );

	foreach ($columns as $i => $column) {
		$line = $column . ($i == count($columns) - 1 ? "\n" : ",");
		fwrite($fp, $line);
	}
      }

    }

    $statementBuilder->IncreaseOffsetBy(StatementBuilder::SUGGESTED_PAGE_LIMIT);
  } while ($statementBuilder->GetOffset() < $totalResultSetSize);

  fclose($fp);

  //shell_exec("cp line-items.csv " . sprintf('%s.csv', 'line-items-' . date('m-d-Y_H-i')));

  printf("Uploading to Google Storage\n");
  $shell = shell_exec("/usr/local/bin/gsutil cp line-items*.csv gs://api-hub-output/dfp-reports/ad-operations/line-items/");

  printf("Deleting downloaded files\n");
  shell_exec("rm line-items*.csv");


  printf("Number of results found: %d\n", $totalResultSetSize);
} catch (OAuth2Exception $e) {
  ExampleUtils::CheckForOAuth2Errors($e);
} catch (ValidationException $e) {
  ExampleUtils::CheckForOAuth2Errors($e);
} catch (Exception $e) {
  printf("%s\n", $e->getMessage());
}
