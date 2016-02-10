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

  $statementBuilder->Where("EndDateTime >= '" . date('Y-m-d', time()) . "'");
  $statementBuilder->Limit("10");

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
	'startDateTime',
	'endDateTime'
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

	$line = implode(',', array(
		$lineItem->orderId,
		'"' . $lineItem->orderName . '"',
		$lineItem->id,
		'"' . $lineItem->name . '"',
		$lineItem->externalId,
		$dateTimeUtils->FromDfpDateTime($lineItem->startDateTime)->format('Y-m-d H:i:s'),
		is_null($lineItem->endDateTime) ? '' : $dateTimeUtils->FromDfpDateTime($lineItem->endDateTime)->format('Y-m-d H:i:s')
        ));
	fwrite($fp, $line . "\n");
      }

    }

    $statementBuilder->IncreaseOffsetBy(StatementBuilder::SUGGESTED_PAGE_LIMIT);
  } while ($statementBuilder->GetOffset() < $totalResultSetSize);

  fclose($fp);

  shell_exec("cp line-items.csv " . sprintf('%s.csv', 'line-items-' . date('m-d-Y_H-i')));

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
