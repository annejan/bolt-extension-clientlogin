Client Login
============

An extension to remember authenticated visitors on your Bolt website. This extension uses 
<a href="http://hybridauth.sourceforge.net" target="_blank">HybridAuth</a> for the actual authentication process

Installation
============

To enable a provider set the value `enabled: true` in the configuration and 
replace the example provider keys with real values. (see below)


Adding Providers
================

Google
------
1. Login to https://console.developers.google.com/project
1. Click 'Create Project' (go to next step if using an existing one)
  1. Set a descriptive Project Name
  1. Agree to terms and services
  1. Click Create
1. Expand 'APIs & auth' menu and select 'Credentials'
  1. Under 'OAuth' click 'Create new Client ID'
  1. Click 'Configure consent screen'
  1. Set your desired email address from the selector
  1. Set a unique 'Product Name'
  1. Click 'Save'
1. In the 'Create Client ID' dialogue
  1. Set **Application Type**: Web Application
  1. Set **Authorized JavaScript Origins**: http://your-bolt-site.example.com  
    *(change the domain name to match yours)*
  1. Set **Authorized Redirect URI**: http://your-bolt-site.example.com/authenticate/endpoint?hauth.done=Google  
    *(change the domain name to match yours)*
  1. Click 'Create Client ID'
1. Under the 'APIs & auth' menu select 'APIs'
  1. Click the 'Google+ API' link (middle of the page at time of writing)
  1. Click the 'Enable API' button
1. Add the 'Client ID' and 'Client Secret' to your config.yml

**NOTE** It may take as long as 10 minutes for the changes to propagate and the client ID and secret to work

Twitter
-------
1. Login to: https://apps.twitter.com/
1. Click 'Create New App' and set:
  - Name
  - Description
  - Website: http://your-bolt-site.example.com/  
  *(change the domain name to match yours)*
  - Callback URL: http://your-bolt-site.example.com/authenticate/endpoint?hauth.done=Twitter  
  *(change the domain name to match yours)*
1. Click 'Create your Twitter application'
1. Select the 'Settings' tab
  - Check 'Allow this application to be used to Sign in with Twitter'
  - Click 'Update settings' button at the bottom of the page
1. Select the 'API Keys' tab
1. Add the 'Client ID' and 'Client Secret' in your config.yml

Facebook
--------
1. Login to Facebook with the account you want to use for the site
1. Go to https://developers.facebook.com
1. Under the 'Apps' menu select 'Create a New App'
1. In the 'Create a new app' dialogue set:
  - **'Display Name'** set to something descriptive
  - **'Namespace'** just leave blank
  - **'Is this a test version of another app?'** leave as 'No'
  - **'Category'** just select a category that fits your site content
1. Click 'Create App' and enter displayed CAPTCHA
  - At this point your app is created in development mode and you will be redirected to the App Dashboard.
1. In the left menu, select 'Settings'
  1. In the 'App Domains' field enter your sites domain name:
    - your-bolt-site.example.com  
    *(change the domain name to match yours)*
  1. In the 'Contact Email' field enter your sites contact email address:
    - someone@your-bolt-site.example.com  
    *(change the address to match your site's)*
  1. Click 'Add Platform' button
  1. Choose "Website" in displayed dialogue
  1. Enter your site's relevant URLs:
    - **'Site URL'** - http://your-bolt-site.example.com
    - **'Mobile Site URL'** - http://mobile.your-bolt-site.example.com  
    *(change the domain name to match yours)*
  1. Click 'Save Changes'
1. In the left menu, select 'Status & Review'
  1. Set the toggle next to **"Do you want to make this app and all its live features available to the general public?"** to Yes
  1. Click 'Confim' in the displayed dialogue
1. In the left menu, select 'Dashboard'
1. Add the 'App ID' and 'App Secret' to your config.yml file


**Multiple URLs**
1. Go to https://developers.facebook.com
1. In the left menu, select 'Settings'
1. Select the 'Advanced' tab
1. Scroll down to the 'Security' section of the page
1. Add URLs to the 'Valid OAuth redirect URIs' field

GitHub
------
1. Log into GitHub
1. Go to: https://github.com/settings/applications/
1. Click 'Register new application'
1. Fill in the fields:
  1. **Application name**
  1. **Homepage URL**: http://your-bolt-site.example.com
  *(change the domain name to match yours)*
  1. **Application description**
  1. **Authorization callback URL**: http://your-bolt-site.example.com/authenticate/endpoint?hauth.done=Github  
  *(change the domain name to match yours)*
1. Click 'Register application'
1. Add the 'Client ID' and 'Client Secret' to your config.yml

Advanced Configuration
----------------------

See <a href="http://hybridauth.sourceforge.net/userguide.html" target="_blank">
the hybrid auth userguide</a> for advanced configuration options.

Template Usage
==============

You can use the following functions and snippets in your templates


Login Link(s)
-------------

There are two Twig function options for displaying the login links:

```
    {{ displaylogin() }}
```

``` 
    {{ displaylogin(true) }}
```
    
In the first instance, after authentication a user is redirected to the homepage.

By supplying the parameter `true` the user is redirected to the current page.

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

By supplying the parameter `true` the user is redirected back to the current page.

Dynamic Link
------------

If you want the login/logout to be automatically varied based on whether a user
is logged in or our, you can use:

```
    {{ displayauth() }}
```

ClientLogin in Bolt Extensions
==============================

This extension is pretty bare-bones by design. Most likely, you will use this 
extension in combination with another extension that builds on its functionality. 

To get information about the current visitor:

```php
if ($this->app['clientlogin.session']->doCheckLogin()) {
    // User is logged in
    if ($this->app['clientlogin.records']->getUserProfileBySession($this->app['clientlogin.session']->token)) {
        $username = $this->app['clientlogin.records']->user['username'];
        $provider = $this->app['clientlogin.records']->user['provider'];
        $providerdata = json_decode($this->app['clientlogin.records']->user['providerdata']);
    }
}
```

Event Handlers
--------------

If you want to hook into login/logout events, ClientLogin dispatches events to 
listeners on `clientlogin.Login` and `clientlogin.Logout`.

You can add the hooks and specify callback functions like so: 

```php
$this->app['dispatcher']->addListener('clientlogin.Login',  array($this, 'myLoginCallback'));
$this->app['dispatcher']->addListener('clientlogin.Logout', array($this, 'myLogoutCallback'));
```

Inside your callback you can get the array of user profile data for the login/logout
with:

```php
public function myLoginCallback(\Bolt\Extension\Bolt\ClientLogin\ClientLoginEvent $event)
{ 
    $userdata = $event->getUser();
}
```
