<?php

/**
 * Instantiate controller
 */

/**
 * Patch system eg. for flash upload
 * This allows to transmit Session-ID and User-Auth Key using GET-Paramenters
 */
@ini_set('session.use_only_cookies', '0');
if (isset($_GET['FE_USER_AUTH']))
{
    $_COOKIE['FE_USER_AUTH'] = $_GET['FE_USER_AUTH'];
}

// ajax.php is a frontend script
define('TL_MODE', 'FE');

// Start the session so we can access known request tokens
@session_start();

// Allow do bypass the token check if a known token is passed in
if (isset($_GET['bypassToken']) && ((is_array($_SESSION['REQUEST_TOKEN'][TL_MODE]) && in_array($_POST['REQUEST_TOKEN'], $_SESSION['REQUEST_TOKEN'][TL_MODE])) || $_SESSION['REQUEST_TOKEN'][TL_MODE] == $_POST['REQUEST_TOKEN']))
{
    define('BYPASS_TOKEN_CHECK', true);
}

// Initialize for Contao <= 2.9
elseif (!isset($_SESSION['REQUEST_TOKEN']))
{
    $arrPOST = $_POST;
    unset($_POST);
}

// Close session so Contao's initalization routine can use ini_set()
session_write_close();

// Initialize the system
if(strpos(dirname(__DIR__),"system")!==false)
    require(dirname(dirname(dirname(__DIR__))) . '/initialize.php');
elseif(strpos(dirname(__DIR__),"composer")!==false)
    require(dirname(dirname(dirname(dirname(dirname(__DIR__))))).'system/initialize.php');


// Preserve $_POST data in Contao <= 2.9
if (version_compare(VERSION, '2.10', '<'))
{
    $_POST = $arrPOST;
}

$objPageAjax = new PageAjax();
$objPageAjax->run();