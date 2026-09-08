<?php
function dean_account_table($dbh)
{
    static $tableName = null;

    if ($tableName !== null) {
        return $tableName;
    }

    foreach (array('tbldean', 'admin') as $candidate) {
        $sql = "SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = :db
                  AND table_name = :table";
        $query = $dbh->prepare($sql);
        $query->bindValue(':db', DB_NAME, PDO::PARAM_STR);
        $query->bindValue(':table', $candidate, PDO::PARAM_STR);
        $query->execute();

        if ((int)$query->fetchColumn() > 0) {
            $tableName = $candidate;
            return $tableName;
        }
    }

    throw new RuntimeException('No dean/admin account table found.');
}

function dean_find_by_username($dbh, $username)
{
    $table = dean_account_table($dbh);
    $sql = "SELECT UserName, Password FROM `$table` WHERE UserName = :username LIMIT 1";
    $query = $dbh->prepare($sql);
    $query->bindValue(':username', $username, PDO::PARAM_STR);
    $query->execute();

    return $query->fetch(PDO::FETCH_OBJ);
}

function dean_update_password($dbh, $username, $passwordHash)
{
    $table = dean_account_table($dbh);
    $sql = "UPDATE `$table` SET Password = :password WHERE UserName = :username";
    $query = $dbh->prepare($sql);
    $query->bindValue(':password', $passwordHash, PDO::PARAM_STR);
    $query->bindValue(':username', $username, PDO::PARAM_STR);

    return $query->execute();
}
?>
