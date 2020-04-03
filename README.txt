CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * How it works
 * Support requests
 * Maintainers


INTRODUCTION
------------

Social Auth Apple is a Apple Sign-in authentication integration for Drupal. It
is based on the Social Auth and Social API projects

It adds to the site:
 * A new url: /user/login/apple.
 * A settings form at /admin/config/social-api/social-auth/apple.
 * A Apple logo in the Social Auth Login block.


REQUIREMENTS
------------

This module requires the following modules:

 * Social Auth (https://drupal.org/project/social_auth)
 * Social API (https://drupal.org/project/social_api)

APPLE ACCOUNT / CREDENTIALS
------------

The hardest part of the installation is to setup your credentials within the
Apple Developer Account.
You can follow this guide:
https://developer.okta.com/blog/2019/06/04/what-the-heck-is-sign-in-with-apple


INSTALLATION
------------

 * Run composer to install the dependencies.
   composer require "patrickbussmann/oauth2-apple:^0.2.1"

 * Install the dependencies: Social API and Social Auth.

 * Install as you would normally install a contributed Drupal module. See:
   https://drupal.org/documentation/install/modules-themes/modules-8
   for further information.


CONFIGURATION
-------------

 * Add your Apple project OAuth information in
   Configuration » User Authentication » Apple Sign-In.

 * Place a Social Auth Login block in Structure » Block Layout.

 * If you already have a Social Auth Login block in the site, rebuild the cache.


HOW IT WORKS
------------

The user can click on the Apple logo on the Social Auth Login block
You can also add a button or link anywhere on the site that points
to /user/login/apple, so theming and customizing the button or link
is very flexible.

After Apple has returned the user to your site, the module compares the user id
or email address provided by Apple. If the user has previously registered using
Apple or your site already has an account with the same email address, the user
is logged in. If not, a new user account is created. Also, an Apple account can
be associated with an authenticated user.


SUPPORT REQUESTS
----------------

 * Before posting a support request, carefully read the installation
   instructions provided in module documentation page.

 * Before posting a support request, check Recent Log entries at
   admin/reports/dblog

 * When posting a support request, please inform if you were able to see any
   errors in the Recent Log entries.


MAINTAINERS
-----------

Current maintainers:
 * Getulio Sánchez (gvso) - https://www.drupal.org/u/gvso
