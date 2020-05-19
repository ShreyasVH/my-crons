<?php

class BackupDB
{
    private $dbHost;
    private $dbPort;
    private $dbUser;
    private $dbPassword;
    private $dbName;

    public function __construct()
    {

    }

    public function setCredentials($host, $port, $user, $password, $dbName)
    {
        $this->dbHost = $host;
        $this->dbPort = $port;
        $this->dbUser = $user;
        $this->dbPassword = $password;
        $this->dbName = $dbName;
    }

    public function sendMail($payload)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, getenv('MAILER_ENDPOINT') . 'api');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 30000);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 30000);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($status != 200)
        {
            echo "\nError while sending mail. Response: " . $result . ". Payload: " . json_encode($payload) . "\n";
        }
        return $result;
    }

    public function getTables()
    {
        $tables = [];
        $query = 'show full tables where Table_Type = \'BASE TABLE\'';
        $result = $this->runQuery($query);

        if(!empty($result))
        {
            $rows = $result->fetch_all(MYSQLI_ASSOC);

            if(!empty($rows))
            {
                $tables = array_column($rows, 'Tables_in_' . $this->dbName);
            }
            $result->free();
        }

        return $tables;
    }

    public function runQuery($query)
    {
        $result = false;
        $dbLink = $this->connect();
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

    public function connect()
    {
        return mysqli_connect($this->dbHost, $this->dbUser, $this->dbPassword, $this->dbName, $this->dbPort);
    }

    public function getTableStructure($tableName)
    {
        $structure = '';
        $query = 'SHOW CREATE TABLE `' . $tableName . '`';
        $result = $this->runQuery($query);
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

    public function getRowCount($tableName)
    {
        $count = 0;

        $query = 'SELECT COUNT(*) as count FROM `' . $tableName . '`';
        $result = $this->runQuery($query);

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

    public function getRows($tableName, $offset, $limit)
    {
        $rows = [];

        $query = 'SELECT * FROM `' . $tableName . '` LIMIT ' . $limit . ' OFFSET ' . $offset;
        $result = $this->runQuery($query);
        if(!empty($result))
        {
            $rows = array_merge($rows, $result->fetch_all(MYSQLI_ASSOC));
            $result->free();
        }

        return $rows;
    }

    public function getReferencedTables($tableName)
    {

    }

    public function getQueriesForTable($tableName)
    {
        $queries = [];

        $structure = $this->getTableStructure($tableName);
        if(!empty($structure))
        {
            $queries[] = "# Dump of table " . $tableName;
            $queries[] = "# ------------------------------------------------------------";

            $queries[] = "DROP TABLE IF EXISTS `" . $tableName . "`;";
            $queries[] = "/*!40101 SET @saved_cs_client     = @@character_set_client */;";
            $queries[] = "/*!40101 SET character_set_client = utf8 */;";
            $queries[] = $structure . ";";
            $queries[] = "/*!40101 SET character_set_client = @saved_cs_client */;";
            $queries[] = "\n";
            $queries[] = 'TRUNCATE TABLE `' . $tableName . "`;\n";

            $queries[] = "/*!40000 ALTER TABLE `" . $tableName . "` DISABLE KEYS */;";
            $totalCount = $this->getRowCount($tableName);

            $offset = 0;
            $limit = 1000;

            while($offset < $totalCount)
            {

                $rows = $this->getRows($tableName, $offset, $limit);
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
                            return '"' . str_replace('"', '\"', $value) . '"';
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

    public function processDatabase()
    {
        $tables = $this->getTables();

        foreach($tables as $index => $tableName)
        {
            if($index > 0)
            {
                echo "\n\t\t::::::::::::::::::::::::::::::\n";
            }

            echo "\n\t\tProcessing table. [" . ($index + 1) . "/" . count($tables) . "]\n";
            $queries = [
                "# ------------------------------------------------------------\n",
                "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;\n",
                "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;\n"
            ];
            $queries = array_merge($queries, $this->getQueriesForTable($tableName));

            $queries[] = "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=1;\n";
            $queries[] = "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=1;\n";
            $queries[] = "# ------------------------------------------------------------";

            $fileName = $tableName . '.sql';
            file_put_contents($fileName, implode("\n", $queries));


            $payload = [
                'from' => 'samham348@gmail.com',
                'to' => 'shreyas.hande@gmail.com',
                'subject' => 'DB Backup - ' . $this->dbName . ' ' . date('d-m-Y'),
                'body' => "Host: " . $this->dbHost . "\nPort: " . $this->dbPort . "\nPFA",
                'attachments' => [
                    [
                        'filename' => $fileName,
                        'content' => base64_encode(file_get_contents($fileName))
                    ]
                ]
            ];

            echo "\n\t\t\tSending email\n";

            $this->sendMail($payload);

            unlink($fileName);

            echo "\n\t\tProcessed table. [" . ($index + 1) . "/" . count($tables) . "]\n";
        }
    }


    public function execute()
    {
//        $this->setCredentials(
//            getenv('DB_HOST_DUEL_LINKS'),
//            getenv('DB_PORT_DUEL_LINKS'),
//            getenv('DB_USER_DUEL_LINKS'),
//            getenv('DB_PASSWORD_DUEL_LINKS'),
//            getenv('DB_NAME_DUEL_LINKS')
//        );
//        $this->processDatabase();

        $payload = [
            'from' => 'samham348@gmail.com',
            'to' => 'shreyas.hande@gmail.com',
            'subject' => 'Testing',
            'body' => "Body"
        ];
        $this->sendMail($payload);
    }
}

$runner = new BackupDB();
$runner->execute();