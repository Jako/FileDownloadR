<?php
/**
 * Resolve access policies
 *
 * @package filedownloadr
 * @subpackage build
 *
 * @var array $options
 * @var xPDOObject $object
 */

$accessPolicies = [
    [
        'policy' => [
            'name' => 'FileDownloadR',
            'description' => 'Create, upload, view, list, update and remove files.',
            'lexicon' => 'permissions'
        ],
        'template' => [
            'name' => 'AdministratorTemplate',
        ],
        'permissions' => [
            'directory_create',
            'directory_list',
            'directory_remove',
            'directory_update',
            'file_list',
            'file_remove',
            'file_update',
            'file_upload',
            'file_view',
            'file_create',
        ]
    ]
];

/**
 * @param modX $modx
 * @param array $policy
 * @param array $template
 * @param string $permission
 * @return bool
 */
function createAccessPolicy($modx, $policy, $template, $permission)
{
    /** @var modAccessPolicyTemplate $accessPolicyTemplate */
    if (!$accessPolicyTemplate = $modx->getObject('modAccessPolicyTemplate', [
        'name' => $template['name']
    ])
    ) {
        $modx->log(xPDO::LOG_LEVEL_INFO, 'Access Policy Template "' . $template['name'] . '" not available.');
        return false;
    }

    if (!$modx->getObject('modAccessPermission', [
        'name' => $permission,
        'template' => $accessPolicyTemplate->get('id')
    ])) {
        $modx->log(xPDO::LOG_LEVEL_INFO, 'Access Permission "' . $permission . '" not available in template "' . $accessPolicyTemplate->get('name') . '".');
        return false;
    }

    /** @var modAccessPolicy $accessPolicy */
    if (!$accessPolicy = $modx->getObject('modAccessPolicy', [
        'name' => $policy['name']
    ])
    ) {
        $accessPolicy = $modx->newObject('modAccessPolicy');
        $accessPolicy->fromArray([
            'name' => $policy['name'],
            'description' => $policy['description'],
            'data' => [$permission => true],
            'lexicon' => $policy['lexicon']
        ]);
        $accessPolicy->addOne($accessPolicyTemplate, 'Template');
        $modx->log(xPDO::LOG_LEVEL_INFO, 'Access Policy "' . $policy['name'] . '" created.');
    } else {
        $data = $accessPolicy->get('data');
        $data = ($data) ? array_merge($data, [$permission => true]) : [$permission => true];
        $accessPolicy->set('data', $data);
        $modx->log(xPDO::LOG_LEVEL_INFO, 'Access Policy "' . $policy['name'] . '" updated.');
    }
    $accessPolicy->save();

    return true;
}

/**
 * @param modX $modx
 * @param array $policy
 * @return bool
 */
function removeAccessPolicy($modx, $policy)
{
    /** @var modAccessPermission $accessPermission */
    if ($accessPolicy = $modx->getObject('modAccessPolicy', ['name' => $policy['name']])) {
        $accessPolicy->remove();
        $modx->log(xPDO::LOG_LEVEL_INFO, 'Access Policy "' . $policy['name'] . '" removed.');
    }
    return true;
}

$success = true;
if ($object->xpdo) {
    /** @var modX $modx */
    $modx = &$object->xpdo;
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            foreach ($accessPolicies as $accessPolicy) {
                foreach ($accessPolicy['permissions'] as $accessPermission) {
                    $result = createAccessPolicy($modx, $accessPolicy['policy'], $accessPolicy['template'], $accessPermission);
                    $success = $success && $result;
                }
            }

            break;
        case xPDOTransport::ACTION_UNINSTALL:
            foreach ($accessPolicies as $accessPolicy) {
                $result = removeAccessPolicy($modx, $accessPolicy['policy']);
                $success = $success && $result;
            }
            break;
    }
}
return $success;
