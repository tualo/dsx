<?php

namespace Tualo\Office\DSX\Read;

/**
 * Permission checks against ds_access for the groups of the current session.
 */
class AccessControl
{
    private const RIGHTS = ['read', 'write', 'delete', 'append'];

    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param string $right one of read, write, delete, append (column of ds_access)
     */
    public function can(string $tableName, string $right): bool
    {
        if (!in_array($right, self::RIGHTS, true)) {
            throw new \Exception('Unbekanntes Recht `' . $right . '`.', 15400);
        }
        $rows = $this->db->direct(
            'select ds_access.table_name
            from ds_access
                join view_session_groups on ds_access.role = view_session_groups.`group`
            where ds_access.table_name = {tablename} and ds_access.`' . $right . '` = 1
            limit 1',
            ['tablename' => $tableName]
        );
        return is_array($rows) && count($rows) > 0;
    }

    public function canRead(string $tableName): bool
    {
        return $this->can($tableName, 'read');
    }

    public function assertRead(string $tableName): void
    {
        if (!$this->canRead($tableName)) {
            throw new \Exception('Sie haben kein Leserecht für `' . $tableName . '`.', 15403);
        }
    }
}
