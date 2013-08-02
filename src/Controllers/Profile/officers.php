<?php

/**
 * This provides similar information to listOfficers, but in a far nicer format.
 * 
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 20130802
 * @package MyURY_Profile
 */
$officers = Profile::getOfficers();

foreach ($officers as $k => $v) {
  if (!empty($officers[$k]['name'])) {
    $officers[$k]['url'] = CoreUtils::makeURL('Profile', 'view', array('memberid' => $v['memberid']));
  }
  
  if (!empty($officers[$k]['memberid'])) {
    $image = User::getInstance($officers[$k]['memberid'])->getProfilePhoto();
    $officers[$k]['image'] = $image !== null ? $image->getURL() : Config::$default_person_uri;
  } else {
    $officers[$k]['image'] = Config::$default_person_uri;
  }
}

CoreUtils::getTemplateObject()->setTemplate('Profile/officers.twig')
        ->addVariable('title', 'URY Committee')
        ->addVariable('officers', $officers)
        ->render();