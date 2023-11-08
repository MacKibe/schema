//
//Resolve references to the io
import * as io from "./io.js";
//
//Resolve mutall_error class
import * as schema from "./schema.js";
//
//To resolve the server methods
import * as server from "./server.js";
//Import the base tree library
import * as tree from "./tree.js";
//The entity-attribute-relation data model organized as tree
//
//Modelling content that represent databases
class dbase extends tree.common.content {
    dbase;
    //
    //Use a schema database to construct a database content
    constructor(dbase) {
        //
        //The name used as a tag in a tree view as the same as that of the database
        const name = dbase.name;
        //
        //The properties of this content are none
        const properties = {};
        //
        //The parent of this node is node. Root nodes have no parent
        super(name, properties);
        this.dbase = dbase;
    }
    //Returns the cildren of a database as tables
    async get_children_content() {
        //
        //Get the tables of this dbase as an array
        return Object.values(this.dbase.entities)
            //
            //Map the databes entities to content equivalents
            .map(entity => new table(entity, this));
    }
}
//
//Modelling content that represents a table. Its parent is a dbase and its 
//children are records. The number depends on whether the table is hierarchical
//or not
class table extends tree.common.content {
    entity;
    parent;
    //
    constructor(entity, parent) {
        //
        //Initialize the inherited class
        super(
        //The tagname to use for table is the same as its name 
        entity.name, 
        //
        //There are no know properties of a table
        {}, 
        //
        //The parent of a table is its source dbane
        parent);
        this.entity = entity;
        this.parent = parent;
    }
    //The children of a table are records. the number depens on whether the
    //underlying entity is hiarchical or not
    async get_chilren() {
    }
}
//
//Content from this EAR namespace extends the common tree version
class content extends tree.common.content {
    entity;
    //
    //The metadata resulting from a record description 
    //The original sql used for formulating the query for isolating this record's children
    sql;
    //
    //The column names
    col_names;
    //    
    //The EAR content constructor
    constructor(
    //
    //Entity is the bit that ear.content extends the common.content 
    entity, 
    //
    //The rest of the properties are required by the common.content
    //
    //The tagname for this content
    name, 
    //
    //The priperties od this content
    properties, 
    // 
    //The precise requirements of a parent in EAR namespace 
    parent) {
        //
        //Initialization requrements for common content
        super(name, properties, parent);
        this.entity = entity;
    }
    //The where clause for selecting children of this content. By default, there
    //is node
    get_children_condition() { return ""; }
    //Use the editor query to return the children of this root node. They are
    //all teh record in the entity 
    async get_children_content() {
        //
        //Get the editor description.
        const metadata = await server.exec(
        //
        //The editor class is an sql object that was originaly designed 
        //to return rich content for driving the crud page.
        "editor", 
        //
        //Constructor args of an editor class are ename and dbname 
        //packed into a subject array in that order.
        [this.entity.name, this.entity.dbase.name], 
        //
        //The method called to retrieve editor metadata on the editor class.
        "describe", 
        //
        //There are no describe method parameters
        []);
        //
        //Destructure the metadata
        //const [idbase, col_names, sql_original, max_record] = metadata;
        this.sql = metadata[2];
        this.col_names = metadata[1];
        //
        //Formulate the (local) sql for selecting children of the primary key 
        const local_sql = `select entry.* from (${this.sql}) as entry ${this.get_children_condition()}`;
        //
        //Execute the sql to get Ifuel,
        const ifuel = await server.exec(
        //
        //Use the database class to query
        "database", 
        //
        //Get the dbname, as the only database constructor argument
        [this.entity.dbase.name], 
        //
        //The method to execute
        "get_sql_data", 
        //
        //The sql argument of the method
        [local_sql]);
        //
        //Convert Ifuel to children of the record type. Their parent is this content
        return ifuel.map(fuel => new record(fuel, this.entity, this));
    }
    //By default, we cannot create content for nodes in this namespace
    create_null_content() {
        //
        //Get the name used of this objects's constructurtor
        const name = this.constructor.name;
        //
        throw new schema.mutall_error(`Content for '${name} cannot be constructed`);
    }
    //
    //In general, content cannot be deleted
    delete() {
        throw new schema.mutall_error(`Content for '${this.constructor.name} cannot be deleted`);
    }
    //In general, content cannot be saved
    save(tr) {
        throw new schema.mutall_error(`Content for '${this.constructor.name}' cannot be saved`);
    }
    //By default, no property of EAR content is shown in the tree view as 
    //atttributes. The user my choose to override this behaviour on a case by
    //case basis
    get_tree_view_attributes() {
        return {};
    }
}
//Record as a content with specific fuel. 
class record extends content {
    fuel;
    parent;
    //
    //A globally accessible cipy of mutall_users database. It is set when the
    //database is initially created from scratch. This allows re-use of the 
    //database between many record instances
    static mutall_users;
    //
    constructor(
    //
    fuel, 
    //
    entity, 
    //
    //Ensure that the parent of a record cannot be null, buy making it public
    parent) {
        //The properties of a record is the same as its fuel
        const properties = fuel;
        //
        //The tagname of a record is the friendly component of the primary key
        const tagname = record.get_primarykey(fuel, entity)[1];
        //
        super(entity, tagname, properties, parent);
        this.fuel = fuel;
        this.parent = parent;
    }
    //Returns the colum names needed to head the childrem in the list
    //view. There is only one: the (child) column's value 
    get_header_names() {
        return ['value'];
    }
    //Create the io for the named column of this record. The name may be
    //'tagname', or a reference to a to a column of the underlying entity
    create_io(cname, anchor) {
        //
        //Tagname is a readonly column not associated with a database
        if (cname === 'tagname')
            return new io.readonly(anchor);
        //
        //A schema column has a predefined io
        const col = this.entity.columns[cname];
        if (col !== undefined)
            return io.io.create_io(anchor, col);
        //
        //Any other name should be flagged as unkonwn
        throw new schema.mutall_error(`Unable to determine the io of column ${cname} `);
    }
    //Returns he primary key column as a tuple of 2 values: the primary key
    //number and the friendly componnet
    static get_primarykey(fuel, entity) {
        //
        //Get teh entity name
        const ename = entity.name;
        //
        //Retrieve the primary key value as a json string
        const jsonstr = String(fuel[ename]);
        //
        //Parse the json string to the 2-member table. This will throw an exception
        //if the string is not valid json
        const tuple = JSON.parse(jsonstr);
        //
        //return the tuple
        return tuple;
    }
    //Creating the null content of a record. Assume that the axis is 
    //'sibbling of'
    create_null_content(axis = 'sibling') {
        //
        //
        //Only siblings for a record can be created for this version
        if (axis !== 'sibling')
            throw new schema.mutall_error('Only siblings of a ear.record can be constructed');
        //
        //Since this is a sibbling beging created, its entity must be the same 
        //as that of this record
        const entity = this.entity;
        //
        //The fuel for the  new record is a copy of tis record's fuel with 
        //all values set to null
        const fuel = {};
        for (const key in this.fuel) {
            fuel[key] = null;
        }
        ;
        //
        //The parent of this record is that same as that of the new record. 
        const parent = this.parent;
        //
        //The parent cannot be null based on the axis assumption
        if (parent === undefined)
            throw new schema.mutall_error('The parent of this content must be defined');
        //
        return new record(fuel, entity, parent);
    }
    //
    //The primary key can be obtained from the properties
    get pk() {
        //
        //Get the entity name
        const ename = this.entity.name;
        //
        //Get the primary key column, as a json array string
        const json = String(this.properties[ename]);
        //
        //Convert the json string to a tuple of 2 parts: the primary key value and its
        //friendly component
        const primarykey = JSON.parse(json);
        //
        //Set the primary key value; its the first component
        return primarykey[0];
    }
    //Get the children of a record. They are columns derived  from properties and 
    //pointers of the current entity
    async get_children_content() {
        //
        //Use the properties of this record to collect the column-based children. 
        const columns = this.collect_columns();
        //
        //Open the mutall_users database. It needs to be part of the search space
        //for pointers
        const mutall_users = await record.get_mutall_users();
        //
        //Use the current entity to collect the pointer-based children of this record. 
        //Be careful about potential recursion?????
        const pointers = this.collect_eas_pointers(mutall_users);
        //
        //Return both column- and pointer-based children of this record 
        return [...columns, ...pointers];
    }
    //
    //Get the mutall users database. The method is sttaic because it may be
    //required by more than one table
    static async get_mutall_users() {
        //
        //Rteurn the mutall users database if it is valid
        if (record.mutall_users !== undefined)
            return record.mutall_users;
        //
        //Construct the database from scratch 
        //
        //Get the database structure
        const idbase = await server.exec('database', ['mutall_users'], 'export_structure', []);
        //
        //Use the structure to create (and set) the database
        record.mutall_users = new schema.database(idbase);
        //
        //return the new database
        return record.mutall_users;
    }
    //
    //
    //Use the properties of this record to collect the column-based children. 
    *collect_columns() {
        //
        //Loop through all the properties of this record. The key of a property
        //corresponds to the name of a column
        for (const cname in this.properties) {
            //
            //The column named tagname is not drived from a schema.column, so
            //it cannot create an ear.column. Ignore it
            if (cname === 'tagname')
                continue;
            //
            //Get the column that matches the name
            const col = this.entity.columns[cname];
            //
            //Verify that the column is valid
            if (col === undefined)
                throw new schema.mutall_error(`Column '${cname}' is not defined in entity ${this.entity.name}`);
            //
            //Ddiscard primary keys
            if (col instanceof schema.primary)
                continue;
            //
            //Exclude foreign keys that point to the parent of this content
            if (this.discard_foreigner(col))
                continue;
            //
            //Compileand yield a column-base child of the current record. The 
            //parent of the column is this record
            yield new column(col, this);
        }
    }
    ;
    //Determie if a foreing key column is to be discarded from the children
    //of this record or not. It should be if  points to an ancestor that is 
    //a record refereced by the column
    discard_foreigner(col) {
        //
        //The column should not be discarded if it is a foreign key...
        return col instanceof schema.foreign
            //
            //...and this record has a parent...
            && this.parent !== undefined
            //
            //...that has another parent of type record...
            && this.parent.parent instanceof record
            //
            //..that is referenecd by the foreign key column.
            && this.parent.parent.entity.name === col.ref.table_name
            && this.parent.parent.entity.dbase.name === col.ref.db_name;
    }
    //
    //Use the current entity to collect the pointer-based children od this record. 
    //Be careful about the possibility of recursion. To avoid recurssion, build the
    //pointers in 2 phases: phase 1 for structural columns and phase 2 for cross
    //mmebers 
    *collect_eas_pointers(mutall_users) {
        //
        //Get both the structral and cross member pointers of the current entity (as
        //foreign key references)
        const schema_pointers = this.collect_schema_pointers(mutall_users);
        //
        //Loop through the schema pointers to convert them to the EAR versions
        for (const schema_pointer of schema_pointers) {
            //
            //Convert the schema pointer to ear version
            const ear_pointer = new pointer(schema_pointer, this);
            //
            yield ear_pointer;
        }
    }
    //Returns the pointers of this record. These are foreign keys that references
    //the entity of this record.
    *collect_schema_pointers(mutall_users) {
        //
        //Loop through all the databases needed for searching pointers
        for (const dbase of [this.entity.dbase, mutall_users]) {
            //
            //Loop through all the entity (names) of the database
            for (const ename in dbase.entities) {
                //
                //Loop through all the columns of entity
                for (const cname in dbase.entities[ename].columns) {
                    //
                    //Get the named column
                    const col = dbase.entities[ename].columns[cname];
                    //
                    //Only foreign keys are considerd
                    if (!(col instanceof schema.foreign))
                        continue;
                    //
                    //The column's reference must match this record
                    if (col.ref.db_name !== this.entity.dbase.name)
                        continue;
                    if (col.ref.table_name !== this.entity.name)
                        continue;
                    //
                    yield col;
                }
            }
        }
    }
    //By comparing the original and the values in the tr, write changes to the storage 
    //system
    async save(tr) {
        //
        //Collect all the label layouts and use questionnaire to save the data
        const layouts = Array.from(this.collect_labels(tr));
        //
        //Write the data to the database
        const result = await server.exec(
        //
        //Use the php questionnaire class
        "questionnaire", 
        //
        //The only constructor argument is the layouts
        [layouts], 
        //
        //Use the load method that returns a more structured result that
        //can be interrogated further
        'load_user_inputs', 
        //
        //Load common needs no arguments
        []);
        //
        //Report the Imala resukt
        return this.report_imala(result, tr);
    }
    //
    //This method makes the error button visible and puts the error in its 
    //(the button's) span tag which allows the user to view the Imala report.
    //It also updates the primary key field with a "friend", when it is not 
    //erroneous
    report_imala(mala, tr) {
        //
        //If there are syntax errors, report them; there cannot be other
        //types of errors, so, abort the process after the report.
        if (mala.class_name === "syntax") {
            //
            //Convert the errors to a string.
            const errors = mala.errors.join("\n");
            //
            const error = new schema.mutall_error(`${mala.errors.length} syntax errors:\n ${errors}`);
            //
            //Abort the reporting, as there cannot be other types of errors, and return the error.
            return error;
        }
        //At this time we must have a runtime result. It is a sign of a problem if we don't 
        if (mala.class_name !== "runtime")
            throw new schema.mutall_error(`A runtime result was expected`);
        //
        //We expect, when we call this method, to be loading one (tr) row, so 
        //there will be only one indexed entry in the Imala result. Ensure
        //this assumption is held
        if (mala.result.length !== 1)
            throw new schema.mutall_error(`Only one runtime entry is expected. ${mala.result.length} found`);
        //
        //Get the only runtime entry
        const entry = mala.result[0].entry;
        //
        //If the the entry points to an error, return the message
        if (entry.error)
            return new Error(entry.msg);
        //
        //At this point, the saving must have been successful. We need to 
        //update the primary key and friendly attributes of the list view, 
        //as well as the node of the tree view 
        //
        //Update the primary io in the list view
        //
        //Formulate the primary value and friend as a json string comprising
        //of a tuple with the 2 elements
        const json = JSON.stringify([entry.pk, entry.friend]);
        //
        //Get the primary key io
        const Io = this.get_pk_io(tr);
        //
        //Set its value
        Io.value = json;
        //
        //Update the matching io in the tree view
        //
        return 'ok';
    }
    //Returns the io corresponding to the primary key value from the given tr
    get_pk_io(tr) {
        //
        //Get all the tds of the tr as an array
        const tds = Array.from(tr.cells);
        //
        //Find the td that has an io of the primary key class
        const td = tds.find((td) => io.io.get_io(td) instanceof io.primary);
        //
        //It is an error if the primary key cannot be found
        if (td === undefined)
            throw new schema.mutall_error(`Unable to find a primary io in this tr '${tr}'`);
        //
        //Get the io that matches the td
        return io.io.get_io(td);
    }
    //Collect the data to be written to the database as label layouts
    *collect_labels(tr) {
        //
        //Loop through all the cells of the given tr and yield its label 
        //if valid. In future, an io should yield a label directly
        for (const td of Array.from(tr.cells)) {
            //
            //Get the io that matches the td
            const Io = io.io.get_io(td);
            //
            //If the io is not asociated with a fatabase  column, then you
            //cannot save it. Ignore it and continue
            if (Io.col === undefined)
                continue;
            //
            //Get the database, table and column names
            const dbname = Io.col.entity.dbase.name;
            const ename = Io.col.entity.name;
            const cname = Io.col.name;
            //
            //Get the value to save
            const value_new = Io.value;
            //
            //Get the original value
            const value_old = this.fuel[cname];
            //
            //Yield the value, if it is valid for saving
            if (this.value_is_valid(Io.col, value_old, value_new)) {
                //
                //Use the tr's row index as a label
                yield [dbname, ename, [tr.rowIndex], cname, value_new];
            }
        }
    }
    //Decide if a value is fit for saving or not
    value_is_valid(col, value_old, value_new) {
        //
        //A primary key is valid for saving if it is not null
        if (col.name === col.entity.name && value_old !== null)
            return true;
        //
        //Any value is valid for update if it has changed
        if (value_old !== value_new)
            return true;
        //
        //By default, a value is not valid for saving
        return false;
    }
}
//The columm of a record as a child content. The parent is a record
class column extends content {
    col;
    //
    constructor(col, parent) {
        //
        //The entity of the ear.column is the same as that if the given
        //schema.column 
        const entity = col.entity;
        //
        //The tagname is the same as the column's name
        const tagname = col.name;
        //
        //The only column's property is its value
        //
        //use the parent to get the column's value
        const value = parent.fuel[col.name];
        //
        //Compile the only property
        const properties = { value: value };
        //
        //Initialize the content
        super(entity, tagname, properties, parent);
        this.col = col;
    }
    //Returns the header colum names. They should be as many as the keys
    //of this columns properties
    get_header_names() {
        return Object.keys(this.properties);
    }
    //Create the io for the named column this ccontent. There are 2 possible
    //types of columns: the tagname, and this columns value. 
    create_io(cname, anchor) {
        //
        switch (cname) {
            //
            //Tagname is always read only
            case 'tagname': return new io.readonly(anchor);
            //
            //The value column should be associated with the io.type for
            //for this column
            case 'value':
                //
                //Return the io associated with the current schema.column
                return io.io.create_io(anchor, this.col);
            default:
                //Any other name is likely to be an error
                throw new schema.mutall_error(`Unable to determine the io of column ${cname} `);
        }
    }
    //Column is a leaf (not branch)
    is_branch() {
        return false;
    }
    //Leaves do not have children; its illegal to try to access their children as it
    //indicates a logic failure somewhere.
    async get_children_content() {
        throw new schema.mutall_error('A column-based content has no children as it is a leaf node');
    }
    //By default, no property of EAR content is shown in the tree view as 
    //atttributes. Here we override this behaviour to display all the (user 
    //editable) attributes of a column 
    get_tree_view_attributes() {
        return this.properties;
    }
    //Override how attributes of a column (which is only one -- is value) is
    //displayed. In general, all tree attributes attached to the given selector
    //as key/value pairs. For a column the property name 'value' is dropped
    add_tree_attributes(node, properties, selector) {
        //
        //Create the option element as a child of the selector
        const option = node.create_element('div', selector, { className: 'option' });
        //
        //Add the key/value separator span tag
        node.create_element('span', option, { className: 'sep', textContent: ': ' });
        //
        //Convert the only column property value to a string
        let str = String(Object.values(properties)[0]);
        //
        //Truncate the string to 15 characters if necessary
        const max = 20;
        str = str.length > max ? str.substring(0, max - 1) + '…' : str;
        //
        //Add the value span tag to the option. 
        node.create_element('span', option, { className: 'value', textContent: str });
    }
}
//A pointer is content associated with a foreign key which references the parent
//record of the pointer
class pointer extends content {
    pointer;
    parent;
    //
    constructor(
    //
    //The foreign key that defines this pointer
    pointer, 
    //
    //The parent of a pointer is a record
    parent) {
        //The entity of a pointer is derived from the foreign key
        const entity = pointer.entity;
        //
        //A pointer has no known properties .
        const properties = {};
        //
        //The tagname is teh same as the entities name
        const tagname = entity.name;
        //
        //Initialize the base content
        super(entity, tagname, properties, parent);
        this.pointer = pointer;
        this.parent = parent;
    }
    //Returns the header colum names. They are set when the children are read
    //from the server
    get_header_names() {
        //
        //The column names must be set; otherwise thre is a problem
        if (this.col_names === undefined)
            throw new schema.mutall_error(`Header column names are not yet set`);
        return this.col_names;
    }
    //Create the io for the named column of this pointer. The only name that is
    //expected is tagname. All other nams must as a result of displaying
    //columns and pointers in the same table as records. Ignore them by making
    //them read only. This issue will be better resolved when the list view is
    //laid out not just in a tabular fashion
    create_io(cname, anchor) {
        //
        return new io.readonly(anchor);
    }
    //The where clause for selecting all records from the database that
    // point to the parent
    get_children_condition() {
        //
        //Get the field name to search in the pointer's home
        const fname = this.pointer.name;
        //
        //get the expression for extraction the exact primary key value from the
        //foreign key whose value as a json string. It is the first component of the
        //foreign key
        const exp = `${fname}->>"$[0]"`;
        //
        //All the children must point to the primary key of the parent 
        const pk = this.parent.pk;
        //
        //Formulate the complete where clause (note the quotes enclosing the primary ey value)
        return `where  ${exp} = '${pk}'`;
    }
}
