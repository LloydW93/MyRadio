<?php
/**
 * Gives a User a Training Status.
 */
use \MyRadio\MyRadio\URLUtils;
use \MyRadio\ServiceAPI\MyRadio_User;
use \MyRadio\ServiceAPI\MyRadio_TrainingStatus;
use \MyRadio\ServiceAPI\MyRadio_UserTrainingStatus;

MyRadio_UserTrainingStatus::create(
    MyRadio_TrainingStatus::getInstance($_POST['status_id']),
    MyRadio_User::getInstance($_POST['memberid'])
);

URLUtils::backWithMessage('Training data updated');
