<?php
/**
 * Database layer — single PDO connection (lazy).
 */

/**
 * @return PDO
 * @throws PDOException
 */
function db()
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

/**
 * Can we connect AND is the schema present?
 * Used by bootstrap.php to decide whether to send the user to install.php.
 *
 * @return bool
 */
function db_ready()
{
    try {
        $pdo = db();
        $pdo->query("SELECT 1 FROM settings LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Run a SELECT and return all rows.
 */
function db_all($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Run a SELECT and return a single row (or null).
 */
function db_one($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/**
 * Run a SELECT and return a single scalar value (or null).
 */
function db_val($sql, $params = [])
{
    $row = db_one($sql, $params);
    return $row === null ? null : array_values($row)[0];
}

/**
 * Insert a row from an associative array.
 *
 * @return int last insert id
 */
function db_insert($table, $data)
{
    $cols = array_keys($data);
    $sql  = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
          . implode(',', array_fill(0, count($cols), '?')) . ')';
    db()->prepare($sql)->execute(array_values($data));
    return (int) db()->lastInsertId();
}

/**
 * Update rows from an associative array.
 *
 * @return int affected rows
 */
function db_update($table, $data, $where, $whereParams = [])
{
    $sets = [];
    foreach (array_keys($data) as $col) {
        $sets[] = '`' . $col . '` = ?';
    }
    $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
    $st  = db()->prepare($sql);
    $st->execute(array_merge(array_values($data), $whereParams));
    return $st->rowCount();
}

/**
 * Delete rows.
 */
function db_delete($table, $where, $params = [])
{
    $st = db()->prepare('DELETE FROM `' . $table . '` WHERE ' . $where);
    $st->execute($params);
    return $st->rowCount();
}
