<?php

/**
 * @package     Crowley-Hammer20k
 * @author      Gabriel Mioni <gabriel@gabrielmioni.com>
 */

/**
 * This file is used as the target of a daily cronjob.
 *
 * If the $_GET['req'] value is set for the correct secret code, the file will create a new company_data_controller
 * object, which will update the json.txt file.
 */

require_once('company_data_controller.php');

/*  When called during the cron job, the $secret_code value must be present and it must be included in the
 *  cron job query string. */

$secret_code = '******************'; // <- Set this with a real password.

$request = isset($_GET['req']) ? htmlspecialchars($_GET['req']) : null;

if ($request === $secret_code)
{
    new company_data_controller();
}