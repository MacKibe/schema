<?php
//
include_once 'migration.php';
//Assume the sql statement was sent through a post request
$cmd=$_POST["cmd"];
//
//Create a php data access object(pdo)
$pdo/*:PDO*/ = new PDO("mysql:host=$servername;dbname=mutallco_rental", $cmd->username, $cmd->password);
 // 
//Use the pdo object to execute an sql statement
$result/*:PDOStatement*/=$pdo->query($sql,PDO::FETCH_NUM);
//
//Fetch all the data that is retrieved
$rows/*:array*/=$result->fetchAll();
//
//Print the rows of data to the console
echo json_encode($rows);
var_dump($rows);
