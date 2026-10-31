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

### SQL-Debugging

Die SQL-Ausgabe wird in der Anwendungskonfiguration
`configuration/.htconfig` aktiviert:

```ini
[ds]
return_sql=1
```

Diese bereits von `DSReadRoute` verwendete Einstellung ist standardmäßig
deaktiviert (`0`). Wenn sie aktiviert ist, enthält die Read-Antwort
`debug_sql` mit der vollständigen Datenabfrage einschließlich Filter,
Sortierung und Limit. Bei `count=1` enthält `debug_count_sql` die separate
Zählabfrage, sofern `nodata` nicht aktiviert ist. Die Einstellung gilt für
`/ds/{tablename}/read`, `/dsx/{tablename}/read`, Streaming und direkte
`RestApiGet::read()`-Aufrufe. Ein Anfrageparameter `debug_sql` hat keinen
Einfluss darauf.

Der bestehende Anfrageparameter `debug_query=1` liefert unabhängig von dieser
Einstellung weiterhin `debug_query` mit der Basisabfrage. Bei `nodata=1` wird
keine Datenabfrage ausgeführt: `debug_sql` ist dann bei aktivierter Einstellung
`null`, während wie bisher `query` und `limitterm` zurückgegeben werden.

Die Abfragen können Tabellen- und Spaltennamen sowie Filter- und Suchwerte
enthalten. `return_sql=1` gibt diese Daten bei allen betroffenen Reads aus und
sollte nur für vertrauenswürdige Diagnosen aktiviert werden. Der Regressionstest
kann ohne Datenbank mit `php tests/Read/RestApiGetDebugTest.php` ausgeführt
werden.

### Streaming

`RestApiGet::stream($request, $write, $trailer)` liest die Zeilen ungepuffert (`Database::directIterate()`, `MYSQLI_USE_RESULT`) und schreibt das JSON zeilenweise – der Speicherbedarf ist unabhängig von der Ergebnisgröße. Die Routen nutzen dafür `StreamResponse::send()`.

- Rechteprüfung, `count`, Labels und Query-Start passieren vor dem ersten geschriebenen Byte; Fehler dort werden als Exception geworfen und können normal über `App::result` beantwortet werden.
- Fehler während des Lesens beenden das JSON mit `"success": false` und `msg` (deshalb stehen `success`/`msg` am Ende).
- Lesefehler werden über den Logger `dstable` mit Tabellenname, Schema und Fehlerkette protokolliert. Während eines ungepufferten Reads wird kein zusätzliches SQL zur Ermittlung des Galera-Hostnamens ausgeführt.
- `StreamResponse` hängt am Ende wie `App::end()` die Felder `errors` und `warnings` aus `App::result()` an (Stand nach dem Schreiben der Zeilen).
- Ohne `count=1` wird `total` aus den gelesenen Zeilen berechnet (`start` + Anzahl Zeilen) und steht mit `has_more` hinter `data`. `has_more: true` heißt: die Seite ist voll (Zeilen = `limit`), es kann weitere geben; bei weniger Zeilen ist das Ende erreicht. Mit `count=1` steht das per SQL gezählte `total` wie bisher am Anfang, `has_more` entfällt.
- Während des Lesens darf auf derselben Verbindung keine andere Abfrage laufen.

`execute()` liefert weiterhin das komplette Ergebnis als Array (für PHP-Aufrufer mit kleinen Mengen).

Mit `labels=1` (optional `language`, z.B. `DE`) liefert das Ergebnis zusätzlich `labels.list` und `labels.form`: die aktiven Einträge aus `ds_column_list_label` bzw. `ds_column_form_label`, sortiert nach `position`. `dataIndex` entspricht dem Schlüssel der Spalte in `data` (abhängig von `comibedfieldname`).

Die Fehlerprotokollierung kann ohne Datenbank mit
`php tests/Read/ReadErrorLoggingTest.php` getestet werden.

## Schreiben (`Tualo\Office\DSX\Write\RestApiSet`)

PHP-Nachbau der Procedure `dsx_rest_api_set`. `DSTable` sowie die `/ds/{tablename}/create`, `/update` und `/delete`-Wege verwenden die PHP-Klasse; die Procedure bleibt für direkte SQL-Aufrufer bestehen.

```php
$result = RestApiSet::write($db, [
    'tablename' => 'korrespondenz_typen',
    'type' => 'insert',            // insert | update | delete | replace
    'update' => true,              // optional: insert ... on duplicate key update
    'ignore' => false,             // optional: insert ignore
    'replace' => false,            // optional: replace into
    'useInsertUpdate' => false,    // optional: update als insert ... on duplicate key update
    'data' => [['__id' => 'ext-1', 'name' => 'Brief']],
]);
// ['success'=>true,'message'=>'OK','tablename'=>..,'writetable'=>..,'type'=>..,'subtype'=>..,
//  'affected_rows'=>1,'data'=>[['__clientid'=>'ext-1','__id'=>'7','id'=>7,'name'=>'Brief', ...]]]
```

Klassen:

| Klasse | Aufgabe |
|---|---|
| `WriteRequest` | Validierung (errno 1001, gleiche Meldungen wie die Procedure), Typ/Subtyp, benötigte Rechte |
| `WriteDefinition` | `ds.writetable`, `ds_column` der Schreibtabelle, Defaults (`{#serial}`, `{:expr}`, `{GUID}`, `{DATETIME}`, ...), Schlüsselbedingungen |
| `DataPreparer` | Typkonvertierung der Daten per `json_table` (gleiche Semantik wie die Procedure), Defaults, Serials |
| `StatementWriter` | insert/replace/ignore/ODKU (gechunkt), update je Zeile, delete per Tupel-Liste |
| `FileDataHandler` | `__file_*`-Felder → `ds_files`/`ds_files_data`, Verknüpfung über `file_id`, Aufräumen alter Dateien |
| `Transaction` | eigene Transaktion bzw. Savepoint, falls bereits eine Transaktion offen ist |

Rechte: `ds_access` (`write` für update, `append` für insert, `delete` für delete) über `Read\AccessControl::can()`.
Spaltennamen werden ausschließlich aus `ds_column` übernommen, Werte werden escaped.

Abweichungen zur Procedure (bewusst):

- update setzt nur die gesendeten Spalten; Primärschlüssel werden nicht mehr über Defaults überschrieben.
- update ohne `__id` aktualisiert über die gesendeten Schlüsselspalten (Procedure: keine Änderung).
- delete nur mit `__id` löscht den Datensatz (Procedure: Fehler).
- `type=replace` erzeugt ein `replace into` (Procedure: kein Statement).
- Auto-Increment-Werte werden in `data`/`__id` zurückgegeben.
- kein `temporary_table_name` mehr; die API liest die betroffenen Zeilen per `__id` erneut, damit das Read-Format samt Labels erhalten bleibt.
- kein Logging in `dsx_rest_api_set_log_statement`.
- Datei-Cleanup erfolgt inline (`ds_files_cleanup` würde durch `DROP TABLE` implizit committen).
