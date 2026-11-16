<?php
class DatabaseHelper {
    public static function runQuery($pdo, $sql, $parameters = array()) {
        if (!is_array($parameters)) {
            $parameters = array($parameters);
        }

        $statement = null;
        if (count($parameters) > 0) {
            $statement = $pdo->prepare($sql);
            $executedOk = $statement->execute($parameters);
            if (!$executedOk) {
                throw new PDOException("Failed to execute prepared statement.");
            }
        } else {
            $statement = $pdo->query($sql);
            if (!$statement) {
                throw new PDOException("Failed to execute query.");
            }
        }
        return $statement;
    }
}
?>