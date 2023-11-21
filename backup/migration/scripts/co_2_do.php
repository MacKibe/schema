#!usr/bin/php
<?php
//
//This is a (unix) script (hence the shebang instruction) that is
//executed by a crontab command to migrate data changes in
//mutallco_rental database on mutall.co.ke server to a similar
//dabase in Digital Ocean in prodduction mode.
//
//Resolve access to the shared migration code
include_once 'migration/migration.php';

//Create a server that will write data to mutallco_rental database
//on the local server
$do= new mutall\migration\server('mutallco_rental');
//
//Compile the data source
$from_co = ['dbname'=>'mutallco_rental', 'server'=>server::CO];
//
//Migrate data from the co.ke server to this DO server
$do->migrate($from_co);