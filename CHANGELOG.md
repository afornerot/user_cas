CHANGELOG
=========

Version 1.5.6
-------------
* Change most of the INFO log writes to DEBUG


Version 1.5.5
-------------
* Add Support for ownCloud 10.0.10 and Nextcloud 14.0.0


Version 1.5.4
-------------
* Fixes Nextcloud log flood bug
* Fixes documentation links in info.xml
* Fixes ownCloud/Nextcloud Session Token-Password bug
* Fixes enforce authentication issues with Database Backend
* Fixes enforce authentication issues with public gallery interface


* Adds support for ownCloud until version 10.0.9 and Nextcloud until version 13.0.6
* Adds documentation for basic installation via release archive


* Removes hardcoded PHP version requirements


Version 1.5.3
-------------
* Hotfixes the IP address range separator in the exclude specific IPs field and changes it from "/" to "-"
* Fixes ownCloud 10.0.8 bug and raises compatibility to 10.0.8

Version 1.5.2
-------------
* Add settings field to exclude specific Ips and/or IP-ranges from force login
* Nextcloud: Move settings panel to section "Security" (was in "Additional" before)
* Remove the signature from repo (if you need a signed version, please use one of the release packages or download from ownCloud Market/Nextcloud AppStore)

Version 1.5.1
-------------
* Hotfixes wrong links in 403 error page if enforce authentication was on
* Hotfixes wrong translation in 403 error page for ECAS instances
* Removes return type hints not compatible with PHP 5.6
* Adds functionality to provide more than one mapping field to ownCloud userdata fields (e.g. DisplayName can now be concatenated by a firstname and a lastname CAS-field)

Version 1.5.0
-------------
* Drop ownCloud 9 support
* Major source code optimizations, fix several errors associated with redirection after login
* Add ECAS support
* Add authorization feature via groups
* Add error views for when not authorized or when the CAS-Client throws errors
* Support for ownCloud oauth2 App, it’s now possible to authenticate a desktop or mobile phone client with CAS via oauth2 provider

Version 1.4.9
-------------
* Hotfixes the autocreate bug, mentioned in Issue [#13](https://github.com/felixrupp/user_cas/issues/13).

Version 1.4.8
-------------
* Hotfixes the current 1.4 version to fix a major bug preventing the OCS-Api to work, while the user_cas app is installed and enabled.

Version 1.4.7
-------------
* Hotfixes the min version and **lowers it to 9.1.6**

Version 1.4.6
-------------
* Hotfix for app initialization

Version 1.4.5
-------------
* Fix for autocreate bug
* Re-add phpcas path to use custom phpcas library, if wanted
* Remove GIT submodule for jasig phpcas
* Add composer dependencies instead
* **Raise minimum Owncloud Version to 10.0**

Version 1.4.2, 1.4.3, 1.4.4
---------------------------
* Hotfixes for logging

Version 1.4.1
-------------
* Hotfix for group and protected group handling

Version 1.4.0
-------------
* Completely rewritten in object oriented code, based on Owncloud 9.1 app programming guidelines

Version 0.1.1
-------------
* Added CSRF protection on setting form
* Use openssl_random_pseudo_bytes instead of mt_rand (if available)

Version 0.1
-------------
* Initial plugin
