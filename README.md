# Wizard
Wizard simplifies the handling of multistep forms.

## Features
* All forms submit to the same controller/action
* Allow returning to earlier steps or enforce forward only navigation
* Looping - repeat one or more steps as many times as needed
* Plot Branching Navigation (PBN) - decide which path to take depending on the user's response
* Step Timeout - steps can have a timeout to ensure a user responds within a given time
* Pause/resume - save partially completed Wizards; restore and continue from that point
* Event driven - write the handler functions and hook them up to Wizard events

## Installation
Install Wizard using [Composer](https://getcomposer.org/)

Add the following to the <i>require</i> section of your composer.json:

```json
"beastbytes/wizard": "*"
```
 
or run 
```php
php composer.phar require -dev "beastbytes/wizard:*"
```