![http://whmcs.com/appstore/2706/WHMCS-Registrars-COZA.html](http://img.shields.io/badge/WHMCS-AppStore-blue.svg "Official Community App")

whmcs-registrars-coza
=====================

**whmcs-registrars-coza** is a domain registrar module connecting the [CO.ZA](https://www.registry.net.za)
zone with [WHMCS](https://www.whmcs.com). It uses EPP for communicating with the Registry via the
[php-epp2](https://github.com/AfriCC/php-epp2) library.

It is written in modern PHP and tries to fix some shortcommings of the existing
registrar modules.

Released under the GPLv3 License, feel free to contribute (fork, create
meaningful branchname, issue pull request with thus branchname)!


**Table of Contents**  *generated with [DocToc](http://doctoc.herokuapp.com/)*

- [whmcs-registrars-coza](#user-content-whmcs-registrars-coza)
    - [Features](#user-content-features)
    - [Requirements](#user-content-requirements)
    - [Installation](#user-content-installation)
        - [Upload](#user-content-upload)
        - [Activate](#user-content-activate)
        - [Config](#user-content-config)
        - [Cronjob](#user-content-cronjob)
        - [Language Overrides](#user-content-language-overrides)
        - [Templates](#user-content-templates)
            - [clientareadomaincontactinfo.tpl](#user-content-clientareadomaincontactinfotpl)
            - [clientareadomaindetails.tpl](#user-content-clientareadomaindetailstpl)
            - [clientareadomainregisterns.tpl](#user-content-clientareadomainregisternstpl)
    - [Changelog](#user-content-changelog)
    - [Credits](#user-content-credits)
    - [Acknowledgments](#user-content-acknowledgments)
    - [License](#user-content-license)


Features
--------

* consistent contact handles based on user base of WHMCS
* contact update hooks, e.g. as soon as a client changes his details, all
domains mapped to that contact will be update as well
* advanced transfersync cleanups (no dead contact handles, applies contact + ns as given)
* glue records
    * creation + deletion
    * mix host names with glue-records
    * round-robin records for glue-records
    * IPv6 glue-records
* OTE integration (auto masks co.za with test zone)


Requirements
------------

It uses [php-epp2](https://github.com/AfriCC/php-epp2) as EPP client library,
so it shares the requirements with that package:

* WHMCS 5.3.7 or higher
* PHP5.4 or higher
* libicu 4.8 or higher
* php-intl 3 or higher
* php-mcrypt


Installation
------------

### Upload

Upload everything inside the "*upload*" folder (excluding the folder itself)
directly into your WHMCS installation.


### Activate

1) Setup -> Products/Services -> Domain Registrars (Activate + Configure **CO.ZA**)

2) Setup -> Addon Modules (Activate + Configure **CO.ZA EPP Messages**)

3) Setup -> Staff Management -> Administrator Roles -> Edit (enable **CO.ZA Balance**)


### Config

![whmcs-registrars-coza configuration](https://www.afri.cc/img/whmcs-registrars-coza.png "Configuration Screen")


### Cronjob

in */etc/cron.d/whmcs*

```
MAILTO=hostmaster@YOURDOMAIN.COM

# default whmcs cron
43 */4 * * * www-data cd /var/www/whmcs/crons; php -q domainsync.php > /dev/null
# epp poll messages
13 * * * *   www-data php -q /var/www/whmcs/crons/cozapoll.php > /dev/null
# cleans up after a domain transfer-in
33 * * * *   www-data php -q /var/www/whmcs/crons/cozacleanup.php > /dev/null
```


### Language Overrides

It is highly recommended to add the following (or similar) in your lanuage overrides
(e.g. */lang/overrides/english.php*)

```php
$_LANG['domainregisternsreg'] = 'Create a Glue Record';
$_LANG['domainregisternsdel'] = 'Delete a Glue Record';
$_LANG['domainregisternsmod'] = 'Modify a Glue Record';
$_LANG['domainregisterns'] = 'Glue Records';
$_LANG['domainregisternsexplanation'] = 'Required if a domain below the same zone is used as nameserver (eg. NS1.yourdomain.co.za, NS2.yourdomain.co.za).';
$_LANG['cartnameserversdesc'] = '<p>If you want to use custom nameservers then enter them below. By default, new domains will use our nameservers for hosting on our network.</p><p>After domain registration you will also be able to create <em>glue records</em>.</p>';
$_LANG['domainnsexp'] = '<p>You can change where your domain points to here. Please be aware changes can take up to 24 hours to propogate.</p><p>Please go to "Management Tools" » "Glue Records" to delete or create Glue Records</p>';
$_LANG['domaincontactchoose'] = 'Current Contact';
```


### Templates

#### clientareadomaincontactinfo.tpl

will hide the custom-whois form in favor of a consistent user-/contactid:

```
SEARCH:
<p><label class="full control-label"><input type="radio" class="radio inline" name="wc[{$contactdetail}]" id="{$contactdetail}1" value="contact" onclick="usedefaultwhois(id)"{if $defaultns} checked{/if} /> {$LANG.domaincontactusexisting}</label></p>

REPLACE:
{if $domain|regex_replace:"/^([^\.]+\.)/":"" eq "co.za"}
<input type="hidden" name="wc[{$contactdetail}]" id="{$contactdetail}1" value="contact">
{else}
<p><label class="full control-label"><input type="radio" class="radio inline" name="wc[{$contactdetail}]" id="{$contactdetail}1" value="contact" onclick="usedefaultwhois(id)"{if $defaultns} checked{/if} /> {$LANG.domaincontactusexisting}</label></p>
{/if}
```

```
SEARCH:
<option value="u{$clientsdetails.userid}">{$LANG.domaincontactprimary}</option>

REPLACE:
<option value="u{$clientsdetails.userid}" {if $contactdetails.Registrant.user_id eq $clientsdetails.userid}selected{/if}>{$LANG.domaincontactprimary}</option>
```

```
SEARCH:
<option value="c{$contact.id}">{$contact.name}</option>

REPLACE:
<option value="c{$contact.id}" {if $contactdetails.Registrant.contact_id eq $contact.id}selected{/if}>{$contact.name}</option>
```

```
SEARCH:
<p><label class="full control-label"><input type="radio" class="radio inline" name="wc[{$contactdetail}]" id="{$contactdetail}2" value="custom" onclick="usecustomwhois(id)"{if !$defaultns} checked{/if} /> {$LANG.domaincontactusecustom}</label></p>
<fieldset>
(...)
</fieldset>

ADD BEFORE:
{if $domain|regex_replace:"/^([^\.]+\.)/":"" neq "co.za"}

ADD AFTER:
{/if}
```


#### clientareadomaindetails.tpl

disables the input for glue records to force users to use the "Glue Record"
panel and therfore not screw up with records.

```
SEARCH:
<input class="input-xlarge domnsinputs" id="ns1" name="ns1" type="text" value="{$ns1}" />
<input class="input-xlarge domnsinputs" id="ns2" name="ns2" type="text" value="{$ns2}" />
<input class="input-xlarge domnsinputs" id="ns3" name="ns3" type="text" value="{$ns3}" />
<input class="input-xlarge domnsinputs" id="ns4" name="ns4" type="text" value="{$ns4}" />
<input class="input-xlarge domnsinputs" id="ns5" name="ns5" type="text" value="{$ns5}" />

REPLACE:
<input class="input-xlarge domnsinputs" id="ns1" name="ns1" type="text" value="{$ns1}" {if $ns1|replace:$domain:'' neq $ns1}disabled{/if} />
<input class="input-xlarge domnsinputs" id="ns2" name="ns2" type="text" value="{$ns2}" {if $ns2|replace:$domain:'' neq $ns2}disabled{/if} />
<input class="input-xlarge domnsinputs" id="ns3" name="ns3" type="text" value="{$ns3}" {if $ns3|replace:$domain:'' neq $ns3}disabled{/if} />
<input class="input-xlarge domnsinputs" id="ns4" name="ns4" type="text" value="{$ns4}" {if $ns4|replace:$domain:'' neq $ns4}disabled{/if} />
<input class="input-xlarge domnsinputs" id="ns5" name="ns5" type="text" value="{$ns5}" {if $ns5|replace:$domain:'' neq $ns5}disabled{/if} />
```


#### clientareadomainregisterns.tpl

Add multiple IP inputs to allow round-robin records + disable "modify" glue records
as currently not supported by module.

```
SEARCH:
<input type="text" name="ipaddress" id="ip1" />

REPLACE:
<input type="text" name="ipaddress[]" id="ip1" />
<input type="text" name="ipaddress[]" id="ip2" />
<input type="text" name="ipaddress[]" id="ip3" />
<input type="text" name="ipaddress[]" id="ip4" />
<input type="text" name="ipaddress[]" id="ip5" />
```

```
SEARCH:
<input type="hidden" name="sub" value="modify" />

SURROUND BY:
{if $domain|regex_replace:"/^([^\.]+\.)/":"" neq "co.za"}

{/if}
```


Changelog
---------

* 0.1.0 - 2014-06-19
    * Initial Version


Credits
-------

* [Günter Grodotzki](https://twitter.com/lifeofguenter)
* [All Contributors](https://github.com/AfriCC/whmcs-registrars-coza/graphs/contributors)


Acknowledgments
---------------

* [Nigel Kukard](https://gitlab.devlabs.linuxassist.net/u/nkukard) (original author of [whmcs-coza-epp](https://gitlab.devlabs.linuxassist.net/awit-whmcs/whmcs-coza-epp))
* [All Contributors of whmcs-coza-epp](https://gitlab.devlabs.linuxassist.net/awit-whmcs/whmcs-coza-epp/graphs/master)


License
-------

whmcs-registrars-coza is released under the GPLv3 License. See the bundled
[LICENSE](https://github.com/AfriCC/whmcs-registrars-coza/blob/master/LICENSE)
file for details.