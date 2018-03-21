<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-rbac/blob/master/LICENSE.md
 * @link       https://github.com/flipboxfactory/craft-rbac
 */

namespace flipbox\craft\rbac\migrations;

use flipbox\craft\rbac\DbManager;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class InitRBAC extends \m140506_102106_rbac_init
{
    /**
     * @var DbManager
     */
    private $authManager;

    /**
     * @param DbManager $authManager
     * @param array $config
     */
    public function __construct(DbManager $authManager, array $config = [])
    {
        $this->authManager = $authManager;
        parent::__construct($config);
    }

    /**
     * @return DbManager|\yii\rbac\DbManager
     */
    protected function getAuthManager()
    {
        return $this->authManager;
    }
}
