<?php
/**
 * The most listened to timeslots this academic year
 * 
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 20130626
 * @package MyRadio_Stats
 */
CoreUtils::getTemplateObject()->setTemplate('table.twig')
        ->addVariable('title', 'Most listened to shows this academic year')
        ->addVariable('tabledata', MyRadio_Show::getMostListened(strtotime(CoreUtils::getAcademicYear().'-09-01')))
        ->addVariable('tablescript', 'myury.datatable.default')
        ->render();