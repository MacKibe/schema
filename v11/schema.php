<?php
namespace root;
//
//Test if am currently in a session 
//if the sessions are not set stat the sessionthis is to prevent the error being
// thrown that the session variable is undefined;
if(session_status()===PHP_SESSION_NONE){
    session_start(); 
    //
    include_once 'config.php';
}
//
//The supper class that supports the common methods for all the classes 
//in a mutall project. The bind_arg(), a method required to support calls from
//javascript is implemented at this level
class mutall{
    //
    //Every mutall object is characterised by this property
    public string $class_name;
    //
    //The namespace of this mutall object
    public string $ns;
    //
    public bool $throw_exception;
    //
    function __construct(bool $throw_exception=true) {
        //
        //What do you do if there are any (entity) errors?. That depends on the
        //3rd parameter -- throw_exception. The Default is true
        $this->bind_arg('throw_exception', $throw_exception, true);
        //
        //
        $reflect = new \ReflectionClass($this);
        //
        $this->class_name = $reflect->getShortName();
        //
        //Add tehnnamespace from which this obet was created
        $this->ns = $reflect->getNamespaceName();
    }
    
    
    //The function that supports executon of arbitray methods on arbitrary class
    //objects from Javascript. This method is called from index.php. Why is this
    //file not part of the schema files?
    static function index(){
        //
        //Test if the class name is provided by the user. If not, re-direct to the
        //default home page.
        if (!isset($_REQUEST['class'])){
            //
            //Class name not provided. Go to the default home page
            //Get the http that referered to this page
            $referer = $_SERVER['HTTP_REFERER'];
            //
            //Extract the url path
            $path = parse_url($referer, PHP_URL_PATH);
            //
            //Retrieve the directory
            $dir = pathinfo($path, PATHINFO_DIRNAME);
            //
            //Retrieve the basename,
            $basename = pathinfo($dir, PATHINFO_BASENAME);      
            //
            //The complete website directory 
            $location = "../$basename/$basename.php";
            //
            //Re-direct to the home page. (Note. Make sure tat there is no echoing
            //before a header is sent!!)
            header("Location: $location");
            exit();
        }
        //
        //The class was providec by the user. The method must also be provided; 
        //otherwise we report an error
        // 
        //Retrieve and set the classname from the url 
         $class=$_REQUEST['class'];
        //
        //Test if there is a method set 
        if(!isset($_REQUEST['method'])){
            //
            //METHOD NOT KNOWN 
            //
            //Die and throw an exception that a method must be set 
            throw new \Exception('The method of the class to execute must be set');
        }
        //
        //METHOD KNOWN 
        //Retrieve and set the method from the query string 
        $method= $_REQUEST['method'];
        //
        //Determine if the desired method is static or not. By default it is dynanic,
        //i.e., not static
        if (isset($_REQUEST['static']) && $_REQUEST['static']){
            //
            //Execute the method and track its result. 
            $result = $class::$method();
        }
        else{
            //
            //Create an object of the class on assumption that the class is not 
            //static. It is possible for this to throw an exception. E.g., 
            //new database($dbname( where $dbnam does not exist. 
            $obj= new $class();
            //
            //Execute the methord ad track its result. 
            $result = $obj->$method();
        }
        //
        //This is the Expected result from the calling method
        return $result;
    }
    
    //Returns true if the named property can be bound to the given value.
    function try_bind_arg(string $name, &$value=null):bool{
    //
        //Check if the value is null
        if (is_null($value)){
            //
            //The value is empty.
            //
            //Get the named property from the server global variables
            if (isset($_REQUEST[$name])){
                //
                //Retrieve the name value
                $value= $_REQUEST[$name];
            }
            //
            //Search in the session variables
            elseif (isset($_SESSION[$name])){
                //
                //Retrieve the name value
                $value= $_SESSION[$name];
            }
            else{
                return false;
            }
        }
        //
        //Set the named propertey to the argument value
        $this->$name = $value;
        //
        return true;
    }
            
    //Bind the named propety of this object to the either given value 
    //or the matching global request , whichever is available in that order
    ///If failure try to bind it to the default value, if provided
    function bind_arg(string $name, &$arg, $default=null):void{
    //
        //Try direct binding to 
        if ($this->try_bind_arg( $name, $arg)){
            return;
        }
        //
        //Try binding to the default value
        if (!is_null($default)){
            $arg = $default;
            $this->$name = $default;
            return;
        }
        //
            throw new \Exception("Argument $name cannot be bounded");
        }
    
    //Report exceptions in a more friendly fashion
    static function get_error(Exception $ex):string {
        //
        //Replace the hash with a line break in teh terace message
        $trace = str_replace("#", "<br/>", $ex->getTraceAsString());
        //
        //Retirn the full message
        return $ex->getMessage() . "<br/>$trace";
    }
    //
    //sets the database access credentials as session keys to avoid passing them
    //any time we require the database object 
    static function save_session($username, $password){
        //
        //save the database credentials as session variables hence we do not have to 
        //overide them anytime we want to acccess the database yet they do not change 
        //Save the username 
        if(!isset( $_SESSION['username'])){
        $_SESSION['username']= $username;
        }
        //
        //Save the password 
        if(!isset( $_SESSION['password'])){
        $_SESSION['password']= $password;
        }
    }
    //The following tow functios are used for intercepting posted data for debugging
    //purposes.
    //
    //1. Save posted data to a file
    static function save_contents(){
       $json = json_encode($_POST);
       file_put_contents('post.json', $json);
    }

    //Retrieve posted data to a file
    static function get_contents(){
       $contents = file_get_contents('post.json');
       $_POST = json_decode($contents, true);
    }
    
     //Offload the properties from the source to the destination
    static function offload_properties($dest, $src){
        //
        // throuhg all the proprties of the source and each property to the
        //destination if it does not exist
        foreach($src as $key=>$value){
            //
            if (!isset($dest->$key)){
                $dest->$key = $value;
            }
            
        }
        return $dest;
    }
    
    //Ignoring the variables that are not used mostly durring destructring 
    //or position based element
    static function ignore(){}

}

//Modelling special mutall objects that are associated with a database schema.
//Database, entity, index and column extends this class. Its main charatreistic
//is that it represents a package whose contents can "saved", resulting in 
//a basic expression.
class schema extends mutall{
    //
    //The partial name of a mutall object is needed for formulated xml tags
    public string $partial_name;
    //
    //The full nam of a schema is needed for for formulating xml tags. It is the
    //schems short name plus the partial name
    public string $full_name;
    //
    //Togle the default auto commit of the trasactions to true of false onorder to 
    //influence a rollback and a commit upon the end of a transaction 
    //The default is a false 
    static bool $roll_back_on_fatal_error=false;
    //
    //A achema object has dual forms. The first one is said to be static; the 
    //second one is activated. When a schema object is activated, the resulting 
    //errors are manaed by ths property
    public array /*error[]*/$errors=[];
    //
    //Data export layouts
    const format_4d = '4 dimension';
    const format_label = 'flat label';
    const format_tabular = 'flat tabular';
    
    //
    //Define the full name of a mutall object set the error handling
    function __construct(string $partial_name) {
        //
        $this->partial_name = $partial_name;
        //
        parent::__construct();
        //
        //Formulate the full name
        $this->full_name = "$this->class_name.$partial_name";
        
    }
    //
    //Saves a schema object to the database by:-
    //-opening the save tag (nameed using teh partial name)
    //-writing the schema object to the database
    //-closing the save tag.
    //The key point about save is that all schema object uses this impleentation
    //AND CANNOT OVVERIDE IT.
    final function save(array $alias=[]): expression{
        //
        //Open the log for this save operation
        $element=log::$current->open_tag($this->full_name);
        //
        //Get the expression returned after a write into the database. Take care 
        //of the fact that the writing may fail with an exception
        $return = $this->write($alias);
        //
        log::$current->add_attr('result', "$return",$element);
        //
        //Close the log for this save
        log::$current->close_tag($element);
        //
        //return the basic expresiion 
        return $return;
    }
    //
    //Every schema must implement its own way of writing to the database; ehen it
    //does, it must return a basic expression. If it does not implement a write 
    //method then this default one will throw throw an exception. 
        //
    //The write functionality is built around classes in the capture namespace
    //that are extensins of the root versions. This approach helps to separate 
    //root and catier e operations, thus minimising the root fot print.
    /*abstract */function write(array $alias=[]):expression{
        //
        //Use the alias to switch off the warning...
        $msg = print_r($alias, false);
        //
        throw new \Exception('This class '.$this->class_name." should implement mutall->write($msg) method");
    }
    
    //Write the given columns and an alias to the current dataase and return the valid and invalid
    //cases
    //$alias shows the exact entity record to be retrieved
     function write_columns(array $schemas, array $alias):array/*[valid*, invalid*]*/{
       //
       //Save the given columns to the database retutinhing their statuses, ie., 
       //error or otherwise
       $statuses = array_map(fn($schema)=>$schema->save($alias), $schemas);
        //
        //Filter out the errors from this statuses which invalidate the ability 
       //of this index to save the current record
        $errors = array_filter($statuses, fn($status)=>$status->is_error());
        //
        $oks = array_filter($statuses, fn($status)=>!($status->is_error()));
        //
        //Return the result
        return ['valids'=>$oks, 'invalids'=>$errors];
    }
    
    //Returns the named database if it was previously opened; otherwise it creates
    //one from either serialized data or information schema. The former is applied
    //if the user requests it explicity. Typicaly this is the case when we access
    //the same data through mutipl page calls fom javascript. This feature was 
    //designed to address the slow response of retrieving metadata from the 
    //iformationn schema.
    //The method is designed to be called from javascript
    function open_dbase(string $dbname=null,  string $dbns=null, bool $use_serialize=null): database{
        //
        //Bind the database name. It is mandatory
        $this->bind_arg('dbname', $dbname);
        //
        //Bind the namespace; the default is that of that of this opbejct
        $this->bind_arg('dbns', $dbns, $this->ns);
        //
        //Bind whether to use serialized data or not. The default is not to use 
        //serialised version especially in during development. The user deliberately
        //switches it on to improve persformance acrosss eb pasges
        $this->bind_arg('use_serialize', $use_serialize, false);
        //
        //Compile the fully qualified dabasse
        $database = "$dbns\\database";
        //
        //Test if the database requested was previously opened 
        //
        //Test if this database is a fresh one (not from serialization). Note
        //that datase::$current is defiend at to levels: root and capture to 
        //reccognise that we are dealing with 2 different datababases named the
        //same
        if(isset($database::$current[$dbname])){ return $database::$current[$dbname];}
        //
        //If the serialization is not requested, then simply create a namespace
        //sensitive database.
        if (!$use_serialize){
            //
            //Create the database (IN TEH CURRENT NAMSEPACE)and make it current
            $dbase = new $database($dbname);
            //
            //Set the namespace-aware current database
            $database::$current[$dbname]= $dbase;
            //
            return $dbase;
        }
        //
        //Serialization can be used
        //
        //Chech whenther there exists a database ti be unseralialized IN THE
        //CURRENT NAMESPACE
        if (isset ($_SESSION['databases'][$dbns][$dbname])) {
            //
            //Yes there is ne. Unserializes it and ma it current (in the CURRENT
            //NAMESPACE)
            //
            //return the serializes version
            $dbase = unserialize($_SESSION['databases'][$dbns][$dbname]);
            //
            //Set the namespace-aware current database
            $database::$current[$dbname]= $dbase;
            //
            return $dbase;
        } 
        //
        //As a last resort create a database from information schema
        $dbase_fresh =  new $database($dbname);
        //
        //Set the namespace-aware current database
        $database::$current[$dbname]= $dbase_fresh;
         //
        //Serlilaise the database and save it IN THE CURRENT NAMESPACE
        $_SESSION['databases'][$dbns][$dbname]= serialize($dbase_fresh);
        //
        //Return a database populated from first principles
        return $dbase_fresh;
    }

    

    //Add fields (to this schema object) derived from the given comment string 
    //provided as a json 
    function add_comments(string $json):void{
        //
        //Test if the comment is empty, then it has nothig to add
        if (empty($json)){return;}
        //
        //Decode the comment json string to a php (stdClass) object, it may 
        //fail. 
        try{
            //
            //Add the comment property to teh entoty
            $comment = json_decode($json, JSON_THROW_ON_ERROR);
            //
            //Offload the comment fields to this schema object
            mutall::offload_properties($this, $comment);
        }catch(Exception $ex){
            //
            //Compile the error message
            $msg = "Invalid json string in the comment of $this->class_name";
            //
            //Add the error to those of activating the schema object
            $this->errors[] = new myerror($msg, mutall::get_error($ex));
}
    }
}

//Modelling the database as a schema object (so that it too can save data to 
//other databses)
class database extends schema{
    //
    //An array of entties the are the collection of the tables that are required to create a 
    //database 
    public array $entities=[];
    //
    //This is the pdo property that allows us to query and retrieve information from 
    //the database it is a property to avoid this class from extending a pdo
    protected \PDO $pdo;
    
    //Let the user set what should be considred as the default database. This is 
    //the database that is picked if a daabase name is not given explicity. This 
    //is designed to simplify working with a single database.
    static database $default;
    //
    //An aray of ready to use databases (previously descrobed as unserialized). 
    static array/*database[name]*/ $current=[];
    //
    //The database constructor requires the following parameters (assuming that
    //it will be calelds from javascript):- 
    //name: name of the database which is mandatory 
    //complete: an optional boolean that indicates whether we desire a database
    //complete with irts entis or not. The the default is complete. If not 
    //an empty shell is returned; this may be useful when quering the database
    //directly, i.e., wihput teh need of the object model
    function __construct(string $name=null, bool $complete=null, $throw_exception=null){
        //
        //Bind the (mandatory) dbname 
        $this->bind_arg('name', $name);
        //
        //Construct the parent 
        parent::__construct($name);
        //
        //Set the default value of the optional complete as true
        $this->bind_arg('complete', $complete, true);
        //
        //What do you do if there are any (entity) errors?. That depends on the
        //3rd parameter -- throw_exception. The Default is true
        $this->bind_arg('throw_exception', $throw_exception, true);
        //
        //Connect to the database
        $this->connect();
        //
        //Set teh current database, so tthat it can be accessede by all her 
        //dependants during activation.
        database::$current[$name]=$this;
        //
        //Attend to the 'complete' option. You are done if an incomplete database 
        //is required. Don waste time on entities. This is important if all we
        //want is to run a query
        if (!$complete) { return; }
        //
        //Activate the schema objects (e.g., entities, columns, etc) associated
        //with this database
        $this->activate_schema();
        //
        //Populate the dbase with aliased entities, i.e., aliens using the
        //already populated entities.
        $this->compile_aliens();
        //
        //Set the relational dependency for all the entities and log all the 
        //cyclic conditions as errors.
        $this->set_entity_depths();
        //
        //Depending on the the throw_exception setting...
        if ($throw_exception){
            //
            //Compile the error report.
            //
            //start with an empty report and no_of_errors as 0
            $no_of_errors=0; $report="";
            //
            $this->get_error_report($no_of_errors, $report);
            //
            if  ($no_of_errors>0){
                throw new \Exception($report);
            }
        }
    }
    
    //Activate the schema objects (e.g., entities, columns, etc) associated
    //with this database
    private function activate_schema(){
        //
        //Query the information information scheme once for the following data
        //
        //Activate all the entities of this database from the tables of the 
        //information
        $this->activate_entities();
        //
        //Activate all the columns of this database from the columns of the 
        //information schema
        $this->activate_columns();
        //
        //Activate all the identification inices from the statistics of the 
        //information schema
        $this->activate_indices();
    }
    
    //Activate all the entities of this database by querying the information schema.
    //This method needs to be overriden to extend entities, for instance, when 
    //entities in the capture namespace are created from those in the root.
    function activate_entities(): void{
        //
        //Get all the static entities from the information schema's table.
        $tables = $this->get_entities();
        //
        //Now activate the entities, indexing them as you go along
        foreach($tables as [$dbname, $ename, $comment]){
            //
            //Create the entity in the root namespace
            $entity = new entity($ename, $dbname);
            //
            //Add fields derived from comments
            $entity->add_comments($comment);
            //
            //Push the entity object to the array to be returned
            $this->entities[$ename] = $entity;
        }
    }
    
    //Retyrn all th tables of this database from the nformation schema
    private function get_entities():array/*[dbname, ename, comment][]*/{
        //
        //Let $sql be the statement for for retrieving the entities of this
        //database.
        $sql = "select "
            //    
            . "table_schema as dbname, "
            //    
            . "table_name as ename, "
            //    
            . "table_comment as comment "
        . "from "
            . "information_schema.tables "
        . "where "
            //
            //Only tables of the current database are considerd
            . "table_schema = '$this->name' "
            //
            //Exclude the views
            . "and table_type = 'BASE TABLE'";
        //
        //Execute the $sql on the the schema to get the $result
        $result = $this->pdo->query($sql);
        //
        //Retrueve the entires from the $result as an array
        $tables = $result->fetchAll();
        //
        //Return the tables list.
        return $tables;
    }
  
    //Activate all the columns of all the tables in this database. This can be
    //overriden, so it is public
    function activate_columns():void{
        //
        //Get the static columns from the information schema
        $columns = $this->get_columns(); 
        //
        //Get the current database
        $dbase = database::$current[$this->name];
        //
        foreach($columns as [$dbname, $ename, $cname, $data_type, $default, $is_nullable, $comment]){
            //
            //Compile the column options
            $options = new \stdClass();
            $options->data_type = $data_type;
            $options->default = $default;
            $options->is_nullable =$is_nullable;
            $options->comment = $comment;
            //
            //Create an ordinary column. It will be upgrated to a forein key
            //at a later stage, if necessary
            $column=new attribute($dbname, $ename, $cname, $options);
            //
            //Offload the options to the column
            mutall::offload_properties($column, $options);
            //
            //Add fields derived from comments
            $column->add_comments($comment);
            //
            //Add the column to the database
            $dbase->entities[$ename]->columns[$cname]= $column; 
        }
        //
        //Activate the foreign key colums
         //
        //Promote attributes to foreign keys where necessary, using the column 
        //usage of the information schema
        $this->activate_foreign_keys();
     }
  
    //Get all the columns for all the tables in this database
    private function get_columns():array/**/{
        //Select the columns of this entity from the database's information schema
        $sql = "select "
            //
            ."table_schema as dbame, "
            //
            //specifying the exact table to get the column from
            . "table_name as ename, "
            
            //Shorten the column name
            . "column_name as cname, "
            //
            //Specifying the type of data in that column
            . "data_type, "
            //
            //Get the default 
            . "column_default as `default`, "
            //
            //if it is nullable
            . "is_nullable, "
              //
            //Extract any meta data json information in the comments
            . "column_comment as comment "
        . "from "
            //
            //The main driver of this query
            . "information_schema.`columns` "
        . "where "
            //    
            // The table schema is the name of the database
            . "table_schema = '{$this->name}' "
            //
            //Exclude primary keys
            . "and not(column_key='PRI')";
        //
        //Get the entities database. We may not be calling this from a database
        //since an entity can be created without referring to a database. Hence its
        //open, rtaher than $current
        $dbase= $this->open_dbase($this->name);
        //
        //Execute the $sql on the the schema to get the $result
        $result = $dbase->pdo->query($sql);
        //
        //Retrueve the entitiesfrom the $result as an array
        return $result->fetchAll();
    }
    
    //Promote existing to foreign keys where necessary, using the column 
    //usage of the information schema
    private function activate_foreign_keys(){
        //
        //Get the current database
        $dbase = database::$current[$this->name];
        //
        //Get the static foregn key columns 
        $columns = $this->get_foreign_keys();
        //
        //Use the columns to promote the matching attribute to foreign keys
        foreach($columns as [$dbname, $ename, $cname, $ref_table_name, $ref_db_name]){
            //
            //Get the matching attribute. It must be set by this time
            $attr = $dbase->entities[$ename]->columns[$cname];
            //
            //Pair teh referenecd table and databases
            $ref = new \stdClass();
            $ref->table_name = $ref_table_name;
            $ref->db_name = $ref_db_name;
            //
            //Create a foreign key colum using the same name
            $foreign = new foreign($dbname, $ename, $cname, $attr->options, $ref);
            //
            //Offload the options to the foreign. (Why is this necesary???)
            mutall::offload_properties($foreign, $attr->options);
            //
            //Replace the attrite with the forein key
            $dbase->entities[$ename]->columns[$cname] = $foreign;
        }
    }
     
    //Update some ordinary columns to foreign columns base on the key column 
    //usage table
    private function get_foreign_keys(): array/*[dbname, ename, cname, ref_table_name, ref_db_name][]*/{
        //
        //Set sql statement for selecting all foreign key columns of this table 
        //and database
        $sql = "select "
                
            // The table schema is the name of this database
            . "table_schema  as dbname, "
            //
            //specifying the exact table to get the column from
            . "table_name as ename, "    
            //
            . "column_name as cname, "
            //
            //Specify the referenced table and her database
            . "referenced_table_name as ref_table_name, "
            //    
            . "referenced_table_schema as ref_db_name "
                
        . "from "
            //
            //The main driver of this query
            . "information_schema.key_column_usage "
        . "where "
            //    
            // The table schema is the name of this database
            . "table_schema = '{$this->dbname}' "
            //
            //The column must be used as a relation (i.e., as a forein key) 
            . "and referenced_table_schema is not null ";
         //
        $dbase= database::$current[$this->dbname];
        //
        //Execute the $sql on the the schema to get the $result
        $result = $dbase->pdo->query($sql);
        //
        //Retrueve the entitiesfrom the $result as an array
        return $result->fetchAll();
    }
    
    
    //Activate all the identification indices from the statistics of the 
    //information schema. This can be overriden
    function activate_indices(){
        //
        //Get all the index columns for all the indices for all the entities
        //in this database
        $columns = $this->get_index_columns();
        //
        //Build the indices and thier active columns
        foreach($columns as [$dbname, $ename, $ixname, $cname]){
            //
            //Get the current database for packing the index rows
            $dbase = database::$current[$dbname];
            //
            //Get the named index;
            $index = $dbase->entities[$ename]->indices[$ixname] ?? null;
            //
            //If it does not exist, create it
            if (is_null($index)){
                //
                //Create a new index
                $index = new index($dbname, $ename, $ixname);
                //
                //Add the index to the entity
                $dbase->entities[$ename]->indices[$ixname]=$index;
            }
            //
            //Set the index column; this implies that the columns must be activated
            //before indices
            $index->columns[$cname] = $dbase->entities[$ename]->columns[$cname];
        }
    }
    
    //Get all the static index columns for all the incdices for all the entities
    //in this database
    private function get_index_columns():array/*[][]*/{
        //
        //The sql that obtains the column names
        $sql=  "select "
            //    
            . "index_schema as dbname, "
            //
            . "table_name as ename, "
               // 
            . "index_name  as ixname, " 
             //  
            . "column_name as cname "
            //
        . "from "
            //
            //The main driver of this query
            . "information_schema.statistics "
        . "where "
            //    
            // Only index rows from this datbase are considerd
            . "index_schema = '{$this->dbname}' "
            // 
            //Identification fields have patterns like id2, identification3
            . "and index_name like 'id%'"; 
        //
        $dbase= database::$current[$this->dbname];
        
        //Execute the $sql on the the schema to get the $result
        $result = $dbase->pdo->query($sql);
        //
        //Retrueve the entitiesfrom the $result as an array
        return $result->fetchAll();    
    }
    
    
    //Returns an error report and the numbet\r of errors it contains
    private function get_error_report(int &$no_of_errors, string &$report):void{
        //
        //Start with an empty report
        $report = "";
        
        //Report errors ate the database level
        $count = count($this->errors);
        //
        //Report the errors if necessary.
        if ($count>0){
            $report.=print_r($this->errors, false);
        }
        //
        //Report entoty errors
        foreach($this->entities as $entity){
            //
            $entity->get_error_report($no_of_errors, $report);
        }   
    }
    
    //Set the dependency depths for all the entities as weell as loggin any 
    //cyclic errors
    function set_entity_depths():void{
        //
       foreach($this->entities as $entity){
           //
           //Get he paths pof this entity and check for cyclic errors.
           $paths = $entity->collect_id_paths($this->throw_exception);
           //
           //
           $counts = array_map(fn($path)=>count($path), $paths);
           //
           //
           $entity->depth = count($counts)==0 ? 0 : max($counts);
       } 
    }


    //Report errrors arising out of the activation process, rather than throw 
    //than error as it occurs
    private function report_activation_errors(){
        //
        //Get teh numbe of errors
        $count = count(self::$errors);
        //
        //There has to be at leason one error for the reporting to be done
        if ($count===0) {return; }
        //
        $msg = "There are $count activation errors. They are:-<br/>"
            .implode(".<br/>", database::$errors);
        //
        throw new \Exception($msg);
    }
    
    
    //Populates the entities with aliens. An alien is an alised entity. Aliens 
    //were introdcuced to solve the forking problem.
    private function compile_aliens(){
        //
        //Identifying aliens and update the relational data model
        //
        //Aliens occur when an entity is referenced by more than 1 foreign column 
        //that share teh same home entity, i.e., forking
        //
        //Loop through all the entities to retrieve only the foreign columns that
        //share teh same home entity
        foreach ($this->entities as $entity){
            //
            //Get the foreign key columns 
            $foreigners= array_filter($entity->columns,fn($column)=>$column instanceof foreign);
            //
            //Get the foreigns keys that point to an enity that neess aliasing
            $candidates = array_filter($foreigners, fn($foreigner)=>$foreigner->is_alien());
            //
            //Step through the candidates and convert their referenecd tables to 
            //aliens
            foreach ($candidates as $candidate){
                //
                //The name of the alias name
                $alias_name = $candidate->name;
                //
                //Get the referenced table and database names
                $ref_table_name = $candidate->ref->table_name;
                $ref_db_name = $candidate->ref->db_name;
                //
                //Create an alien, i.e., an aliased entity
                $alien=new alien($ref_db_name, $ref_table_name,  $alias_name);
                //
                //Re-route the referenced table of the candidate foriegner to the
                //alien's name
                $candidate->ref->table_name = $alias_name;
                //
                //Save the alien, i.e, alias enyoty to the entities collection.
                $this->entities[$alias_name]=$alien;
            }  
            //
            
       }
    }
    
    //When you serialize a database, exclude the pdo property. Otherwise you
    //get a runtime error.
    function __sleep() {
        return ['name', 'entities'];
    }
    
    //Set the pdo property when the database is unserialized    
    function __wakeup(){
        $this->connect();
    }
    
    //Returns data after executing the given sql on this database
    function get_sql_data(string $sql=null) :array{
        //
        //Bind the sql statement and database name
        if(is_null($sql)){$this->bind_arg('sql', $sql);}
        //
        //Query the database using the given sql
        $results = $this->pdo->query($sql);
        //
        //Fetch all the data from the database -- indexed by the column name
        $data = $results->fetchAll(\PDO::FETCH_ASSOC);
        //
        //Return the fetched data                
        return $data;
    }
    
    //Turns off autocommit mode. Hence changes made to the database via $this->pdo
    //are not committed until you end the transaction by calling $this->commit()
    //or $this->rollBack
    function beginTransaction():void{
        $this->pdo->beginTransaction();
    }
    
    //Save the changes made to the database permanently 
    function commit():void{
        $this->pdo->commit();         
    }
    
    //Roles back the current transaction. i.e avoid commiting it permanently 
    //to the database.Please note this function is only effective if we had begun
    // a transaction
    function rollBack():void{
        $this->pdo->rollBack();
    }
    

    //Overrding teh query method so that it can be evoked from JS. We use this
    //qiery method for sqls that dont return a result
    function query($sql=null){
        //
        //Bind the sql statement and database name
        if(is_null($sql)){$this->bind_arg('sql', $sql);}
        //
        //Query the database using the gven sql
        return $this->pdo->query($sql);
    }
    
    //Set the PDO property of this database; this links the mutall database 
    //model to the PHP vesrion.
    private function connect(){
        //
        //Formulate the full database name string, as required by MySql. Yes, this
        //assumed this model is for MySql database systems
        $dbname = "mysql:host=localhost;dbname=$this->name";
        //
        //Initialize the PDO property. The server login credentials are maintained
        //in a config file.
        $this->pdo = new \PDO($dbname, config::username, config::password);
        //
        //Throw exceptions on database errors, rather thn returning
        //false on querying the dabase -- which can be tedious to handle for the 
        //errors 
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        //
        //Prepare variables (e.g., current and userialised) to support the 
        //schema::open_databes() functionality. This is designed to open a database
        //without having to use the information schema which is terribly slow.
        //(Is is slow wor badly written? Revisit the slow issue with fewer 
        //querying of the information schema)
        //Save this database in a ready, i.e., unserialized,  form
        database::$current[$this->name]= $this;
        //
        //Add support for transaction rolling back, if valid. See the 
        //\capture record->export() method
        if (isset(schema::$roll_back_on_fatal_error) && schema::$roll_back_on_fatal_error){
            $this->pdo->beginTransaction();
        }
    }
    
    //Returns a json string of this database structure 
    function __toString() {
        //
        //Encode the database structure to a json string, throwing exception if
        //this is not possible
        try{
            $result= json_encode($this, JSON_THROW_ON_ERROR);
        }catch(Expection $ex){
            $result = mutall::get_error($ex); 
        }
        //
        return $result;
    }
    
    //Returns the primary key value of the last inserted in a database.
    //Remember that pdo is prrotected, and so cannot be accessed directly
    function lastInsertId(){
        return $this->pdo->lastInsertId() ;
    }
} 

//Modelling the (arithmetic) expressions used in formulating sql statements. 
//E.g., 2, 2*amount, etc.
abstract class expression extends mutall{
    //
   //
   function __construct() {
       parent::__construct();   
   }
    //
    //An expresiion to (SQL) string is implemented in various ways depending on what 
    //expression it is. You get a runtime error if not
    function to_str(): string{
        //
        throw new \Exception("No to_str string method found for $this->class_name");
    }
    
    //Yield the entity name in this expression. This generator is used for 
    //identifying ename targets of a partial select.
    //An expression, in gemaral. does not yoedl an entoty name. See column
    function get_ename(){}
    
    //An expresion is not ann error by default.
    function is_error(){return false; }
    
    
    //Every expression has the ability to populate inself in a pot i.e 
    //Given a pot an expression can populate its self 
   function pack(array &$pot, string $cname, string $ename, array $alias=[], string $dbname=null):void{
       //
       //A database must be available
       if (is_null($dbname) &! isset(database::$default->name)){
           throw new \Exception("Database name is not specified");
       }
       //
       //Set the dbname dimension if not set 
       if (!isset($pot[$dbname])){
           $pot[$dbname] =[];
       }
       //
       //Set the ename dimention if not set 
       if (!isset($pot[$dbname][$ename])){
           $pot[$dbname][$ename] = new \Ds\Map();
       }
       //
       //Set the alias dimension if it is  not set 
       if (!isset($pot[$dbname][$ename][$alias])){
           $pot[$dbname][$ename][$alias] = [];
       }
       //
       //Test if this column is set 
       //
       //Column is set report a deblicate entry by setting its status to an error 
       if (isset($pot[$dbname][$ename][$alias][$cname])){
           //
           //There is a problem!!!!!!!!Yiou are overwriting data
           throw new \Exception('You may be overwriting exsitng data', [$ename, $alias, $cname]);
       }
       //
       //Set the complete three dimentional pot [ename][alias][cname]
       $pot[$dbname][$ename][$alias][$cname] = $this;
   }

}

//Class that represents an entity. An entity is a schema object, which means 
//that it can be saved to a database.
class entity extends schema{
    //
    //Name of the entity and the coodinates of the entity
    public $name;
    public $dbname;
    //
    //The relation depth of this entity. The defeault is 0; 
    public ?int $depth=null;
    //
    //The json user information retrieved from the comment after it was decoded  
    public $comment;
    //
    //Defining the instance of a child class column that feed the entity with more 
    //properties popilated by the function get column()
    public $columns=[];
    //
    //Defining the array $induces that is used to store the indexed columns 
    //from the function get_induces 
    public $indices=[];
    //
    //The pointers of this entity. This is a private property. Use 
    //get_pointers() if you wich to cces the pointers of an entity because we
    //need to construct them initially
    private array /*foreigner[name]*/$pointers=[];
    //
    //represents a database table
    //designed to be called from javascript
    // The entity constructor requires:- both mandatory
    // a) the entity name 
    // b) the parent database name 
    function __construct(string $name= null, string $dbname=null){
        //
        //bind the arguments if they are null
        $this->bind_arg('name', $name);
        $this->bind_arg('dbname', $dbname);
        //
        //Create the parent
        parent::__construct($name);
    }
    
    //Returns an error report and the numbet\r of errors it contains
    function get_error_report(int &$no_of_errors, string &$report):void{
        //        
        //Report errors ate the database level
        $count = count($this->errors);
        //
        //Report the errors if necessary.
        if ($count>0){
            $report.=print_r($this->errors, false);
        }
        //
        //Report entoty errors
        foreach($this->columns as $column){
            //
            $column->get_error_report($no_of_errors, $report);
        }   
    }

    //Returns the string version of this entity expression 
    function to_str(): string {
        return "`$this->name`";
    }
    
    //Retuns the 'from' clause of an sql statement
    function get_ename(): string {
         return "`$this->name`";
    }

    //A private property that represebts the current foreigners
    private $foreigners_=null;
    
    //Return pointers and foreign key columns of this entity, jointly called
    //foreigners. This has to be a function; hopefully it wil be called when 
    //the entities of the database have been constructed.
    function foreigners():array/*of foreigner*/{
        //
        //Test if we have been here before. If so, return the current foreinres
        if (!is_null($this->foreigners_)) {return $this->foreigners_;} 
        //
        //Select only the foreign key columns of this entity
        $home = array_filter($this->columns, function($column){
            //
            return $column instanceof foreign;            
        });
        //
        //Collect all the (away) foreign key colujmns, a.k.a, pointers
        $away= $this->get_pointers();
        //
        $this->foreigners_ = array_merge($home,$away);
        //
        //Return the joint foreigners as the home and away based foreign key
        //columns.
        return $this->foreigners_;    
    }
   
    //returns an array of all the pointers that reference this entity 
    function get_pointers() :array/*with pointers */{
        //
        //Do not use first principles if the pointers are ready
        //if (isset($this->pointers)) {return $this->pointers;}
        //
        //Work out the entity pointrrs from first principle, beginnig with an 
        //empty array 
        $this->pointers=[];
        //
        //Populate the pointers with from the entities hence we can only call 
        //this methord after we have completely created the database 
        //
        //Loop through all the columns of all the entities
        foreach (database::$current[$this->dbname]->entities as $entity){
            //
            foreach($entity->columns as $column){
                //
                //Only foreign key columns contrute to pointers
                if ($column instanceof foreign){
                    //
                    //Get the name of the referenced dabaase and entity names
                    $ref_db_name = $column->ref_table_name;
                    $ref_table_name = $column->ref_table_name;
                    //
                    //Test if the referenced ename is this entity 
                    //if it is this entity create a pointer and push it 
                    //in the array
                    if($ref_table_name===$this->name){
                        //
                        //Create a pointer 
                        $pointer = new pointer($column);
                        //
                        //push the pointer in the pointers array
                        $this->pointers[]=$pointer;  
                    }    
                }
            }
        }
        //
        //
        return $this->pointers;
    }
    
    //Returns the "relational dependency". It is the longest identification path 
    //from this entity. 
    
    //
    // of this entity based on foreign keys. If
    //a cyclic errpor is detected, then we log the error and return null.
    function get_dependency(array &$path): ?int{
        //
        //Check whether this entty is already in the path. If it is, then this is 
        //a cyclic problem.
        if (in_array($this, $path)){
            //
            $path_str = print_r($path, false);
            //
            //Report cyclic error
            $this->errors[]=new myerror("Cyclick error for entity $this->name", $path_str);
            //
            return null;
        }
        //Put thhis entity in teh patth
        $path[]=$this;
        //
        //Compare the current curenmt dependdeny with tahte of this entoty and pick 
        //the larger of the two.
        
        //1.Test if there is an already existing dependency and if indexes are present 
        ///
        //1.a Test if we already know the dependency. If we do just return it...
        if (!is_null($this->depth)){ return $this->depth;}
        //
        //...otherwise calculate it from 1st principles.
        //1.b Test if there are induces 
        //Some of the entities do not have idices hence if the indices array is 
        //empty return a null
        if(empty($this->indices)){return null;}
        //
        //2.Filter the columns of this entity and remain with only the ids
        //Filter out all the columns that are used for identification
        $ixcolumns= array_filter($this->columns,fn($col)=>$col->is_id());
        //
        //Filter and remain with the column foreigns that are used for identification
        $f_ids= array_filter($ixcolumns, fn($col)=>$col instanceof foreign);
        //
        //3.test if the foreigners are empty
        //
        //Test if there are no foreign key columns, return 0.
        if(sizeof($f_ids)==0){$this->depth=0;}
        //
        ////4.Map the id columns with the dependency of the referenced entity
        //There are foreign keys as indexes
        else{
            //Map the columns with the dependencies if the referenced entity 
            $cols_depths=array_map(function($col)use($path){
                //
                //Get the referenced entity name
                $ename= $col->ref_table_name;
                //
                //Get the affected entity using a database open, in case the 
                //referenced database is not the current one. This is true for
                //data that spans multiple datbases
                 $dbase= $this->open_dbase($col->ref_db_name);
                //
                //Get the actual entity
                $entity = $dbase->entities[$ename];
                //
                //Get the referenced entity's dependency.
                return $entity->get_dependency($path);
            }, $f_ids);
            //
            //Get the maximum dependency
            //Get the foreign key entity with the maximum dependency, x
            $max_dependency = max($cols_depths);
            //
            //Set the dependency
            $this->dependency_ = $max_dependency + 1;
        }
        //
        //The dependency to return is x+1
        return $this->dependency_;
    }
    
    //Return all the valid paths from this entity following the id home based foreigners 
    //as the terminal condition
    function collect_id_paths(bool $throw_exception=true):array/*<array<path>>*/{
      //
      //begin with an empty path 
      $paths=[];
      //
      //populate the empty path with a path 
      foreach ($this->yield_id_paths([], $throw_exception) as $path){
          //
          //add it to the collection of the paths 
          $paths[]=$path;
      }
      //
      //<array<path>>
      return $paths;
    }
    
    //yields all a complete set of paths from this entity following the foreignn 
    //keys used for identification. The terminal condition for the search is 
    //when we get to an entity thta has no foreign keys.
    private function yield_id_paths(/*foreign[cname]*/array $path, bool $throw_exception=true):\Generator{
        //
        //Get the terminal condition of this method. You need the identififiaction
        // foreign keys
        //
        //Select all the home based foreigners
        $foreigners = array_filter(
                
            $this->columns,
                
            fn($col)=> $col->is_id()
                && $col instanceof foreign
                //
                //Excluded hierarchical situations
                && $col->ename === $col->ref_table_name
        );
        //
        //Test if we are at the end of the path. We are if thera ere no more home 
        //based id foreigners 
        if (count($foreigners)==0){
            //
            //We are at the terminal condition so we yield the path
            yield $path; 
            
        }
        //Change to a new path source entity.
        else{
            //
            //Loop through the generators and yield a path in each by repeating 
            //this process
            foreach($foreigners as $foreigner){
                //
                //Test for cyclic looping. It occurs when the foreigner is already
                //in the path
                if (in_array($foreigner, $path)){
                    //
                    //Addin thos foreiogenr to the path will result to a cyclic
                    //loop
                    
                    $msg = "Cyclic loop when {$foreigner->to_str()} is added to path" ;
                    //
                    if ($throw_exception){
                        throw new Exeption($msg);
                    }else{
                        //
                        $this->errors[] = new myerror($msg, $path);
                    }
                }else{
                    //
                    //Add this foreigner to this path 
                    $path2 = $path + [$foreigner];
                    //
                    //Get the new source; It may be from a different database from
                    //the current one.
                    $dbase = $this->open_dbase($foreigner->ref_db_name);
                    $source2=$dbase->entities[$foreigner->ref_table_name];
                    //
                    //yield the new path
                    yield from $source2->collect_id_paths($path2);
                }
            }
        }
    }
    
    
    //returns true if the entity is used for  reporting 
    function reporting(){
        //
        //Check if the purpose is set at the comment
        if(isset($this->comment->purpose)){
         //
         //Return the repoting status
         return  $this->comment->purpose=='reporting';
         //
         //else return a false 
        }
        return false;
    }
   
}

//Models an alias of an entity
class alien extends entity{
    //
    public $alias_name;
    //
    function __construct($dbname, $ename, $alias_name) {
        //
        $this->alias_name=$alias_name;
        //
        parent::__construct($ename,$dbname);
        //
        //Ovveride teh partial name
        $this->partial_name = $alias_name;
    }
   
}

//Models the index of an entity (needed for unique identification of database 
//entries) as a schema object. That means that it is capable of writing to a 
//database
class index extends schema{
    //
    //Saves the column names obtained by this index name
    public $columns=[];
    //
    //The constructor requires three mandatory parameters 
    //1. dbname the name of the database 
    //2. ename which is the name of the entity this index belongs to 
    //3. The name of the index 
    function __construct(string $dbname, string $ename, string $ixname) {
        //
        //bind the parameters 
        $this->bind_arg('dbname', $dbname);
        $this->bind_arg('ename', $ename);
        $this->bind_arg('ixname', $ixname);
        //
        //construct the parent, using a partial name that includes the entity 
        parent::__construct("$ename.$ixname");
    }
    
    //Returns the string version of this index ???? 
    function to_str(): string {
        return "`$this->ename`";
    }
    
    //Returns the ename of this index???????
    function get_ename(): string {
         return "`$this->ename`";
    }

}

//Modeling the columns of an entity as the smallest package that whose 
//contents can be "saved" to a database
abstract class column extends schema{
    //
    //Every column should have a name 
    public $name;
    
    //the three properties that are required to identify a column the name, ename and 
    //the dbname 
    public $ename;
    //
    //The parent of this column proptected inorder to enable json encoding
    public $dbname;
    //
    //The class constructor
    function __construct(string $dbname=null, string $ename= null, string $name=null, \stdClass $options=null) {
        //
        //bind the arguments
        $this->bind_arg('name', $name);
        $this->bind_arg('ename', $ename);
        $this->bind_arg('dbname',$dbname);
        $this->bind_arg('options',$options);
        //
        //construct the parent mutall
        parent::__construct("$ename.$name");
        //
        //activate this column with the data this inncludes, nullable, length,datatype
        //default value
        database::offload_properties($this, $options);
    }
    //
    // //Returns an error report and the numbet\r of errors it contains
    public function get_error_report(int &$no_of_errors, string &$report):void{
        //        
        //Report errors ate the database level
        $count = count($this->errors);
        $no_of_errors+=$count;
        //
        //Report the errors if necessary.
        if ($count>0){
            $report.=print_r($this->errors, false);
        }   
    }

    //
    //Since i can not acess a protected property
    //(Lawrence, who uses this method? Shoudld it not be named entity() as it is
    //tring to return the entity of this column?)
     function get_parent(){
        //
        //Test first if the entity exists before returninig
        $entity = database::$current->entities[$this->ename] ?? null;
        //
        //If the entity is a null or undefined inform the user to check the 
        //spellings 
        if(is_null($entity) || !isset($entity)){
            
            throw new \Exception("The parent entity named $this->ename an database $this->dbname was not found "
                    . "please check your spelling and ensure you have the correct database name");
        }
        return $entity;
    }
    
    //Returns the non-structural colums of this entity, a.k.a, cross members. 
    //These are optional foreign key columns, i.e., thhose that are nullable.
    //They are important for avidng cyclic loops during saving of data to database
    function is_cross_member(){
       return $this instanceof foreign && $this->is_nullable==='YES';
    }

    //Returns a true if this column is used by any identification index; 
    //otherwise it returns false. Identification columns are part of what is
    //knwn as structural columns.
    function is_id(): bool{
        //
        //Get the indices of the parent entity 
        $indices=database::$current[$this->dbname]->entities[$this->ename]->indices;
        //
        //test if this column is used as an index 
        foreach ($indices as $index) {
            if(in_array($this->name, $index->columns)){
                return true;
            }
        }
        //
        return false;
    }
    
    //
    //Returns a true if this column is used as a descriptive field 
    function is_descriptive(): bool{
        //
        //The descriptive columns are those named, name, title or decription 
        if(in_array($this->name, ['name','description','title'])){
            return true;
        }
        //
        //
       else {return false;}
    }
}

//Template for the second type of column called columns attribute
//It constructor require the following mandatory parameters
//The column name as name 
//The name of the entity to which the column belings as ename 
//The database name to which the entity belongs 
class attribute extends column{
    //
    function __construct(string $dbname=null, string $ename= null, string $name=null, \stdClass $options=null) {
         //
        //The parent constructor 
        parent::__construct($dbname, $ename, $name, $options);
    }
     
     //Returns the string version of this entity expression 
    function to_str(): string {
        return "{$this->ename}.$this->name";
    }
    
    //returns the name
    function get_ename(): string {
         return "{$this->ename}.$this->name";
    }

}

//This is the class that has all the columns which are pointers to other entities 
//i.e foreigners are homed at a particular home 
class foreign extends column{
    //
    //Tthe name of the referenced table and database names
    public \stdClass /*{ref_table_name, ref_db_name}*/ $ref;
    //
    //contains all the columns of type foreign
    function __construct(string $dbname=null, string $ename= null,
             string $name=null, \stdClass $options=null, \stdClass $ref=null) {
        //
        //bind the parameters
        $this->bind_arg('ref', $ref); 
        //
        //The parent constructor 
        parent::__construct($dbname, $ename, $name, $options);
    }
    
    //
    //The away entity of a foreign key column is the refereced one
    function away(): entity{
        //
        return database::$current[$this->ref->db_name]->entities[$this->ref->table_name]; 
    }
    
    //
    //The home entity of a forein key is its entity
    function home(): entity{
        //
        return database::$current[$this->dbname]->entities[$this->ename]; 
    }
    
    //
    //Get the string version of this object that will aid in searching 
    function __toString() {
        return "$this->dbname.$this->ename.$this->name";
    }
    
    //Returns true if this foreiger qualifies to be alien. It does if there is
    //more than 1 column (of the home entity) that has the same reference table 
    //name
    function is_alien():bool{
        // 
        //Get the home entity
        $entity = database::$current[$this->dbname]->entities[$this->ename];
        //
        //Get the reference entity
        $ref= $this->ref;
        //
        //Collect all the columns of the home entity that have this reference table
        $similar= array_filter($entity->columns,
            fn($col)=> 
                $col instanceof foreign 
                && $col->ref->table_name===$ref->table_name
                && $col->ref->db_name===$ref->db_name
        );
        //
        //If the there is more than one case, then true.
        if(count($similar)===1){return false;}else{return true;}
    }
    
    
    //Returns the string version of this entity expression 
    function to_str(): string {
        return "{$this->ename}.$this->name";
    }
    
    //returns the name
    function get_ename(): string {
         return "{$this->ename}.$this->name";
    }
}

//models the columns in other entities that points to a particular entity The diference 
//between a pointer and a foreign is that the pointers do not have a silmilar home
class pointer extends foreign{
    //
    function __construct(foreign $col) {
         //
        //The parent constructor 
        parent::__construct($col->dbname, $col->ename, $col->name,$col->options,
                $col->ref_table_name,$col->ref_db_name);
        
        $this->home = $col->ref_table_name;
        $this->away= $col->ename;
    }
    
    //Pointers run in the opposite direction to corresponding foreign keys, so 
    //that its away entity is the home version of its foreign key
    function away(): entity{
        //
        //Get the referenced entity aand return it 
        return parent::home(); 
    }
    
    //By definition, pointers run in the opposite direction to corresponding foreign keys, so 
    //that its home entity is the away entity of its foreign key.
    function home(): entity{
        //
        //Get the referenced entity aand return it 
        return parent::away(); 
    } 
    
    //
    //Get the string version of this object that will aid in searching 
    function __toString() {
        return "$this->dbname.$this->ename.$this->name";
    }
}

//Expression for handling syntax and runtime errors in the code execution note that 
//the error class does not have an sql string equivalent 
//$smg is the error message that resulted to this error 
//$suplementary data is any additional information that gives more details about 
//this error.
//Error seems to be an existing class!!
class myerror extends expression{
    //
    //Keeping track of the row counter for error repoerting in a multi-row dataset
    static /*row id*/?int $row=null;
    
    //The supplementary data is used for further interogation of the error 
    //message. 
    public $supplementatry_data;
    //
    //Construction requires a mandatory error message and some optional suplementary 
    //data that aids in deburging
    function __construct(string $msg, $supplementary_data=null){
        $this->msg = $msg;
        $this->supplementary_data = $supplementary_data;
        //
        parent::__construct();
    }
    
    
    //The strimg representtaion of an error
    function __toString():string{
        return "Error. $this->msg";
    }
    
    //An error is always an error.
    function is_error(){return true; }
    
}

//This is the simplest form of an expression it includes simple characters 
//e.g / , .
//The parameters are 
//$value which is the expression to be displayed in the sql must not be null
class literal extends expression{
    //
    //This is the value to be represented as an expression 
    public  /*mixed*/ $value;
    //
    //We require the value inwhich to express as an expression 
    function __construct($value){
        //
        //The value of a literal cannot be an object
        if(is_object($value)){
            throw new \Exception('An object cannot be a a literal value');
        }
        //
        //save the value
        $this->value= $value;
        //
        //The parent expression
        parent::__construct();
    }
    
    //Converying a literal to an sql string
    public function to_str(): string {
        //
        //Erroneous values do not have a string representation. Abort
        if ($this->value instanceof myerror){
            throw new \Exception("An error object {$this->value->msg} does not have an SQL string represention");
        }
        //
        //Empty strings will be outputted as nulls NB only strings can be trimed
        if(is_string($this->value)){
            if (trim($this->value)==='') {return 'null'; }
        }
        //
        //A string version of a literal is basicaly the literal itself as a string 
        //hence it should be enclosed in double quotes 
        return "'$this->value'";
    }
    
    //String representtaon of a litaral
    function __toString() {
        return "$this->value";
    }
    
    //A literal is an error if its value is an error.
    function is_error(){return $this->value instanceof error; }
}

//The log class help to manage logging of save progress data, for training 
//purposes
class log extends \DOMDocument{
    //
    //The file name used for used for streaming
    public $filename;
    //
    //The current log, so that it can be accessed globally
    static log $current;
    //
    //Indicates if logging is needed or not; by default it is needed
    static bool $execute = true;
    //
    //The elememnt stack
    public array $stack=[];
    //
    //The document to log the outputs
    function __construct($filename){
        //
        //Set the file handle
        $this->filename=$filename;
        //
        parent::__construct();
        //
        if (log::$execute){
            //
            //Start the xml document 
            $root = $this->createElement('root');
            $this->appendChild($root);
            //
            //Place the root at the top of the atck
            $this->stack = [$root];
        }
    }
    
    //Returns the element at the top of the stck
    function current(){
        return $this->stack[count($this->stack)-1];
    }
    
    //Output the xml document
    function close(){
        //
        //Close the file handle
        $this->save($this->filename);
    }
    
    //Output the open tag for start of expression save
    function open_save(schema $obj){
        //
        //Output the expresion full name tag
        if (!log::$execute) {return;}
        //
        //Create the element
        $elem = $this->createElement($obj->full_name);
        $this->current()->appendChild($elem);
        //
        //Place it in the stack
        array_push($this->stack, $elem);
        //
        return $elem;
    }
    
    //Creates a tag and appends it to the tag ontop of the stack given a tag name  
    function open_tag(string $tag_name){
        //
        //Only continue if we are in a logging mode 
        if (!log::$execute) {return;}
        //
        //In the logging mode
        //Create the element of the tagname provided 
        $elem = $this->createElement($tag_name);
        //
        //Apeend the element to the one on top of the stack  i.e current;
        $this->current()->appendChild($elem);
        //
        //Place it in the stack
        array_push($this->stack, $elem);
        //
        //return the element creates
        return $elem;
    }
    
    //sets the attributes of an element given the string attribute name, the element 
    //and the value 
    function add_attr(string $attr_name, string $value, $element=null ){
        //
        if (!log::$execute){return;}
        //
        //
        //$Ensure the element we are adding the value is at the top of the stack
        //enquire on how to deal with this situatuation 
        if (!is_null($element) && $this->current()==!$element){
            throw new Exeption ('Your stack is corrupted');
        }else{
            $this->current()->setAttribute($attr_name, $value);
        }
    }
    
    //ClosiNg pops off the given element from the stack
    function close_tag($element=null){
        //
        //If not in log mode
        if (!log::$execute){return;}
        //
        //Use the givebn element for tesing integory
        if (!is_null($element) && $this->current()==!$element){
            throw new Exeption ('Your stack is corrupted');
        }
        array_pop($this->stack);
    }
    
}
