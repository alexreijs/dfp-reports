
<?php
/**
 * This example runs a reach report.
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

date_default_timezone_set('Europe/Amsterdam');

// You can set the include path to src directory or reference
// DfpUser.php directly via require_once.
// $path = '/path/to/dfp_api_php_lib/src';
$path = dirname(__FILE__) . '/lib';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

require_once 'Google/Api/Ads/Dfp/Lib/DfpUser.php';
require_once 'Google/Api/Ads/Dfp/Util/v201508/ReportDownloader.php';
require_once 'Google/Api/Ads/Dfp/Util/v201508/StatementBuilder.php';
require_once dirname(__FILE__) . '/examples/Common/ExampleUtils.php';

try {
  // Get DfpUser from credentials in "../auth.ini"
  // relative to the DfpUser.php file's directory.
  $user = new DfpUser();

  // Log SOAP XML request and response.
  $user->LogDefaults();

  // Get the ReportService.
  $reportService = $user->GetService('ReportService', 'v201508');

  // Create report query.
  $reportQuery = new ReportQuery();
  $reportQuery->dimensions = array('LINE_ITEM_ID'); //, 'CREATIVE_SIZE'); //, 'AD_UNIT_ID', 'AD_UNIT_NAME'); //, 'TARGETING', 'CUSTOM_TARGETING_VALUE_ID');
  //$reportQuery->dimensionAttributes = array('LINE_ITEM_GOAL_QUANTITY', 'LINE_ITEM_PRIORITY', 'LINE_ITEM_LIFETIME_IMPRESSIONS', 'LINE_ITEM_LIFETIME_CLICKS');
  $reportQuery->columns = array('AD_SERVER_DELIVERY_INDICATOR');

  // Create statement to filter for an order.
  //$statementBuilder = new StatementBuilder();
  //$statementBuilder->Where('LINE_ITEM_ID =:lineItemId')->WithBindVariableValue('lineItemId', 229412960); //224008040

  // Set the filter statement.
  //$reportQuery->statement = $statementBuilder->ToStatement();

  // Set the dynamic date range type or a custom start and end date that is
  // the beginning of the week (Sunday) to the end of the week (Saturday), or
  // the first of the month to the end of the month.
  $reportQuery->dateRangeType = 'TODAY';

  // Create report job.
  $reportJob = new ReportJob();
  $reportJob->reportQuery = $reportQuery;

  // Run report job.
  $reportJob = $reportService->runReportJob($reportJob);

  // Create report downloader.
  $reportDownloader = new ReportDownloader($reportService, $reportJob->id);

  printf("Waiting for report to be ready ...\n");

  // Wait for the report to be ready.
  $reportDownloader->waitForReportReady();

  // Change to your file location.
  $filePath = sprintf('%s.csv.gz', 'line-item-attributes-' . date('m-d-Y_H-i'));

  printf("Downloading report to %s ...\n", $filePath);

  // Download the report.
  $reportDownloader->downloadReport('CSV_DUMP', $filePath);

  // Unzip report
  $unzipped = gzdecode(file_get_contents($filePath));
  printf("Unzipping report...\n", $filePath);


  // Save including date
  //$fn = substr($filePath, 0, -3);
  //$fp = fopen($fn, "a");
  //fwrite($fp, $unzipped);
  //fclose($fp);


  // Save excluding date
  $fn = "./line-item-attributes.csv";
  $fp = fopen($fn, "a");
  fwrite($fp, $unzipped);
  fclose($fp);


  printf("Uploading to Google Storage\n");

  $shell = shell_exec("/usr/local/bin/gsutil cp line-item-attributes*.csv gs://api-hub-output/dfp-reports/ad-operations/line-item-attributes/");
  //foreach($shell as $id => $sh) {
  //  printf($sh);
  //}

  printf("Deleting downloaded files\n");
  shell_exec("rm line-item-attributes*.csv & rm line-item-attributes*.csv.gz");



  printf("Done.\n");

} catch (OAuth2Exception $e) {
  ExampleUtils::CheckForOAuth2Errors($e);
} catch (ValidationException $e) {
  ExampleUtils::CheckForOAuth2Errors($e);
} catch (Exception $e) {
  printf("%s\n", $e->getMessage());
}

