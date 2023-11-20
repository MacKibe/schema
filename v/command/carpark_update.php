#!/usr/bin/php
<?php
//
//
namespace mutall;
//
//This file supports the sending of the mutall carpark vehicle collection reports
//at 6:00 p.m everyday. For this, we need to have this file scheduled to run as a 
//crontab.
//
//The default path set by the user when accessing the credentials
if (!isset($_SERVER['DOCUMENT_ROOT']))
    $_SERVER['DOCUMENT_ROOT'] = '/home/mutall/projects/';
$path = '/home/mutall/projects/';
//
//set_include_path($path);
//
//Resolve the reference to the messenger
include_once $path. "schema/v/code/messenger.php";
//
//Include the database to allow for execution of queries.
include_once $path. "schema/v/code/schema.php";
//
//Include the sql to support the development of queries
include_once $path. "schema/v/code/sql.php";
//
//Create an instance of the database class
$dbase = new \mutall\database("mutall_ranix", true, false);
//
//Have access to the messenger class
$messenger = new \mutall\messenger();
//
//1. Retrieve the job name as part of the command line parameters.
//  N.B,; The first parameter [0] is the file name
$job_name = $argv[1];
//
//2. Formulate the query to retrieve the performance from the database
$query = file_get_contents(__DIR__ . "/../queries/carpark.sql");
//$query = file_get_contents(messenger::home . messenger::carpark_update);
//
//3. Execute the query to retrieve the vehicle collection errors
$results = $dbase->get_sql_data($query);
//
//3.1 Begin with an empty report
$report = "";
//
//When there are some results, send a message
if (count($results) > 0) {
    //
    //3.2 Retrieve the date of collecting the vehicle records
    $report .= "Date:- " . $results[0]['siku'] . "\n";
    //
    //3.3 Extract the message to send from the result
    foreach ($results as $result) {
        //
        //The operator collecting the results during that day
        $report .= "Operator:- " . $result['operator'] . "\n";
        //
        //The total number of car visits in the carpark on that day
        $report .= "Total number of visits:- " . $result['total_visits'] . "\n";
        //
        //The count of errors during that day
        $report .= "Number of errors:- " . $result['error_count'] . "\n";
        //
        //The error rate
        $report .= "Error rate:- " . $result['error_rate'] . "%\n \n";
    }
}
//
//When there are no results, send the message to show that there are no results
else {
    //
    //Compile the message to send
    $report .= "No data was collected today.";
    //
    //End the program's execution if no data is selected
    return;
}
//
//3.4 The subject of the message
$subject = "Carpark report on " . date("Y-m-d", time());
//
//3.5 .The recipient of the message is Mr. Muraya
//Construct a new standard class
$recipient = new \stdClass;
//
//Provide the type of recipient
$recipient->type = "individual";
//
//The recipient's primary key
$recipient->user = [119];
//
//The technology to use when sending the email
$technology = ['phpmailer'];
//
//4. Send the message:- Either through mail or via SMS
$errors = $messenger->send($recipient, $subject, $report, $technology);
//
//
if (count($errors) > 0) {
    //
    //Log the errors obtained to the error log file
    $messenger->report_errors($errors);
    //
    //Do not continue from this place onwards
    return;
}
//
//Compile the performance for that day
$compiled = "$report";
//
//5. Update the database with the message sent
//
//The query to update the database with
$sql = $dbase->chk(
    "INSERT INTO mutall_users.msg "
        . "(subject, text) "
        . "VALUES('$subject','$compiled')"
);
//
//Run the query to update the job table with the message. WHAT HAPPENS WHEN THE
// PROGRAM FAILS. hint-> ERROR HANDLING.
$update = $dbase->query($sql);
//
//Record errors collected to the error log
if (!$update) {
    //
    //Compile the errors collected when updating the system
    $result = explode("\n", $update);
    //
    //Report the errors collected
    $messenger->report_errors($result);
    //
    //Do not continue
    return;
}
//
//6. Update the operator earnings
//
//The primary key of the operator
$pk= $results[0]['pk'];
//
//The total number of visits in the carpark
$visits= $results[0]['total_visits'];
//
//The total number of errors in the vehicle carpark
$error_count= $results[0]['error_count'];
//
//6.1. Construct the query to load the operator earnings for the day
$operator = $dbase->chk(
    "INSERT INTO mutall_ranix.earning(operator,recorded_vehicles,errors)
        VALUES($pk,$visits,$error_count)"
);
//
//6.3. Execute the query to update the operator performance in the table
$insert = $dbase->query($operator);
//
//Record the errors that may occur during inserting
if (!$insert) {
    //
    //Compile the errors collected when updating the system
    $result = explode("\n", $insert);
    //
    //Report the errors collected
    $messenger->report_errors($result);
    //
    //Do not continue
    return;
}
