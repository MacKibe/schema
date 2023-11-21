<?php
//
//The PHPMailer Abstract class reference defined through composer
include_once __DIR__. '/vendor/autoload.php';

//Fix the references to the php mailer class
use PHPMailer\PHPMailer\PHPMailer;
//
//Fix the reference to the php mailer's SMTP class
use PHPMailer\PHPMailer\SMTP;
//
//Introduce the Exception handler class
use PHPMailer\PHPMailer\Exception;
//
class mailer extends technology{
    
    //The link to php mailer
    public phpmailer $phpmailer;
    
    function __construct(string $sql){
        //
        parent::__construct($sql);
        //
        //Instantiate the PHPMailer method and passing a value of true allows 
        //for exception handling
        $this->phpmailer = new PHPMailer(true);
        //
        //Set up the system configuration
        $this->server_config();
        //
        //Set up the administration addresses
        $this->set_source_addresses();
    }
    
    //The configuration details to the server that will open a connection to the
    // server to enable us to send emails to our clients
    private function server_config() {
        //
        //Set the SMTP DEBUGGER that allows for verbose(detailed) error messages
        // for better debugging
        $this->phpmailer->SMTPDebug = SMTP::DEBUG_OFF;
        //
        //Instruct the mailer to only send emails using SMTP
        $this->phpmailer->isSMTP();
        //
        //Set the host the SMTP server will use to send the emails through.
        //Gmail usually uses the ('smtp.gmail.com') as the server default.
        $this->phpmailer->Host = 'smtp.gmail.com';
        //
        //Enable SMTP Authentication
        $this->phpmailer->SMTPAuth = true;
        //
        //Set the server username. When using gmail as the HOST server, 
        //this parameter is usually the google email address.
        $this->phpmailer->Username = "mutalldata@gmail.com";
        //
        //Set the server password.
        $this->phpmailer->Password = 'djjqaghqbsypbeqv';
        //
        //Enable TLS encryption for the server
        $this->phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        //
        //Define the server port to use for this connection. Google uses port 
        //587 when SMTP is set to use TLS encryption. 
        //When not using TLS encyrption, the port that is used is 465
        $this->phpmailer->Port = 587;
    }
    //
    //This method allows for the specification of the senders and the recievers 
    //for the email address namely the sender, the reciever, and the CC
    private function set_source_addresses() {
        //
        //Set the email sender. Two paramaters, the email and name to identify 
        //the sender of the email.i.e. ("mutalldata@gmail.com","MUTALL DATA")
        $this->phpmailer->setFrom("mutalldata@gmail.com", "MUTALL DATA");
        //
        //Set the email to reply to. The email and the reply keyword are the two
        // parameters in this section
        $this->phpmailer->addReplyTo("mutalldata@gmail.com", "Reply");
    }
    
    
    //Send the message content to the addressees.
    function send(array $addresses, stdClass $content):void{
        //
        //Clear addresses
        $this->phpmailer->clearAddresses();
        //
        //Add each address to the message queue
        foreach($addresses as $address){
            //
            //Retrieve the contact (either email or phone number)
            $email = $address['address'];
            $receiver = address['username'];
            //
            //Set the content type
            $this->phpmailer->isHTML($content.type==='html');
            //
            $this->phpmailer->Subject = $content->subject;
            //
            //Define line break
            $br = $content.type==='html' ? '<\br>': PHP_EOL;
            //
            //Compile the subject
            $message = $content.type==='html'
                //
                //Assume that the message is complete for HTLM styled bodies    
                ?$content->body
                //
                //Add the sender, subject and date to the mesade body for pplain texts
                :"Sent by: ${$content->sender}"
                    .$br
                    ."On:${$content->date}"
                    .$br
                    .$content->body;
            //
            $this->phpmailer->Body = $message;
            //
            //Add the contact to the email addresses; anticipate illegal addresses
            try {
                $this->phpmailer->addAddress($email, $receiver);
            } catch (\Exception $e) {
                //
                //Compile the errors collected from the messenger and show them
                //to the user.
                array_push($this->errors, $this->phpmailer->ErrorInfo);
            }
        }
        //
        //Send teh email, trapping any error
        try {
            $this->phpmailer->send();
        } catch (Exception $e) {
            array_push($this->errors, $this->phpmailer->ErrorInfo);
        }
    }
    
}
