whmcs-registrars-coza
=====================

**whmcs-registrars-coza** is a domain registrar module connecting the CO.ZA
zone with WHMCS. It uses EPP for communicating with the Registry via the
php-epp2 library.

It is written in modern PHP and tries to fix some shortcommings of the existing
registrar modules.

Released under the GPLv3 License, feel free to contribute (fork, create
meaningful branchname, issue pull request with thus branchname)!


Features
--------

* Consistent Contact Handles based on WHMCS User/Contact base
    * update hooks, e.g. mass update contacts are now possible
* Advanced TransferSync
    * delete registry provisioned contact handle after creating own handle
    * apply nameservers
* Advanced Sync
    * change autorenewal status of domains
* Glue Records
    * creation + deletion
    * mix with nameservers with glue-records
* OTE integration (auto masks co.za with test zone)
    * do testing before going live


Requirements
------------

It uses php-epp2 for the EPP component, so the requirements are basically the same:

* WHMCS 5.3.7 or higher
* PHP5.4 or higher
* libicu 4.8 or higher
* php-intl 3 or higher
* php-mcrypt


Installation
------------

### Upload

Upload everything inside the "upload" folder (excluding the folder itself)
directly into your WHMCS installation.


### Config

screenshot


### Cronjob

llllll

### Language Overrides

llll


### Templates

llll


Changelog
---------

* 0.1.0 - 2014-06-18
    * Initial Version


Credits
-------

* [Günter Grodotzki](https://twitter.com/lifeofguenter)
* [All Contributors](https://github.com/AfriCC/whmcs-registrars-coza/graphs/contributors)


Acknowledgments
---------------

* Nigel Kukard (original author of whmcs-coza-epp)
* [All Contributors of whmcs-coza-epp](https://gitlab.devlabs.linuxassist.net/awit-whmcs/whmcs-coza-epp/graphs/master)


License
-------

whmcs-registrars-coza is released under the GPLv3 License. See the bundled
[LICENSE](https://github.com/AfriCC/whmcs-registrars-coza/blob/master/LICENSE)
file for details.