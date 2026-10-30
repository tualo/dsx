<?php

namespace Tualo\Office\DSX\Write;

use Tualo\Office\DSX\Read\SqlQuoter;

/**
 * Stores __file_data / __file_name of written rows in ds_files and ds_files_data and links
 * the file to the row (column file_id). Replaces dsx_rest_api_set_handle_filedata.
 *
 * ds_files_cleanup is done inline: the procedure drops a (temporary) table without the
 * TEMPORARY keyword, which would implicitly commit the surrounding transaction.
 */
class FileDataHandler
{
    private $db;
    private SqlQuoter $quoter;
    private WriteDefinition $definition;

    public function __construct($db, SqlQuoter $quoter, WriteDefinition $definition)
    {
        $this->db = $db;
        $this->quoter = $quoter;
        $this->definition = $definition;
    }

    /**
     * @param array $rows prepared rows with "file" and "id" (key after the write); file_id is set in the values
     */
    public function handle(array &$rows): void
    {
        $q = $this->quoter;
        $writeTable = $this->definition->getWriteTable();
        $handled = false;

        foreach ($rows as &$row) {
            if (is_null($row['file'])) continue;
            $handled = true;
            $file = $row['file'];
            $id = $q->string((string)$file['id']);
            $data = (string)$file['data'];
            $size = (string)strlen($data);
            $hash = $q->string(md5($data));

            $this->db->direct(
                'insert into ds_files (file_id, table_name, name, path, size, mtime, ctime, type, hash, login) values ('
                    . $id . ', ' . $q->string($writeTable) . ', ' . $q->value($file['name']) . ', concat(\'files/\', ' . $id . '), '
                    . $size . ', now(), now(), ' . $q->value($file['type']) . ', ' . $hash . ', @sessionuser'
                    . ') on duplicate key update login = @sessionuser'
            );

            if ($data !== '') {
                $this->db->direct(
                    'insert into ds_files_data (file_id, data) values (' . $id . ', ' . $q->string($data) . ')'
                        . ' on duplicate key update data = values(data)'
                );
                $this->db->direct(
                    'update ds_files set size = ' . $size . ', hash = ' . $hash . ', mtime = now() where file_id = ' . $id
                );
            }

            if (!is_null($row['id'])) {
                $this->db->direct(
                    'update ' . $q->identifier($writeTable) . ' set file_id = ' . $id
                        . ' where ' . $this->definition->keyCondition($row['id'], $q)
                );
            }
            $row['values']['file_id'] = $file['id'];
        }
        unset($row);

        if ($handled) {
            // ds_files_cleanup: files of this table no longer referenced by any row
            $this->db->direct(
                'delete from ds_files where table_name = ' . $q->string($writeTable)
                    . ' and file_id not in (select file_id from ' . $q->identifier($writeTable) . ')'
            );
        }
    }
}
