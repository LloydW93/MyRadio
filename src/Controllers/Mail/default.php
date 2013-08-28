<?php
/**
 * Lists all mailing lists
 * 
 * @todo Datatable niceness
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 20130526
 * @package MyURY_Mail
 */

CoreUtils::getTemplateObject()->setTemplate('table.twig')
        ->addVariable('tablescript', 'myury.mail.default')
        ->addVariable('title', 'All Mailing Lists')
        ->addVariable('tabledata', CoreUtils::dataSourceParser(MyURY_List::getAllLists()))
        ->addInfo('You will only get any messages from URY if you are set to "Receive Email" on your <a href="'.
                CoreUtils::makeURL('Profile','edit').'">profile</a>.')
        ->render();
