<?php
// Allow command line access only.
verifyAccess();

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
