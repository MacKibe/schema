
//Export modules.

export {login, app, page};
//
//Global variable for accessing the current app.
var current_app:app;
abstract class page {
    //
    //The adress  of the page 
    public url?: string;
    //
    constructor(url?: string) {
        //.
        //initiallize the url
        this.url = url;
    }
    //
    //Add events for the page based on the id and the event callback method.
    //Assume its a button....
    add_event_listener(id:string, callback:CallableFunction):void{
        //
        //Add the login event listener.
        const button:HTMLButtonElement|null = window.document.querySelector(id) ;
        //
        //Check for null
        if(button === null)throw new Error(`Button identified by ${id} button not found`);
        //
        //Add the click event listner
        button.addEventListener('click', () => callback());
    }
}

//
//
class login extends page {
    //
    //The email to be authenticated 
    public email: string;
    //
    //
    public username: string;
    //
    constructor(url: string, username: string, email: string) {
        //
        //
        super(url)
        this.email = email;
        this.username = username;
    }

}
//
//
class app extends page {
    //
    //
    public logo?: string;
    public id: string;
    public name?: string;
    public tagline?: string;
    public user:user|undefined;
    //
    //
    constructor(id:string , url?:string, logo?: string, name?: string,
        tagline?: string) {
        super(url);
        //
        //
        this.logo = logo;
        this.id = id;
        this.name = name;
        this.tagline = tagline;
        //
        //Save a copy of this application that`s 
        //accessible globally.
        current_app = this;
        //
        //Add the login click listener
        this.add_event_listener('#login', this.login);   
         
    }
    //
    //Login a user:Get username, authenticate, check register
    async login():void{
        //
        //1.Get the user credentials.
        this.user = await this.get_user();
        //
        //2.Authenticate the credentials using firebase.
        //Get the result.
        const ok = await this.user.is_authentic();
        //
        //Test ok....
        if(!ok)return;
        //
        //3.Check wherether the user is registed with our system or not.
        //Get roles
        const role = await this.get_user_roles();
        //
        //4.Otherwise request the role the user wishes to play in this application 
        //and welcome to home page. 
        if(role.length === 0) await this.user.register();
        //
        //5.if registed welcome the user to the home page.
        this.welcome();
        
    }
    //
    //Returns a username and a password as the user.

}

//
//Module a config: holds directory.
class config{
    //
    public static index:string = "../../schema/v11/login.php";
}
//
//Module a user component: 
class user{
    //
    //Declare the name password and email
    public username:string;
    public email:string;
    public password:string;
    //
    //init
    constructor(username:string, password:string, email:string){
        //
        //Set the properties.
        this.username = username;
        this.email = email;
        this.password = password;
    }
    //
    //
}
