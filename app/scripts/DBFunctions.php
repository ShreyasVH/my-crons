<?php

require_once 'DBFunctions.php';

function getTables($config, $dbName)
{
    $tables = [];
    $query = 'show full tables where Table_Type = \'BASE TABLE\'';
    $result = runQuery($config, $query);

    if(!empty($result))
    {
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        if(!empty($rows))
        {
            $tables = array_column($rows, 'Tables_in_' . $dbName);
        }
        $result->free();
    }

    return $tables;
}

function isAvailable($config)
{
    return connect($config);
}

function runQuery($config, $query)
{
    $result = false;
    $dbLink = connect($config);
    if($dbLink)
    {
        $result = mysqli_query($dbLink, $query);
        if(!$result)
        {
            echo("\nError executing mysql query.\nQuery : " . $query . ".\nResponse : " . json_encode($result, JSON_PRETTY_PRINT) . "\nError : " . $dbLink->error);
        }

        $dbLink->close();
    }
    else
    {
        echo("\nError executing mysql query.\nQuery : " . $query . ".\nResponse : " . json_encode($result, JSON_PRETTY_PRINT) . "\nError : Couldn't connect to DB");
    }
    return $result;
}

function connect($config)
{
    return mysqli_connect($config['host'], $config['user'], $config['password'], $config['dbName'], (int) $config['port']);
}

function getTableStructure($config, $tableName)
{
    $structure = '';
    $query = 'SHOW CREATE TABLE `' . $tableName . '`';
    $result = runQuery($config, $query);
    if(!empty($result))
    {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        if(!empty($rows))
        {
            $structure = $rows[0]['Create Table'];
        }
    }

    return $structure;
}

function getRowCount($config, $tableName)
{
    $count = 0;

    $query = 'SELECT COUNT(*) as count FROM `' . $tableName . '`';
    $result = runQuery($config, $query);

    if(!empty($result))
    {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        if(!empty($rows))
        {
            $row = $rows[0];
            $count = $row['count'];
        }
    }

    return $count;
}

function getRows($config, $tableName, $offset, $limit)
{
    $rows = [];

    $query = 'SELECT * FROM `' . $tableName . '` LIMIT ' . $limit . ' OFFSET ' . $offset;
    $result = runQuery($config, $query);
    if(!empty($result))
    {
        $rows = array_merge($rows, $result->fetch_all(MYSQLI_ASSOC));
        $result->free();
    }

    return $rows;
}

function getQueriesForTable($config, $tableName)
{
    $queries = [];

    $structure = getTableStructure($config, $tableName);
    if(!empty($structure))
    {
        $queries[] = "# Dump of table " . $tableName;
        $queries[] = "# ------------------------------------------------------------";

        $queries[] = "/*!40000 ALTER TABLE `" . $tableName . "` DISABLE KEYS */;";
        $totalCount = getRowCount($config, $tableName);

        $offset = 0;
        $limit = 1000;

        while($offset < $totalCount)
        {

            $rows = getRows($config, $tableName, $offset, $limit);
            if(!empty($rows))
            {
                $columns = array_keys($rows[0]);
                $columns = array_map(function($value) {
                    return '`' . $value . '`';
                }, $columns);

                $valueStrings = [];
                foreach($rows as $row)
                {
                    $valueString = "(";
                    $values = array_values($row);
                    $values = array_map(function($value) {
                        if(is_null($value))
                        {
                            return 'NULL';
                        }
                        else
                        {
                            return '"' . str_replace('"', '\"', $value) . '"';
                        }
                    }, $values);

                    $valueString .= implode(", ", $values);

                    $valueString .= ")";
                    $valueStrings[] = $valueString;
                }
                $query = 'INSERT INTO `' . $tableName . '` (' . implode(', ', $columns) . ") VALUES \n" . implode(",\n", $valueStrings) . ";";
                $queries[] = $query;
            }

            $offset += $limit;
        }

        $queries[] = "/*!40000 ALTER TABLE `" . $tableName . "` ENABLE KEYS */;\n";
    }

    return $queries;
}

function processDatabase($config, $databaseName, $formattedDBName)
{
    $tables = getTables($config, $databaseName);

    $queries = [
        "# ------------------------------------------------------------\n",
        "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;\n",
        "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;\n"
    ];

    $fileName = $formattedDBName . '.sql';

    foreach($tables as $index => $tableName)
    {
        if($index > 0)
        {
            echo "\n\t\t::::::::::::::::::::::::::::::\n";
        }

        echo "\n\t\tProcessing table. [" . ($index + 1) . "/" . count($tables) . "]\n";

        $queries = array_merge($queries, getQueriesForTable($config, $tableName));
        $queries[] = "\n\n";

        echo "\n\t\tProcessed table. [" . ($index + 1) . "/" . count($tables) . "]\n";
    }

    $queries[] = "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=1;\n";
    $queries[] = "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=1;\n";
    $queries[] = "# ------------------------------------------------------------";

    file_put_contents($fileName, implode("\n", $queries));
    $payload = [
        'from' => trim(getenv('FROM_EMAIL_ID')),
        'to' => trim(getenv('TO_EMAIL_ID')),
        'subject' => 'DB Backup - ' . $formattedDBName . ' ' . date('d-m-Y'),
        'body' => "\nPFA",
        'attachments' => [
            [
                'filename' => $fileName,
                'content' => base64_encode(file_get_contents($fileName))
            ]
        ]
    ];

    echo "\n\t\t\tSending email\n";

    sendMail($payload);

    unlink($fileName);
}
