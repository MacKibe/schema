<?php

namespace mutall;
//
//Resolve the reference to the database
include_once 'schema.php';
//
//The scheduler is responsible for creating jobs that are repetitive and those
//that are not repetitive.
class scheduler extends component {

    //
    //This is the full path to the file constructed from the document root and
    //the partial path.
    public string $crontab_data_file;
    //
    //This is the full path to the constructed at files from the document root
    //and it includes the partial path
    public string $at_batch_file;
    //
    //The database object that allows for retrieving queried data(why private??)
    private database $database;
    //
    //THe connection to the database
    public $db;
    //
    //The recursion associated with an event
    public object $recursion;
    //
    //The pk of a job
    public int $pk;
    //
    //The message of a job
    public string $message;
    //
    //The start date of a crontab event
    public  string $start_date;
    //
    //The end date of an event, and on this date, the event should be removed
    //from the database
    public string $end_date;
    //
    //The start date associated with at jobs
    public string $date;
    //
    //The errors associated with the event
    public array $errors = [];
    //
    //The errors that are flagged during the execution of at jobs
    public string $at_error;
    //
    //Errors flagged during the update of cron jobs
    public string $update_error;
    //
    //The name of the job that is saved into the database
    public string $job_name;
    //
    //constructor
    function __construct() {
        //
        //The crontab data file
        $this->crontab_data_file = component::home . component::crontab_file;
        //
        //The at job batch file
        $this->at_batch_file = component::home . component::at_file;
        //
        //
        //Establish a connection to the database(using an incomplete database)
        $this->database = new database("mutall_users", false);
    }
    //
    //Scheduling the requested job
    public function execute(
        //
        //Get the name of the job saved in the database
        string $job_name,
        //
        //It is true when we need to rebuild the crontab, otherwise it is false
        bool $update,
        //
        //The list of at commands as defined in the run_at_commands() below???
        array/*<at>*/ $ats,
        //
        //Recompile the at commands. It is true when we want to recompile the at commands
        //to execute, otherwise it is false.
        bool $recompile
    ): array /*errors|output*/ {
        //
        //Save the job name for use later
        $this->job_name = $job_name;
        //
        //2. Refresh the crontab if necessary
        if ($update) $this->update_cronfile();
        //
        //Refresh the atcommand if necessary
        if ($recompile) $this->update_atfile();
        //
        //Return the collected errors
        return $this->errors;
    }
    //
    //Update the at file with the most recent changes
    public function update_atfile()
    /**ok:error */
    {
        //
        //Construct the query to extract the all jobs, both repetitive and non-repetitive
        //and whose start_date,send_date,or end_date is in the future or in the near future
        $sql = '
        #
        # Compile three queries to extract the repetitive and non-repetitive jobs whose
        #start_date or end_date is in the future.
        with 
            #
            #All non repetitive jobs in the database
            non_rep as (
                select 
                    activity.name,
                    activity.command,
                    activity.recursion->>"$.repetitive" as repetitive,
                    recursion->>"$.send_date" as start_date,
                    recursion->>"$.start_date" as end_date
                from activity
                    where recursion->>"$.send_date">=now()
                    and activity.recursion->>"$.repetitive"="no"
            ),
            #
            #All repetitive jobs whose start_date is in the future
            rep_start as(
                select 
                    activity.name,
                    activity.command,
                    activity.recursion->>"$.repetitive" as repetitive,
                    recursion->>"$.start_date" as start_date,
                    recursion->>"$.end_date" as end_date
                from activity
                    where recursion->>"$.start_date">=now()
            ),
            #
            #All non repetitive jobs whose end_date is in the future
            rep_end as(
                select 
                    activity.name,
                    activity.command,
                    activity.recursion->>"$.repetitive" as repetitive,
                    recursion->>"$.start_date" as start_date,
                    recursion->>"$.end_date" as end_date
                from activity
                    where recursion->>"$.end_date">=now()	
            )
            #
            #Combine the results of the three queries
            table non_rep union all 
            table rep_start union all
            table rep_end
        ';
        //
        //Execute the query and retrieve the activities to recreate the at commands.
        $activities = $this->database->get_sql_data($sql);
        //
        //Use the results to compile the at jobs for every activity listed below
        $this->run_at_command($activities);
    }
    //
    //Run the at commands on a given date with a specific message
    /**
     * 
    The at command is either for:-
    type at =
        //
        //- sending a message indirectly using a job number(from which the message
        //can be extracted from the database)
        { type: "message", send_date: string, message: number,recipient:recipient }
        //
        //- or for initiating a fresh cronjob on the specified date
        | { type: "refresh", start_date: string, end_date: string };
        //
        //
        | { type: "other"; datetime: string; command: string };
     */
    private function run_at_command(array $activities): void {
        //
        //Set the home directory reference for the command.
        $home = component::home;
        //
        //Set the log file to record the errors if any.
        $log = component::log_file;
        //
        //3. Iterate through all the at jobs, creating setting a different execution depending on their types
        // to eventually compile the jobs
        foreach ($activities as $activity) {
            //
            //Initialize a standard object to hold the type of at job
            $at = new \stdClass;
            //
            //An activity is of type message if it is not repetitive, and has a message associated
            //with it.
            if (is_null($activity["end_date"]) && $activity["repetitive"] == "no")
                $at->type = "message";
            //An activity is of type refresh if it is repetitive, has a start_date, end_date,
            //and no message
            if ($activity["repetitive"] == "yes" && !is_null($activity["command"]))
                $at->type = "refresh";
            //
            //An activity is of type other if it has a command and it is not repetitive
            if ($activity["repetitive"] == "no" && !is_null($activity["command"])) $at->type = "other";
            //
            //Initialize a empty batch list of at commands to be executed
            $batch = "";
            //
            //The complete directory to the log file that we will use to log errors for the at jobs at runtime
            $log = ">> " . $home . component::log_file;
            //
            //
            //There are three types of at commands:- 
            switch ($at->type) {
                    //
                case "message":
                    //
                    //A command for sending a message to a user at a specified time.
                    //
                    //Get the send_date of the specified time
                    $date = $activity["start_date"];
                    //
                    //Get the message to send as a job.
                    $msg = $at->message;
                    //
                    //We also need the type of recipient(individual or group) 
                    //to send the message.
                    $type = $at->recipient->type;
                    //
                    //Get the message recipient depending on the type.
                    $recipient = $type == "individual" ? $at->recipient->user : $at->recipient->business->id;
                    //
                    //The user/users receiving the messages
                    $user = implode(",", $recipient);
                    //
                    //The command parameters. They are:- msg(job_number),type(of
                    //recipient), and extra(further details depending on the type
                    //of recipient).
                    $parameters = "$msg $type $user";
                    //
                    //The schedule messenger file
                    $file = "$home" . "code/scheduler_messenger.php";
                    //
                    //Modify the permissions on the messenger file
                    shell_exec("chmod 777 $file");
                    //
                    //Remove the additional line feeds in the file
                    shell_exec("dos2unix $file");
                    //
                    //The file to execute at the requested time.
                    $command = "$file $parameters | at $date $log" . PHP_EOL . "";
                    //
                    //Add this at job to the batch of at jobs
                    $batch .= $command;
                    //
                    break;
                    //
                case "refresh":
                    //
                    //Extract the start date of the repetitive activity
                    $start_date = $activity["start_date"];
                    //
                    //Extract the ent_date for the repetitive activity
                    $end_date = $activity["end_date"];
                    //
                    //The command for rebuilding the crontab on the start_date 
                    $start_command = "$home" . "code/scheduler_crontab.php | at $start_date $log" . PHP_EOL . "";
                    //
                    //Add this type of job to the batch of at jobs
                    $batch .= $start_command;
                    //
                    //The command for rebuilding the crontab on non repetitive activity's end_date 
                    $end_command = "$home" . "code/scheduler_crontab.php | at $end_date $log" . PHP_EOL . "";
                    //
                    //Add this type of job to the batch of at jobs
                    $batch .= $end_command;
                    //
                    break;
                    //
                case "other":
                    //
                    //The date when the other type of at command is executed
                    $date = $activity["start_date"];
                    //
                    //The user specified at jobs
                    $other = $activity["command"];
                    //
                    //The file to execute in the case of other types of at jobs
                    $file = "$home" . "$other";
                    $log = ">> " . $home . component::log_file;
                    //
                    //A user defined command to run.
                    $command =  "$file | at $date $log " . PHP_EOL . "";
                    //
                    //Add the other type of a at job to the batch of at jobs
                    $batch .= $command;
                    //
                    //
                    //Remove the trailing characters generated by the different operating systems
                    shell_exec("dos2unix $file");
                    //
                    //modify the permissions to allow saving the job to the database
                    shell_exec("chmod 777 $command");
                    //
                    break;
                    //
                default:
                    //
                    //Any other unhandled type should be reported as an error.
                    throw new \Exception("Command type for an at job is not supported.");
            }
        }
        //
        //Compile the batch of at jobs into a at file
        \file_put_contents($this->at_batch_file, $batch);
        //
        //Make the batch file executable from the command line
        \shell_exec("chmod 777 $this->at_batch_file");
        //
        //Clear the at queue before loading the new batch of at jobs
        \shell_exec(" atrm $(atq | cut -f1)");
        //
        //The at command that loads the batch file
        $at = "at -u www-data -f $this->at_batch_file now";
        //
        //Run the at batch file to load the at jobs from the batch file
        $result = \shell_exec($at);
        //
        //If the result is null, the job executed successfully and therefore at jobs can now
        //be scheduled.
        if (is_null($result)) {
            //
            //Stop the process.
            return;
        }
        //
        //Test whether the at command executed at all.
        if (!$result) {
            //
            array_push($this->errors, "The at command '$at' failed to execute.");
        }
    }
    //
    //Refreshing the cron-file with the newly created crontab. This method runs a
    //query that extracts all jobs that are active. i.e jobs started earlier than 
    //today and end later than today. start_date<job>end_date
    public function update_cronfile(): void {
        //
        //1. Formulate the query that gets all the current jobs 
        //i.e., those whose start date is older than now and their end date is
        //younger than now(start_date <= now()< end_date)
        $sql = '
        select 
            activity.name,
            activity.msg,
            activity.command,
            activity.recursion->>"$.repetitive" as repetitive,
            recursion->>"$.start_date" as start_date,
            recursion->>"$.end_date" as end_date,
            recursion->>"$.frequency" as frequency 
        from activity 
        where activity.recursion->>"$.repetitive"="yes" 
        and recursion->>"$.start_date"<= now()<recursion->>"$.end_date"
        ';
        //
        //2. Run the query and return the results
        $jobs = $this->database->get_sql_data($sql);
        //
        //3. Initialize the crontab entries
        $entries = "";
        //
        //4. Loop over each job, extracting the frequency as part of the entry.
        foreach ($jobs as $job) {
            //
            //Get the frequency of the job
            $freq = $job['frequency'];
            //
            //Compile the user
            $user = component::user;
            //
            //The directory where the command file is located
            $directory = component::home;
            //
            //The php command file
            $file = $job['command'];
            //
            //The arguments passed
            $arg = $job['name'];
            //
            //The log file
            $log = ">> " . $directory . component::log_file;
            //
            //The crontab file and the job number makes up the command
            $command = $directory . $file . " " . $arg . "";
            //
            //The crontab entry for sending messages
            $entry = "$freq $command $log \n";
            //
            //Add it to the list of entries
            $entries .= $entry;
            //
            //Remove the trailing characters generated by the different operating systems
            shell_exec("dos2unix $file");
            //
            //modify the permissions to allow saving the job to the database
            shell_exec("chmod 777 $command");
        }
        //
        //5. Create a cron file that contains all crontab entries.
        file_put_contents($this->crontab_data_file, $entries);
        //
        //Modify the file permissions
        shell_exec("chmod 777 $this->crontab_data_file");
        //
        //6. Compile the cronjob. 
        //NOTE:- The php user is identified by www-data
        //and a user needs permissions to set up a crontab otherwise it wont execute
        $command = "crontab " . component::user . $this->crontab_data_file . "";
        //$command= "lss -l";
        //  
        //7. Run the cron job
        $result = shell_exec($command);
        //
        //At this the shell command executed successfully
        if (is_null($result)) {
            //
            //This is a successful execution. Return nothing
            return;
        }
        //At this the shell command executed successfully or it failed. Test whether
        //it failed or not.
        if (!$result)
            throw new \Exception("The crontab command for '$command' failed with the "
                . "following '$result'");
        //
        //The shell command succeeded with a resulting (error) message. Add it to
        //the error collection for reporting purposes.
        array_push($this->errors, $result);
    }
}
