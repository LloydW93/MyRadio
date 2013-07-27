<?php
/**
 * 
 * @todo Proper Documentation
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 20130728
 * @package MyURY_Scheduler
 */

//The Form definition
require 'Models/Scheduler/showfrm.php';

try {
  MyURY_Show::create($form->readValues());
} catch (MyURYException $e) {
  require 'Views/Errors/500.php';
  exit;
}

header('Location: '.CoreUtils::makeURL('Scheduler', 'myShows'));