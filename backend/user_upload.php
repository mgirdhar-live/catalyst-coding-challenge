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

$arguments = parseArguments($argv); // Fetch arguments.

$dbConnection = getConnection($arguments); // Establish postgres connection.

if(!$dbConnection)
    die( "Database connection failed." ); // Terminate the script if the connection is not made.

$found = false; // Used to check for valid command line request.
// Process command line arguments.
foreach($arguments as $key => $argument):
    switch($key):
        case 'create_table':
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

            $found = true;
        break;
        default:
        break;
    endswitch;
endforeach;

// List all commands if no option is selected.
if(!$found)
    listCommands();

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
