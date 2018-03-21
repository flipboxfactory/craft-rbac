<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-rbac/blob/master/LICENSE.md
 * @link       https://github.com/flipboxfactory/craft-rbac
 */

namespace flipbox\craft\rbac;

use craft\db\Connection;
use yii\base\InvalidArgumentException;
use yii\base\InvalidCallException;
use yii\rbac\Assignment;
use yii\rbac\Permission;
use yii\rbac\Role;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @property Connection db
 */
class DbManager extends \yii\rbac\DbManager
{
    /**
     * @inheritdoc
     */
    public $itemTable = '{{%rbac_item}}';

    /**
     * @inheritdoc
     */
    public $itemChildTable = '{{%rbac_item_child}}';

    /**
     * @inheritdoc
     */
    public $assignmentTable = '{{%rbac_assignment}}';

    /**
     * @inheritdoc
     */
    public $ruleTable = '{{%rbac_rule}}';

    /**
     * @inheritdoc
     */
    protected function addItem($item)
    {
        $time = time();
        if ($item->createdAt === null) {
            $item->createdAt = $time;
        }
        if ($item->updatedAt === null) {
            $item->updatedAt = $time;
        }
        $this->db->createCommand()
            ->insert($this->itemTable, [
                'name' => $item->name,
                'type' => $item->type,
                'description' => $item->description,
                'rule_name' => $item->ruleName,
                'data' => $item->data === null ? null : serialize($item->data),
                'created_at' => $item->createdAt,
                'updated_at' => $item->updatedAt,
            ], false)->execute();

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function updateItem($name, $item)
    {
        if ($item->name !== $name && !$this->supportsCascadeUpdate()) {
            $this->db->createCommand()
                ->update(
                    $this->itemChildTable,
                    ['parent' => $item->name],
                    ['parent' => $name],
                    [],
                    false
                )
                ->execute();
            $this->db->createCommand()
                ->update(
                    $this->itemChildTable,
                    ['child' => $item->name],
                    ['child' => $name],
                    [],
                    false
                )
                ->execute();
            $this->db->createCommand()
                ->update(
                    $this->assignmentTable,
                    ['item_name' => $item->name],
                    ['item_name' => $name],
                    [],
                    false
                )
                ->execute();
        }

        $item->updatedAt = time();

        $this->db->createCommand()
            ->update($this->itemTable, [
                'name' => $item->name,
                'description' => $item->description,
                'rule_name' => $item->ruleName,
                'data' => $item->data === null ? null : serialize($item->data),
                'updated_at' => $item->updatedAt,
            ], [
                'name' => $name,
            ], [], false)->execute();

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function addRule($rule)
    {
        $time = time();
        if ($rule->createdAt === null) {
            $rule->createdAt = $time;
        }
        if ($rule->updatedAt === null) {
            $rule->updatedAt = $time;
        }
        $this->db->createCommand()
            ->insert($this->ruleTable, [
                'name' => $rule->name,
                'data' => serialize($rule),
                'created_at' => $rule->createdAt,
                'updated_at' => $rule->updatedAt,
            ], false)->execute();

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function updateRule($name, $rule)
    {
        if ($rule->name !== $name && !$this->supportsCascadeUpdate()) {
            $this->db->createCommand()
                ->update(
                    $this->itemTable,
                    ['rule_name' => $rule->name],
                    ['rule_name' => $name],
                    [],
                    false
                )
                ->execute();
        }

        $rule->updatedAt = time();

        $this->db->createCommand()
            ->update($this->ruleTable, [
                'name' => $rule->name,
                'data' => serialize($rule),
                'updated_at' => $rule->updatedAt,
            ], [
                'name' => $name,
            ], [], false)->execute();

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function addChild($parent, $child)
    {
        if ($parent->name === $child->name) {
            throw new InvalidArgumentException(
                "Cannot add '{$parent->name}' as a child of itself."
            );
        }

        if ($parent instanceof Permission && $child instanceof Role) {
            throw new InvalidArgumentException(
                "Cannot add a role as a child of a permission."
            );
        }

        if ($this->detectLoop($parent, $child)) {
            throw new InvalidCallException(
                "Cannot add '{$child->name}' as a child of '{$parent->name}'. A loop has been detected."
            );
        }

        $this->db->createCommand()
            ->insert(
                $this->itemChildTable,
                [
                    'parent' => $parent->name,
                    'child' => $child->name
                ],
                false
            )
            ->execute();

        $this->invalidateCache();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function assign($role, $userId)
    {
        $assignment = new Assignment([
            'userId' => $userId,
            'roleName' => $role->name,
            'createdAt' => time(),
        ]);

        $this->db->createCommand()
            ->insert($this->assignmentTable, [
                'user_id' => $assignment->userId,
                'item_name' => $assignment->roleName,
                'created_at' => $assignment->createdAt,
            ], false)->execute();

        return $assignment;
    }

    /**
     * @inheritdoc
     */
    public function removeAllRules()
    {
        if (!$this->supportsCascadeUpdate()) {
            $this->db->createCommand()
                ->update($this->itemTable, ['rule_name' => null], '', [], false)
                ->execute();
        }

        $this->db->createCommand()->delete($this->ruleTable)->execute();

        $this->invalidateCache();
    }
}
