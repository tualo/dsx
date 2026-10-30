# dsx

## Lesen (`Tualo\Office\DSX\Read\RestApiGet`)

PHP-Nachbau der Stored Procedure `dsx_rest_api_get` (Package `ds`), genutzt von `/dsx/{tablename}/read`.

```php
$result = \Tualo\Office\DSX\Read\RestApiGet::read($db, [
    'tablename' => 'adressen',
    'filter'    => [['property' => 'plz', 'operator' => 'eq', 'value' => '07545']],
    'sort'      => [['property' => 'kundennummer', 'direction' => 'ASC']],
    'page' => 1, 'limit' => 50, 'count' => 1,
]);
```

| Klasse | Aufgabe | SQL-Gegenstück |
|---|---|---|
| `AccessControl` | Leserecht über `ds_access` / `view_session_groups` | Prüfung in `dsx_rest_api_get` |
| `TableDefinition` | `ds` / `ds_column`, Primärschlüssel-Ausdruck, Labels aus `ds_column_list_label` / `ds_column_form_label` | `dsx_get_key_sql_prefix`, `dsx_get_key_sql` |
| `FilterBuilder` | `filter` / `latefilter` (inkl. `__id`, `in`, `is null`, Gruppen) | `dsx_filter_values`, `dsx_filter_values_simple`, `dsx_filter_operator` |
| `SortBuilder` | `sort` | `dsx_read_order` |
| `SqlQuoter` | Literale / Bezeichner | `dsx_filter_values_extract`, `FIELDQUOTE` |
| `RestApiGet` | Ablauf, Suche, Volltext, Paging, Ergebnis | `dsx_rest_api_get`, `fulltext_like` |

Spaltennamen werden gegen `ds_column` geprüft, Werte escaped. `extendedwhere` (rohes SQL) wird nur mit `new RestApiGet($db, true)` ausgewertet.

Mit `labels=1` (optional `language`, z.B. `DE`) liefert das Ergebnis zusätzlich `labels.list` und `labels.form`: die aktiven Einträge aus `ds_column_list_label` bzw. `ds_column_form_label`, sortiert nach `position`. `dataIndex` entspricht dem Schlüssel der Spalte in `data` (abhängig von `comibedfieldname`).
