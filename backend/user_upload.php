<?php

// Available argument that could be used.
$commands = [
    '--file'            => 'This argument is used to specify the name of the CSV to be parsed (File should be placed in backend folder).',
    '--create_table'    => 'This argument will cause the PostgreSQL users table to be built (and no further action
    will be taken)',
    '--dry_run'         => 'This argument will be used with the --file directive in case we want to run the script but not
    insert into the DB. All other functions will be executed, but the database won\'t be altered.',
    '-u'                => 'PostgreSQL username.',
    '-p'                => 'PostgreSQL password.',
    '-h'                => 'PostgreSQL host.',
    '--help'            => 'which will output the above list of directives with details.'
];

$results    = ['success' => [], 'errors' => []]; // Contains result of import process.
$encoding   = 'UTF-8'; // Encoding used for parsing column.
$separator  = ','; // Separator used to explode each line.
$enclosure  = '"'; // Enclosure used to decorate each field.
$maxRowSize = 4096; // Maximum row size to be used for decoding.
$successCode= "0;32"; // Color code for success messages.
$errorCode  = "0;31"; // Color code for error messages.
$dbName     = "customdb"; // Database name.
$tableName  = 'users';    // Table name.
$found = false; // Used to check for valid command line request.

verifyAccess(); // Allow command line access only.
$arguments = parseArguments($argv); // Fetch arguments.
$dbConnection = getConnection($arguments); // Establish postgres connection.

if (!$dbConnection) {
    die("\033[".$errorCode."mDatabase connection failed.\033[0m\n");// Terminate the script if the connection is not made.
}

// Process command line arguments.
foreach ($arguments as $key => $argument) {
    if ($argument === 'dry_run') {
        continue;
    }

    switch ($key) {
        case 'create_table':
            createTable();
            $found = true;
            break;
        case 'help':
            listCommands();
            $found = true;
            break;
        case 'file':
            $isDryRun = isset($arguments['dry_run']) ? true : false;
            if ($isDryRun) {
                dryRun($argument);
            } else {
                importUsers($argument);
            }

            displayResults();
            $found = true;
            break;
        default:
            break;
    }
}

// List all commands if no option is selected.
if (!$found) {
    listCommands();   
}

function dryRun($csv) {
    global $successCode;
    echo "\033[".$successCode."mDry run, no data is going to be added.\033[0m\n";
    importUsers($csv, false);
}

// Insert record into the database.
function insertRecord($array, $insertRecords) {
    global $results, $dbConnection, $tableName;
    $name    = formatValue($array,'name');
    $surname = formatValue($array,'surname');
    $email   = formatValue($array,'email');
    $result;

    try {
        $result = @pg_query($dbConnection, "SELECT email FROM $tableName WHERE email = '$email'");
        if (!$result) {
            $array['message'] = "Some error occured fetching the record($email).";
            $results['errors'][] = $array;
            return false;
        }
        $result = @pg_fetch_assoc($result);
    } catch (Exception $e) {
        $array['message'] = "Some error occured fetching the record($email).";
        $results['errors'][] = $array;
        return;
    }

    if ($result) { // show error, don't insert
        $array['message']    = $email. " already exists.";
        $results['errors'][] = $array;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // show error, don't insert
        if($email !== '')
            $array['message']    = $email. " is an invalid email.";
        else
            $array['message']    = "Email is a required field.";       
        $results['errors'][] = $array;
    } elseif ($insertRecords) { // insert records
        try {
            $sql = "INSERT INTO $tableName(name, surname, email) VALUES('$name','$surname','$email')";
            $result = @pg_query($dbConnection, $sql);
            if(!$result)
            {
                $array['message'] = "Some error occured while inserting the record($email).";
                $results['errors'][] = $array;
                return;
            }
            $array['message'] = "$email successfully inserted.";
            $results['success'][] = $array;    
        } catch (Exception $e) {
            $array['message'] = "Some error occured while inserting the record($email).";
            $results['errors'][] = $array;    
        }
    } 
}    

function checkIfTableExists() {
    global $dbConnection, $dbName, $tableName, $errorCode;
    $sql = "
        SELECT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE  table_catalog = '$dbName'
        AND    table_name = '$tableName'
    );
    ";

    try {
        $result = @pg_query($dbConnection, $sql);
        if (!$result) {
            echo "\033[".$errorCode."mSome error occured while checking table status.\033[0m\n";
        }

        $result = @pg_fetch_assoc($result);
        if ($result['exists'] === 'f') {
            die( "\033[".$errorCode."mUser table does not exists, Run --create_table command first.\033[0m\n" );
        }
    } catch (Exception $e) {
        echo "\033[".$errorCode."mSome error occured while creating user table.\033[0m\n";
    }    
}

// Print results
function displayResults() {
    global $results, $successCode, $errorCode;
    foreach ($results as $key => $result)
    {
        if ($key === 'success') {
            $colorCode = $successCode;
        } elseif ($key === 'errors') {
            $colorCode = $errorCode;
        }

        foreach ($result as $sResult) {
            echo "\033[".$colorCode."m".$sResult['message']."\033[0m\n";
        }
    }
}

// Format cell data.
function formatValue($array, $key) {
    $value = isset($array[$key]) ? $array[$key] : '';
    $value = strtolower($value);
    $value = ucfirst($value);
    $value = addslashes($value);
    return $value;
}

// Change the encoding to Utf8
function convertEncoding($item) {
    global $encoding;
    return iconv($encoding, "$encoding//IGNORE", $item);
}
    
// Check and remove not required values from csv data.
function parseArray($array) {
    $array = array_map('trim', $array);
    $array = array_map('convertEncoding', $array);   
    return $array;
}

// Parse the csv file
function parseFile($filePath) {   
    global $encoding, $separator, $enclosure, $maxRowSize;

    $content = [];
    $file = fopen($filePath, 'r');
    $fields = fgetcsv($file, $maxRowSize, $separator, $enclosure);
    $fields = parseArray($fields);
    while ( ($row = fgetcsv($file, $maxRowSize, $separator, $enclosure)) !== false ) {
        if ( count($row) < 3 ) // skip empty lines
            continue;
        
        $i = 0;
        $tmp = [];
        foreach ($fields as $field) {
            $tmp[$field] = $row[$i];
            $i++;
        }
        $content[] = parseArray($tmp);
    }

    fclose($file);
    return $content;
}
    
// Import users
function importUsers($csv, $insertRecords = true) {
    global $errorCode;
    checkIfTableExists(); // Check if user table exists.
    $filePath = dirname(__FILE__). "/$csv";

    if (!file_exists($filePath)) // Check if the file exists.
        die( "\033[".$errorCode."mFile does not exists $csv.\033[0m\n" );

    $records  = parseFile($filePath);
    foreach ($records as $record) {
        insertRecord($record, $insertRecords);
    }
}

// Create use table if not exists.
function createTable() {
    global $dbConnection, $successCode, $errorCode, $tableName;

    $sql = "
    CREATE TABLE IF NOT EXISTS $tableName (
        id SERIAL PRIMARY KEY,
        name CHARACTER VARYING(100) NULL,
        surname CHARACTER VARYING(100) NULL,
        email CHARACTER VARYING(100) NOT NULL UNIQUE
    );
    ";

    try {
        pg_query($dbConnection, $sql);
        echo "\033[".$successCode."mUser table has been created successfully.\033[0m\n";
    } catch (Exception $e) {
        echo "\033[".$errorCode."mSome error occured while creating user table.\033[0m\n";
    }
    return;
}

// Make database connection
function getConnection(&$arguments) {
    global $errorCode, $dbName;

    try  {
        $u = isset($arguments['u']) ? $arguments['u'] : '';
        $p = isset($arguments['p']) ? $arguments['p'] : '';
        $h = isset($arguments['h']) ? $arguments['h'] : '';
        // database credentails are mandatory.
        if ($u === '' || $p === '' || $h === '') {
            echo "\033[".$errorCode."mArguments -u, -p and -h are required arguments.\033[0m\n";
            listCommands();
            die('');
        }
        $dbConnection = @pg_connect("host=$h dbname=$dbName user=$u password=$p");        
        unset($arguments['u']);
        unset($arguments['h']);
        unset($arguments['p']);
        return $dbConnection;
    } catch (PDOException $e) {
        die("\033[".$errorCode."mDatabase connection failed.\033[0m\n");
    }
}

// List all available commands
function listCommands() {
    global $commands;    
    echo "FOLLOWING ARGUMENTS COULD BE USED\n\n";

    foreach ($commands as $key => $command) {
        echo "$key => $command\n\n";
    }
}

// Parse command line arguments into useful form.
function parseArguments($arguments) {
    $args = [];
    foreach ($arguments as $argument) {
        if (preg_match('/--([^=]+)=?(.*)/',$argument,$result)) {
            $args[$result[1]] = $result[2]; 
        } elseif (preg_match('/-([^=]+)=(.*)/',$argument,$result)) {
            $args[$result[1]] = $result[2]; 
        }
    }    
    return $args;
}

// Allow to access via command line.
function verifyAccess() {
    global $errorCode;

    if (PHP_SAPI !== 'cli'){
        die("\033[".$errorCode."mThe script can be only accessed from the command line.\033[0m\n\n");
    }
}
