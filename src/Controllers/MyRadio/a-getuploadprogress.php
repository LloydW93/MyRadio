<?php

/**
 * Returns the APC upload progress data for the given upload ID.
 */
use \MyRadio\MyRadio\URLUtils;

if (function_exists('uploadprogress_get_info')) {
    $data = uploadprogress_get_info($_REQUEST['id']);
} else {
    trigger_error('uploadprogress PECL extension is not installed.');
    $data = false;
}

URLUtils::dataToJSON($data);
