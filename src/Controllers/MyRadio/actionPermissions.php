<?php
/**
 * Provides a tool to manage permissions for MyRadio Service/Module/Action systems
 *
 * @version 20120723
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @package MyRadio_Core
 */

/**
 * Form definition for adding permissions.
 */
$form = new MyRadioForm(
    'assign_action_permissions',
    $module,
    $action,
    array(
        'debug' => true,
        'title' => 'Assign Action Permissions'
    )
);

$form->addField(
    new MyRadioFormField(
        'service',
        MyRadioFormField::TYPE_SELECT,
        array(
            'options' => CoreUtils::getServices(),
            'explanation' => 'Select a Service to apply permissions to',
            'label' => 'Service'
        )
    )
)->addField(
    new MyRadioFormField(
        'module',
        MyRadioFormField::TYPE_TEXT,
        array(
            'explanation' => 'Type a Module to apply permissions to',
            'label' => 'Module'
        )
    )
)->addField(
    new MyRadioFormField(
        'action',
        MyRadioFormField::TYPE_TEXT,
        array(
            'explanation' => 'Type an Action within that Module to apply permissions to. '
                .'Leave blank to apply it to all Actions.',
            'label' => 'Action',
            'required' => false
        )
    )
)->addField(
    new MyRadioFormField(
        'permission',
        MyRadioFormField::TYPE_SELECT,
        array(
            'explanation' => 'Select a permission that you want to add which when granted '
                .'allows a user to perform this Action. These use boolean OR, not AND so may not '
                .'stack as you would like depending on circumstances. Leave blank to allow global '
                .'access.',
            'label' => 'Permission',
            'required' => false,
            'options' => array_merge(
                array(
                    array(
                        'value' => null,
                        'text' => 'GLOBAL ACCESS'
                    )
                ),
                CoreUtils::getAllPermissions()
            )
        )
    )
);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //Submitted
    $data = $form->readValues();

    $setModule = CoreUtils::getModuleId($data['module']);
    $setAction = CoreUtils::getActionId($setModule, $data['action']);
    $permission = $data['permission'];
    if (empty($setAction)) {
        $setAction = null;
    }
    if (empty($permission)) {
        $permission = null;
    }

    CoreUtils::addActionPermission($setModule, $setAction, $permission);

    CoreUtils::backWithMessage('The action permission has been updated.');

} else {
    //Not Submitted
    //Include the current permissions. This will be rendered in a DataTable.
    $data = CoreUtils::getAllActionPermissions();

    /**
     * Pass it over to the actionPermissions view for output.
     */
    for ($i = 0; $i < sizeof($data); $i++) {
        $data[$i]['del'] = array(
            'display' => 'text',
            'url' => CoreUtils::makeURL(
                'Core',
                'removeActionPermission',
                array('permissionid' => $data[$i]['actpermissionid'])
            ),
            'value' => 'Delete'
        );
    }
    $form->setTemplate('MyRadio/actionPermissions.twig')
        ->render(array(
            'tabledata' => $data,
            'tablescript' => 'myury.core.actionPermissions'
        ));
}
