<?php

namespace Tualo\Office\DSX\Read;

/**
 * Read permission check against ds_access for the groups of the current session.
 */
class AccessControl
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function canRead(string $tableName): bool
    {
        $rows = $this->db->direct(
            'select ds_access.table_name
            from ds_access
                join view_session_groups on ds_access.role = view_session_groups.`group`
            where ds_access.table_name = {tablename} and ds_access.`read` = 1
            limit 1',
            ['tablename' => $tableName]
        );
        return is_array($rows) && count($rows) > 0;
    }

    public function assertRead(string $tableName): void
    {
        if (!$this->canRead($tableName)) {
            throw new \Exception('Sie haben kein Leserecht für `' . $tableName . '`.', 15403);
        }
    }
}
