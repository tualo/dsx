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

### Streaming

`RestApiGet::stream($request, $write, $trailer)` liest die Zeilen ungepuffert (`Database::directIterate()`, `MYSQLI_USE_RESULT`) und schreibt das JSON zeilenweise – der Speicherbedarf ist unabhängig von der Ergebnisgröße. Die Routen nutzen dafür `StreamResponse::send()`.

- Rechteprüfung, `count`, Labels und Query-Start passieren vor dem ersten geschriebenen Byte; Fehler dort werden als Exception geworfen und können normal über `App::result` beantwortet werden.
- Fehler während des Lesens beenden das JSON mit `"success": false` und `msg` (deshalb stehen `success`/`msg` am Ende).
- `StreamResponse` hängt am Ende wie `App::end()` die Felder `errors` und `warnings` aus `App::result()` an (Stand nach dem Schreiben der Zeilen).
- Ohne `count=1` wird `total` aus den gelesenen Zeilen berechnet (`start` + Anzahl Zeilen) und steht mit `has_more` hinter `data`. `has_more: true` heißt: die Seite ist voll (Zeilen = `limit`), es kann weitere geben; bei weniger Zeilen ist das Ende erreicht. Mit `count=1` steht das per SQL gezählte `total` wie bisher am Anfang, `has_more` entfällt.
- Während des Lesens darf auf derselben Verbindung keine andere Abfrage laufen.

`execute()` liefert weiterhin das komplette Ergebnis als Array (für PHP-Aufrufer mit kleinen Mengen).

Mit `labels=1` (optional `language`, z.B. `DE`) liefert das Ergebnis zusätzlich `labels.list` und `labels.form`: die aktiven Einträge aus `ds_column_list_label` bzw. `ds_column_form_label`, sortiert nach `position`. `dataIndex` entspricht dem Schlüssel der Spalte in `data` (abhängig von `comibedfieldname`).
