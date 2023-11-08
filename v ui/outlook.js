//
//Export modules.
export { login, app, page };
//
//
class page {
    //
    constructor(url) {
        //.
        //initiallize the url
        this.url = url;
    }
}
//
//
class login extends page {
    //
    constructor(url, username, email) {
        //
        //
        super(url);
        this.email = email;
        this.username = username;
    }
}
//
//
class app extends page {
    //
    //
    constructor(url, logo, id, name, tagline) {
        super(url);
        //
        this.logo = logo;
        this.id = id;
        this.name = name;
        this.tagline = tagline;
    }
}
//
//
