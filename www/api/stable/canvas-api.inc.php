<?php

require_once('debug.inc.php');

if(!defined('API_CLIENT_ERROR_RETRIES')) {
	define('API_CLIENT_ERROR_RETRIES', 5);
	debug_log('Using default API_CLIENT_ERROR_RETRIES = ' . API_CLIENT_ERROR_RETRIES);
}
if(!defined('API_SERVER_ERROR_RETRIES')) {
	define('API_SERVER_ERROR_RETRIES', API_CLIENT_ERROR_RETRIES * 5);
	debug_log('Using default API_SERVER_ERROR_RETRIES = ' . API_SERVER_ERROR_RETRIES);
}
if(!defined('DEBUGGING')) {
	define('DEBUGGING', DEBUGGING_LOG);
}

define('CANVAS_API_DELETE', 'delete');
define('CANVAS_API_GET', 'get');
// define('CANVAS_API_PATCH', 'patch'); // supported by Pest, but not the Canvas API
define('CANVAS_API_POST', 'post');
define('CANVAS_API_PUT', 'put');

define('CANVAS_TIMESTAMP_FORMAT', 'Y-m-d\TH:iP');

define('TRIM_EXCEPTION_MESSAGE_LENGTH', 50);

/* we use Pest to interact with the RESTful API */
require_once('Pest.php');

/* handles HTML page generation */
require_once('page-generator.inc.php');

/**
 * Generate the Canvas API authorization header
 **/
function buildCanvasAuthorizationHeader() {
	return array ('Authorization' => 'Bearer ' . CANVAS_API_TOKEN);
}

/**
 * Pass-through a Pest request with added Canvas authorization token
 **/
$PEST = new Pest(CANVAS_API_URL);
function callCanvasApi($verb, $url, $data = array()) {
	global $PEST;
	$response = null;

	$clientRetryCount = 0;
	$serverRetryCount = 0;
	do {
		$retry = false;
		try {
			$response = $PEST->$verb($url, $data, buildCanvasAuthorizationHeader());
		} catch (Pest_ServerError $e) {
			/* who knows what goes on in the server's mind... try again */
			$serverRetryCount++;
			$retry = true;
			debug_log('Retrying after Canvas API server error. ' . preg_replace('%(.{0,' . TRIM_EXCEPTION_MESSAGE_LENGTH . '}.+)%', '\\1...', $e->getMessage()));
		} catch (Pest_ClientError $e) {
			/* I just watched the Canvas API throw an unauthorized error when, in fact,
			   I was authorized. Everything gets retried a few times before I give up */
			$clientRetryCount++;
			$retry = true;
			debug_log('Retrying after Canvas API client error. ' . preg_replace('%(.{0,' . TRIM_EXCEPTION_MESSAGE_LENGTH . '}.+)%', '\\1...', $e->getMessage())); 
		} catch (Exception $e) {
			displayError(
				array(
					'Error' => $e->getMessage(),
					'Verb' => $verb,
					'URL' => $url,
					'Data' => $data
				),
				true,
				'API Error',
				'Something went awry in the API'
			);
			exit;
		}
	} while ($retry == true && $clientRetryCount < API_CLIENT_ERROR_RETRIES && $serverRetryCount < API_SERVER_ERROR_RETRIES);
	
	if ($clientRetryCount == API_CLIENT_ERROR_RETRIES) {
		displayError(
			array(
				'Status' => $PEST->lastStatus(),
				'Error' => $PEST->lastBody(),
				'Verb' => $verb,
				'URL' => $url,
				'Data' => $data
			),
			true,
			'Probable Client Error',
			'After trying ' . API_CLIENT_ERROR_RETRIES . ' times, we still got this error message from the API.'
		);
		exit;
	}
	
	if ($serverRetryCount == API_SERVER_ERROR_RETRIES) {
		displayError(
			array(
				'Status' => $PEST->lastStatus(),
				'Error' => $PEST->lastBody(),
				'Verb' => $verb,
				'URL' => $url,
				'Data' => $data
			),
			true,
			'Probable Server Error',
			'After trying ' . API_CLIENT_ERROR_RETRIES . ' times, we still got this error message from the API.'
		);
		exit;
	}
	
	$responseArray = json_decode($response, true);
	
	displayError(
		array(
			'API Call' => array(
				'Verb' => $verb,
				'URL' => $url,
				'Data' => $data
			),
			'Response' => $responseArray
		),
		true,
		'API Call',
		null,
		DEBUGGING_CANVAS_API
	);
	
	return $responseArray;
}

?>