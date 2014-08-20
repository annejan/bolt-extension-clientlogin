Client Login
============

An extension to remember authenticated visitors on your bolt website. This extension uses 
<a href="http://hybridauth.sourceforge.net" target="_blank">hybridauth</a> for the actual authentication process

Installation
============

  - To enable a provider set the value `enabled: true` in the configuration and
    replace the example provider keys with real values. (see below)


Adding providers
================

Google account
--------------
  - Go to https://code.google.com/apis/console/ and log in, if required
  - Create a new Client ID in the 'APIs & auth' menu option, under 'Credentials'
  - Use the 'Client ID' and 'Client Secret' in your config
  - The 'Redirect URIs' should look like this:
    http://example.org/authenticate/endpoint?hauth.done=Google

Twitter
-------
  - Log in at: https://apps.twitter.com/
  - Create an app.
  - Get the API key and secret from the 'API Keys'-tab.
  - Use the 'Client ID' and 'Client Secret' in your config
  - The "Callback URL" should look like: http://example.org/visitors/endpoint

Facebook
--------
  - Log in on facebook with the facebook account you want to use for the site
  - Then go to https://developers.facebook.com
  - In the "Apps" menu choose "Create a New App"
  - You need to enter a "Display name" and a category. You do not need a namespace and leave the test version switch alone.
  - Click "Create app" and then fill in the Captcha
  - After that your app is created in development mode and you will be redirected to de app dashboard.
  - Go to the settings tab and choose "add platform" and choose "Website"
  - Then enter your url for site url and mobile site url
  - After that add extra subdomains to "App Domains"
  - Enter a valid emailaddress in contact email
  - Save your settings
  - Next go to status & review and set the toggle next to "Do you want to make this app and all its live features available to the general public?" to Yes
  - Then go back to the dashboard and copy the App ID and App secret for your config.yml file.

Multiple urls

  - In https://developers.facebook.com go to your app then settings and then the advanced tab.
  - In security add the url's to the "Valid OAuth redirect URIs"

Github
------
  - Log in at Github, and then go to: https://github.com/settings/applications/new
  - Register a new OAuth application.
  - Get the Client id and secret from the top right corner of the screen.
  - The Authorization callback URL should look like: http://example.org/visitors/endpoint

See <a href="http://hybridauth.sourceforge.net/userguide.html" target="_blank">
the hybrid auth userguide</a> for advanced configuration options and how to get
the needed keys.

An example of the provider keys

```
  providers:
    Google:
      label: "Login with google"
      enabled: true
      keys:
        id: "*** your id here ***"
        secret: "*** your secret here ***"
```

Usage
=====

You can also use the following functions and snippets in your templates:

Login Link(s)
----------

There are two Twig function options for displaying the login links:

```
    {{ displaylogin() }}
```

``` 
    {{ displaylogin(true) }}
```
    
In the first instance, after authentication a user is redirected to the homepage.

By supplying the paramter `true` the user is redirected to the current page.

Logout Link
-----------

As with login, there are two options for the logout links:

```
    {{ displaylogout() }}
```

```
    {{ displaylogout(true) }}
```

In the first instance, after logging out a user is redirected to the homepage.

By supplying the paramter `true` the user is redirected back to the current page.

Dynamic Link
------------

If you want the login/logout to be automatically varied based on whether a user
is logged in or our, you can use:

```
    {{ displayauth() }}
```


Using these values in your own extensions
-----------------------------------------

This extension is pretty bare-bones by design. Most likely, you will use this 
extension in combination with another extension that builds on its functionality. 
To get information about the current visitor, use this:

    $visitor = \Authenticate\Controller::checkvisitor($this->app);

Check if $visitor is `empty()` to see if we have a logged on user, from your code. 
If logged on, you'll get an array with the username, id, avatar and information 
supplied by the provider.


Frontend Access Control
=======================

This extension can also leverage Bolt's frontend role persmission model to control access to contenttypes and their listing pages.

Site's config.yml
-----------------

Turn on frontend permission checking

    frontend_permission_checks: true

Site's permissions.yml
----------------------

First, create a role to be used
    roles:
        [...]
        social:
            description: Social Media login access
            label: Social Media Login

Secondly set up the permissions for the desired contenttype(s) to have the 'frontend' paramter set to the role created above.

    contenttypes:
        mycontenttype:
            edit: [ editor ]
            create: [ editor ]
            change-ownership: [ owner ]
            view: [ anonymous ]
            frontend: [ social ]

Authenticate config.yml
-----------------------

Set the 'role' paramter, e.g.

    role: social
