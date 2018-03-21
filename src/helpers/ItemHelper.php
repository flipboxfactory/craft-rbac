<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-rbac/blob/master/LICENSE.md
 * @link       https://github.com/flipboxfactory/craft-rbac
 */

namespace flipbox\craft\rbac\helpers;

use craft\helpers\ArrayHelper;
use yii\db\Query;
use yii\rbac\Assignment;
use yii\rbac\DbManager;
use yii\rbac\Item;
use yii\rbac\Permission;
use yii\rbac\Role;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class ItemHelper
{

    /**
     * @param array $available
     * @param array $inUse
     * @return array
     */
    public static function getDiffItems(array $available, array $inUse)
    {
        // Re-index
        $available = ArrayHelper::index($available, 'name');

        /** @var Item $item */
        foreach ($inUse as $item) {
            ArrayHelper::remove($available, $item->name);
        }

        return array_values($available);
    }

    /**
     * @param string $name
     * @param DbManager $authManager
     * @return array
     */
    public static function getItemsThatMatchBeginning(string $name, DbManager $authManager)
    {
        $items = [];

        $parts = explode(".", $name);
        $activeParts = [];
        foreach ($parts as $part) {
            array_push($activeParts, $part);
            $rows = (new Query())->from($authManager->itemTable)
                ->andWhere(['name' => implode(".", $activeParts)])
                ->andWhere(['!=', 'name', $name])
                ->all($authManager->db);

            foreach ($rows as $row) {
                $items[$row['name']] = static::populate($row);
            }
        }

        return $items;
    }

    /**
     * @param string $name
     * @param DbManager $authManager
     * @return array
     */
    public static function getItemsThatBeginWith(string $name, DbManager $authManager)
    {
        $rows = (new Query())->from($authManager->itemTable)
            ->where(['like', 'name', $name.'%', false])
            ->andWhere(['!=', 'name', $name])
            ->all($authManager->db);

        $items = [];

        foreach ($rows as $row) {
            $items[] = static::populate($row);
        }

        return $items;
    }

    /**
     * @param string $name
     * @param DbManager $authManager
     * @return null|Item
     */
    public static function findItem(string $name, DbManager $authManager)
    {
        if (empty($name)) {
            return null;
        }

        $row = (new Query())->from($authManager->itemTable)
            ->where(['name' => $name])
            ->one($authManager->db);

        if ($row === false) {
            return null;
        }

        if (!isset($row['data']) ||
            ($data = @unserialize(is_resource($row['data']) ?
                stream_get_contents($row['data']) :
                $row['data'])
            ) === false
        ) {
            $data = null;
        }

        return new Item([
            'name' => $row['name'],
            'type' => $row['type'],
            'description' => $row['description'],
            'ruleName' => $row['rule_name'],
            'data' => $data,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ]);
    }

    /**
     * @param string $name
     * @param DbManager $authManager
     * @return Item|Permission|Role|null
     */
    public static function find(string $name, DbManager $authManager)
    {
        if (empty($name)) {
            return null;
        }

        $row = (new Query())->from($authManager->itemTable)
            ->where(['name' => $name])
            ->one($authManager->db);

        if ($row === false) {
            return null;
        }

        return static::populate($row);
    }

    /**
     * @param string $name
     * @param DbManager $authManager
     * @return Item|Permission|Role
     */
    public static function get(string $name, DbManager $authManager)
    {
        return static::find($name, $authManager);
    }

    /**
     * @param string $name
     * @param DbManager $authManager
     * @return Item[]
     */
    public static function getParents(string $name, DbManager $authManager)
    {
        $rows = (new Query())->select(['i.*'])
            ->from($authManager->itemChildTable . ' c')
            ->innerJoin($authManager->itemTable . ' i', 'c.parent=i.name')
            ->where(['child' => $name])
            ->all($authManager->db);

        $items = [];

        foreach ($rows as $row) {
            $items[] = static::populate($row);
        }

        return $items;
    }

    /**
     * @param DbManager $authManager
     * @return Item[]
     */
    public static function getTopLevelItems(DbManager $authManager)
    {
        $rows = (new Query())->select(['i.*', 'c.parent', 'c.child'])
            ->from($authManager->itemTable . ' i')
            ->leftJoin($authManager->itemChildTable . ' c', 'c.child=i.name')
            ->where(['child' => null])
            ->all($authManager->db);

        $items = [];
        foreach ($rows as $row) {
            $items[] = static::populate($row);
        }

        return $items;
    }

    /**
     * @param string $name
     * @param DbManager $authManager
     * @return Item[]
     */
    public static function getChildren(string $name, DbManager $authManager)
    {
        return $authManager->getChildren($name);
    }

    /**
     * @param string $name
     * @param DbManager $authManager
     * @return Role[]
     */
    public static function getParentRoles(string $name, DbManager $authManager)
    {
        $query = new Query();
        $rows = $query->select(['i.*'])
            ->from($authManager->itemChildTable . ' c')
            ->innerJoin($authManager->itemTable . ' i', 'c.parent=i.name')
            ->where(['child' => $name])
            ->andWhere(['type' => Item::TYPE_ROLE])
            ->all($authManager->db);

        $items = [];

        foreach ($rows as $row) {
            $items[] = static::populate($row);
        }

        return $items;
    }

    /**
     * @param string $name
     * @param DbManager $authManager
     * @return Assignment[]
     */
    public static function getAssignments(string $name, DbManager $authManager)
    {
        $assignments = [];
        if ($parents = static::getParentRoles($name, $authManager)) {
            foreach ($parents as $item) {
                $assignments += static::getAssignments($item->name, $authManager);
            }
        }

        $query = (new Query)
            ->from($authManager->assignmentTable)
            ->where(['item_name' => (string) $name]);


        foreach ($query->all($authManager->db) as $row) {
            $assignments[$name][$row['user_id']] = new Assignment([
                'userId' => $row['user_id'],
                'roleName' => $row['item_name'],
                'createdAt' => $row['created_at'],
            ]);
        }

        return $assignments;
    }

    /**
     * @param $row
     * @return Item|Permission|Role
     */
    public static function populate($row): Item
    {
        $class = $row['type'] == Item::TYPE_PERMISSION ? Permission::class : Role::class;

        if (!isset($row['data']) ||
            ($data = @unserialize(is_resource($row['data']) ?
                stream_get_contents($row['data']) :
                $row['data'])
            ) === false
        ) {
            $data = null;
        }

        return new $class([
            'name' => $row['name'],
            'type' => $row['type'],
            'description' => $row['description'],
            'ruleName' => $row['rule_name'],
            'data' => $data,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ]);
    }
}
