<?php
//
//This is a script executed using a browser to test migration
//of data from one DO to the local host. So, this speipt must be run from the
//local host
//
//Resolve access to the shared migration code
include_once 'migration/library.php';

//Create a server that will write data to the test database
//on thse local server
$local = new mutall\migration\server('test');
//
//Specify the data source
$from_do = ['dbname'=>'mutallco_rental', server=>server::DO];
//
//Migrate data from mutallco_rental on DO server to the test database
//in this local host
$local->migrate($from_do);