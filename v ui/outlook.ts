 //
//The following classes will be available to all 
//application being developed by the mutall team 
//e.g realestate, brockerage, chama, e.t.c
export {login, app, page, config};
//
//Models the view that is rendered by a browser.
//This is what the user interacts with in an 
//application e.g login, crud, index, about e.t.c.
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
//This is a page used for authenticating users so 
//that they can be allowed to access the application 
//services.
class login extends page {
    //
    //The email to be authenticated 
    public email: string | undefined;
    //
    //
    public password: string | undefined;
    //
    //
    public provider?: provider;
    // 
    // 

    constructor() {
        //
        //Use the confi file to get the login url
        super(config.login);

    }
}



//
//This class represents authentication service providers
// eg. google,facebook,github
abstract class provider {
    //
    //Every service provider is identified by this name
    //e.g google,facebook.
    public name:string;
    //
    //Initialize the provider using the name. 
    constructor(name:string){
        this.name=name;
    }
    //
    //Allows users to sign in using this provider. 
    //Every provider must supply its own version of 
    //signing in hence abstract.
    abstract login():void;
}

// This class represents the authentication services provided by google.
class google extends provider {


    constructor() {
        

        super('google');
    }
    //
    //This method allows users to signin using their google 
    //account i.e This service is borrowed from firebase
    login(): void{
        
    }
    
}
//
//Represents our custom login provided firebase
class outlook extends provider {
    //
    //
    constructor() {
        super('outlook');
    }
    //
    //This is our custom made signing method. This 
    //process is also implemented using firebase 
    login(): void{
        
    }
    //
    //For custom form of authentication the user needs to have
    //created an account before they can login.Hence this create 
    //enables them to create new accounts.
    create_account(): void {
        
    }
}
// 
// 
class facebook extends provider{
    // 
    // 
    constructor() {
        // 
        // 
        super('facebook');
    }
    //
    //Allows users to signin using their facebook accounts 
    //a service provided by the firebase platform.
    login():void{
        
    }

}
//
//The mechanism of linking services providers 
//to their various consumers.
//This app is the home page of the various mutall
//services also called the index.html of the chama,
//tracker, postek e.t.c 
class app extends page {
    //
    //???????????
    public logo?: string;
    public id: string;
    public name?: string;
    public tagline?: string;
    public user:user|undefined;
    //
    //??????
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
        //Add the login click listener
        this.add_event_listener('#login', this.login);    
    }
    //
    //Authenticate a new user that wants to access the 
    //services of this application.
    async login(){
        //
        //NB A user is only commited in this application 
        //once they have successfully logged in.
        //
        //1.Get the service provider/consumer of intrest
        const User:user = await this.get_user();
        //
        //2.Authenticate the credentials using firebase.
        //Get the result.
        const ok:boolean = await User.is_authentic();
        //
        //If the user is not authentic do not continue 
        //this process
        if(!ok)return;
        //
        //3.Check whether the user is registed with 
        //our system or not.
        const registered:boolean = await User.is_registered();
        //
        //4.If not registered initiate the registration 
        //process. 
        if(!registered) await User.register();
        //
        //commit the user to this app.
        this.user=User;
        //
        //5.Show that the user is welcome in the home page.
        User.welcome();        
    }
    //
    //Return the service provider/consumer 
    get_user():Promise<user>{
      //
      return new Promise(async (resolve,reject)=>{
            //
            //1.Create the login page
            const Login= new login();
            //
            //2.Use the page to collect the user credentials
            //i.e username and password.
            const {email,password}=await Login.get_credentials();
            //
            //3.Use the credentials to create a new user instance.
            const User= new user(email,password);
            //
            //4.Use the newly created user to resolve the promise made 
            resolve(User);
      });  
    }
}
//
//Represents a person/individual that is providing
//or consuming a services we are developing. 
class user{
    //
    //Declare the name password and email
    public email:string;
    public password:string; 
    // 
    //usename is defined on user.welcome hence the undefined
    public username:string|undefined;
    
    //
    //The minimum requirement for authentication is a username and 
    //password
    constructor(email:string,password:string){
        //
        this.email = email;
        this.password = password;
    }
    //
    //Use the firebase library to authenticate this user
    is_authentic():Promise<boolean>{
        //
        return new Promise((resolve,reject)=>{
            //
            //Assuming that the firebase project already exists and the 
            //libraries included
            //1.Initialize the firebase library.
            
            //2.Setup the provider/signin option
            //
            //3.Let the providers to handle the user 
            //
            //4.wait for the user to login 
            //
        })
    }
}
//
//This class is designed to constomise the operating 
//environmennt for the various applications e.g the 
//running versions, file directories, passwords, e.t.c 
//i.e the environmental settings that change frequently
// based on the application.  
class config{
    //
    public static login:string = "../../schema/v11/login.php";
}
