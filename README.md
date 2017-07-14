# SMS Email Relay Overview
An SMS &lt;-> Email relay using [Bandwidth](https://www.bandwidth.com/) messaging services and [Mailgun](https://www.mailgun.com/).


----------
**Table of Contents**

- [SMS Email Relay Overview](#sms-email-relay-overview)
- [Background](#background)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
  * [Instructions for Mailgun](#instructions-for-mailgun)
    + [Add a new subdomain in Mailgun](#add-a-new-subdomain-in-mailgun)
    + [Validate the domain](#validate-the-domain)
    + [Setup a Route](#setup-a-route)
  * [Instructions for Bandwidth](#instructions-for-bandwidth)
    + [Allocate a phone number](#allocate-a-phone-number)
    + [Create an Application](#create-an-application)
    + [Add Phone Numbers](#add-phone-numbers)
  * [Code deployment](#code-deployment)
- [Usage](#usage)
  * [Setup](#setup)
  * [Send an SMS via email (**email -\> SMS**)](#send-an-sms-via-email----email-----sms---)
  * [Receive email via SMS (**SMS-\>email**)](#receive-email-via-sms----sms---email---)
- [Known Limitations](#known-limitations)

----------


# Background
Sometimes we find ourselves in a business environment where email is still the primary form of communication. So how do you close the gap with peers that want to communicate via SMS?
*Answer:* An SMS - Email Relay!

This PHP app leverages Bandwidth's Messaging API and Mailgun's Email API to deliver a feature-rich gateway service.
# Features
 1. 2-way communications between an email address and phone number (1:1 mapping)
 2. Supports SMS
 3. Supports MMS (i.e. picture messaging)
 4. Works with Bandwidth Messaging (USA and Canada)

# Requirements
You will need the following to successfully create your SMS / Email relay service:

 1. A developer account with [Bandwidth](https:://www.bandwidth.com)
 2. A developer account with [Mailgun](https://www.mailgun.com)
 3. A DNS domain (or sub-domain) that you control and can setup custom records for domain verification
 4. A web server with PHP 5.6 or higher
# Installation
Special note: Your web server must be reachable from the public Internet so Bandwidth and Mailgun can signal to you when an inbound event occurs.
## Instructions for Mailgun
You will need to delegate MX records for a domain that will act as your email relay. Since you likely want to use a domain for normal business email through your existing provider, the best course of action is to create a sub domain. We'll use `sms.mydomain.com` for this example.

Mailgun offers a great overview of how to get started. For the impatient, you will need to:

 - Configure a new domain
 - Validate the domain
 - Setup a route so that Mailgun knows how to tell your app a new email has arrived
 - Make note of your API keys. You'll need them for a later step.
### Add a new subdomain in Mailgun
In this example, we are adding `sms.mydomain.com`
![domain setup](https://www.dropbox.com/s/b1m7ky29oxpl8r8/mailgun-newdomain.png?dl=1)
### Validate the domain
Mailgun will give you comphrensive instructions on how to validate your domain. You must complete this step if you want a fully-functional SMS/Email relay.
![domain validation](https://www.dropbox.com/s/sn8zqws9wlvljam/mailgun-domainsetp.png?dl=1)
### Setup a Route
This is the last step needed with completing your Mailgun account. We are asking Mailgun to hold our email and notify our App for each new email. Be sure to:

 - Create a "Catch All" route
 - Select "Store and Notify" and add a URL where Mailgun can reach your code (see [Code Deployment](#code-deployment)). You'll want a URL that points to the `email_to_sms.php` code
 - Add a description (optional)
![mail route](https://www.dropbox.com/s/5ixmitjh8ggvw5n/mailgin-route.png?dl=1)
## Instructions for Bandwidth
The summary steps are:

 - Create an account (if you do not already have one). Direct link is: [https://catapult.inetwork.com/portal/signup](https://catapult.inetwork.com/portal/signup)
 - Allocate a phone number (or multiple phone numbers)
 - Create an Application
 - Add phone numbers to the Application
 - Make note of your API keys for a later step
### Allocate a phone number
You will need at least one phone number.
![Grab a phone number](https://www.dropbox.com/s/f8udovw9y89ke0m/bandwidth-number.png?dl=1)
### Create an Application
Give your App a name. Be sure to enter a URL that is reachable from the Internet. You'll want a URL that points to the `sms_to_email.php` code. See [Code Deployment](#code-deployment) for more details. In this case, we are using https://mydomain.com/smsEmailRelay/sms_to_email.php

![Create an Application](https://www.dropbox.com/s/5tacbvbyqpzliv9/bandwidth-appcreate.png?dl=1)
### Add Phone Numbers
The last step is to associate a phone number with your application. You will need at least one phone number, but feel free to add as many as you need.
![Add Phone Numbers To App](https://www.dropbox.com/s/c2rnz9ck7pwaevu/bandwidth-appaddnumber.png?dl=1)

![Select Phone Numbers to be added](https://www.dropbox.com/s/6ftonld2fbh8tvm/bandwidth-appselectnumber.png?dl=1)
## Code deployment
Now that you've got Bandwidth and Mailgun configured, it's time to get the code installed. On your web server, create a directory at the document root for the sms/email relay service. We'll assume you are on a Unix-based system here:
```
bash$ mkdir smsEmailRelay
bash$ cd smsEmailRelay
bash$ git clone https://github.com/inetBrad/SMS-Email-Relay.git
bash$ cp SMS-Email-Relay/* .
bash$ composer install
```
Create `credentials.php` or edit the `define` statements in `email_to_sms.php` and `sms_to_email.php`. The contents of your `credentials.php` file should look like:

    <?php
    define('LOCALSERVER',          'http://localhost/') ;
    
    // Add Bandwidth credentials here
    define('BANDWIDTH_API_TOKEN',	't-tttttttttttttttttttttttttt');
    define('BANDWIDTH_API_SECRET', 	'xxxxxxxxxxxxxxxxxxxxxxxxxxxx');
    define('BANDWIDTH_USER_ID', 	'u-uuuuuuuuuuuuuuuuuuuuuuuuuu');
    define('BANDWIDTH_API_URL',     "https://api.catapult.inetwork.com/v1/users/" . BANDWIDTH_USER_ID . "/");
    //Optional URL if you want callbacks from Bandwidth
    define('BANDWIDTH_API_CALLBACK', LOCALSERVER . "mycallbacks.php");
    
    // Add mailgun credentials here
    define('MAILGUN_KEY', 			'key-kkkkkkkkkkkkkkkkkkkkkkkk');
    define('DOMAIN',				'sms.mydomain.com');
    ?>
The Bandwidth API Token, Bandwidth API Secret, and Mailgun Key are all available from the respective developer portals.
# Usage
Assuming you have followed the Quick Start guide for this app, you can immediately begin relaying SMS via email.
## Setup 
In the directory where you installed the PHP app, create a .CSV (comma delimited file) called `contacts.csv`. This file controls your mapping between email addresses and phone numbers. This file must contain:
e164 formated phone number, email address. Example:
```
+15554441234,bossman@mydomain.com
+15554441235,sales@mydomain.com
```
Any phone number appearing in your .CSV list **MUST** already be allocated in your Bandwidth developer account.
## Send an SMS via email (**email -\> SMS**)
Create an email with the following parameters:

    From: < Valid email listed in your contacts.csv \>
    To:  < Destination US or Canadian phone number \>
    Subject: < Destination US or Canadian phone number \>
    Body of email: < text of SMS \>

For example, your Gmail window might look like this:
![example gmail screenshot](https://www.dropbox.com/s/r2r84rzr01l34ku/screenshot-mail.google.com-2017-07-12-19-18-10.png?dl=1)

If you use Outlook, your compose window might look like this:
![example Outlook screenshot](https://www.dropbox.com/s/6d9zqfrxoji6yen/screenshot-outlook.png?dl=1)

Easy, right?
## Receive email via SMS (**SMS-\>email**)
No special actions are needed. Simply send an SMS to a number that you've allocated and configured in your [Bandwidth](https://bandwidth.com) account. Make sure the number has also been mapped to an email address in your `contacts.csv` file.
A sender's cell phone might look like:
![Mobile Phone](https://www.dropbox.com/s/iuqaq4rdcjp7mlo/screenshot-mobile.png?dl=1)

# Known Limitations

 - **This code has limited error handling.** If something goes wrong, check your web server's error logs. At a minimum, the code will throw an error if it is unable to find a valid email/phone number combination.
 - **The maximum SMS message length is 1000 characters.** Technically speaking, a single SMS is 160 Unicode characters. However, messages longer than 160 characters are automatically broken into a multi-part
   SMS and re-assembled by the far-end.
 - **The cumulative maximum size of attachments is 5MB.** Not all carriers can accept MMS with media that large, so be safe and keep your attachments below 1MB.
 - **Only PNG, JPG, and GIF filetypes are supported (email-\>SMS).** While Bandwidth accepts a much larger set of MIME types, this code was designed to handle the most common uses cases that would fit the 5MB size constraints without any client-side compression. There are no limitations for attachment types coming from SMS -\> email.

*Tested with Apache 2.4 and PHP 5.6.30*