<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-rbac/blob/master/LICENSE.md
 * @link       https://github.com/flipboxfactory/craft-rbac
 */

namespace flipbox\craft\rbac\migrations;

use Craft;
use craft\db\Migration;
use flipbox\craft\rbac\DbManager;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Install extends Migration
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
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        require Craft::$app->getVendorPath() . "/yiisoft/yii2/rbac/migrations/m140506_102106_rbac_init.php";
    }

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        (new InitRBAC($this->authManager))
            ->up();

        $tables = [
            $this->authManager->itemTable,
            $this->authManager->assignmentTable,
            $this->authManager->itemChildTable,
            $this->authManager->ruleTable
        ];

        foreach ($tables as $table) {
            $this->addColumn(
                $table,
                'uid',
                $this->uid()
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        (new InitRBAC($this->authManager))
            ->down();

        return true;
    }
}
