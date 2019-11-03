<?php

verifyAccess(); // Allow command line access only.

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

$arguments = parseArguments($argv); // Fetch arguments.

$dbConnection = getConnection($arguments); // Establish postgres connection.

if(!$dbConnection)
    die( "Database connection failed." ); // Terminate the script if the connection is not made.

$found = false; // Used to check for valid command line request.
// Process command line arguments.
foreach($arguments as $key => $argument):
    switch($key):
        case 'create_table':
            createTable($dbConnection);
            $found = true;
        break;
        case 'help':
            listCommands();
            $found = true;
        break;
        case 'dry_run':
            $found = true;

        break;
        case 'file':
            importUsers($argument);
            displayResults();
            $found = true;
        break;
        default:
        break;
    endswitch;
endforeach;

// List all commands if no option is selected.
if(!$found)
    listCommands();   

function insertRecord($array)
{
    global $results, $dbConnection;
    $name    = formatValue($array,'name');
    $surname = formatValue($array,'surname');
    $email   = formatValue($array,'email');

    $result;
    try {
        $result = @pg_query($dbConnection, "SELECT email FROM users WHERE email = '$email'");
        if(!$result) {
            $array['message'] = "Some error occured fetching the record($email).";
            $results['errors'][] = $array;    
        }
        $result = @pg_fetch_assoc($result);    
        if(!$result) {
            $array['message'] = "Some error occured fetching the record($email).";
            $results['errors'][] = $array;    
        }
    }
    catch(Exception $e)
    {
        $array['message'] = "Some error occured fetching the record($email).";
        $results['errors'][] = $array;
        return;
    }

    if($result) 
    {
        $array['message']    = $email. " already exists.";
        $results['errors'][] = $array;
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $array['message']    = $email. " is an invalid email.";
        $results['errors'][] = $array;
    }
    else
    {
        $array = [
            'name'    => $name,
            'surname' => $surname,
            'email'   => $email
        ];
        try {
            $sql = "INSERT INTO users(name, surname, email) VALUES('$name','$surname','$email')";
            pg_query($dbConnection, $sql);
            $array['message'] = "$email successfully inserted.";
            $results['success'][] = $array;    
        }
        catch(Exception $e)
        {
            $array['message'] = "Some error occured while inserting the record($email).";
            $results['errors'][] = $array;    
        }
    }
}    


function displayResults()
{
    global $results, $successCode, $errorCode;
    foreach($results as $key => $result)
    {
        if($key == 'success')
            $colorCode = $successCode;
        elseif($key == 'errors')
            $colorCode = $errorCode;
        foreach($result as $sResult)
        {
            echo "\033[".$colorCode."m".$sResult['message']."\033[0m\n";
        }
    }
}

function formatValue($array, $key)
{
    $value = isset($array[$key]) ? $array[$key] : '';
    $value = strtolower($value);
    $value = ucfirst($value);
    return $value;
}

function convertEncoding($item) 
{
    global $encoding;
    return iconv($encoding, "$encoding//IGNORE", $item);
}
    
function parseArray($array) 
{
    $array = array_map('trim', $array);
    $array = array_map('convertEncoding', $array);   
    return $array;
}

// Parse the csv file
function parseFile($filePath)
{   
    global $encoding, $separator, $enclosure, $maxRowSize;

    $content = [];
    $file = fopen($filePath, 'r');

    $fields = fgetcsv($file, $maxRowSize, $separator, $enclosure);

    while ( ($row = fgetcsv($file, $maxRowSize, $separator, $enclosure)) != false ) {
        if ( count($row) < 3 ) // skip empty lines
            continue;
        $i = 0;
        $tmp = [];
        foreach($fields as $field)
        {
            $tmp[$field] = $row[$i];
            $i++;
        }
        $content[] = parseArray($tmp);
    }

    fclose($file);
    return $content;
}
    
// Import users
function importUsers($csv) 
{
    $filePath = dirname(__FILE__). "/$csv";

    if(!file_exists($filePath)) // Check if the file exists.
        die( "File does not exists $csv\n" );

    $records  = parseFile($filePath);

    foreach($records as $record)
        insertRecord($record);
}

function createTable($connection)
{
    global $successCode, $errorCode;
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name CHARACTER VARYING(100) NULL,
        surname CHARACTER VARYING(100) NULL,
        email CHARACTER VARYING(100) NOT NULL UNIQUE
    );
    ";
    echo "\033[".$successCode."mUser table has been created successfully.\033[0m\n";
    try {
        pg_query($connection, $sql);
    }
    catch(Exception $e) 
    {
        echo "\033[".$errorCode."mSome error occured while creating user table.\033[0m\n";
    }
    return true;
}

// Make database connection
function getConnection(&$arguments)
{
    try 
    {
        $u = isset($arguments['u']) ? $arguments['u'] : '';
        $p = isset($arguments['p']) ? $arguments['p'] : '';
        $h = isset($arguments['h']) ? $arguments['h'] : '';
        // database credentails are mandatory.
        if($u == '' || $p == '' || $h == ''){
            echo "Arguments -u, -p and -h are required arguments.";
            listCommands();
            exit;
        }
        $dbname='customdb';
        $dbConnection = @pg_connect("host=$h dbname=$dbname user=$u password=$p");        
        unset($arguments['u']);
        unset($arguments['h']);
        unset($arguments['p']);
        return $dbConnection;
    }
    catch (PDOException $e) 
    {
        die( "Database connection failed" );
    }
}

// List all available commands
function listCommands()
{
    global $commands;
    echo "FOLLOWING ARGUMENTS COULD BE USED:\033[0m\n\n";
    foreach($commands as $key => $command):
        echo "$key => $command\n\n";
    endforeach;
}

// Parse command line arguments into useful form.
function parseArguments($arguments) 
{
    $args = [];
    foreach($arguments as $argument):
        if(preg_match('/--([^=]+)=?(.*)/',$argument,$result)) {
            $args[$result[1]] = $result[2]; 
        }
        elseif(preg_match('/-([^=]+)=(.*)/',$argument,$result)) {
            $args[$result[1]] = $result[2]; 
        }
    endforeach;
    return $args;
}

// Allow to access via command line.
function verifyAccess() 
{
    if(PHP_SAPI != 'cli')
        die('The script can be only accessed from the command line.');
}
