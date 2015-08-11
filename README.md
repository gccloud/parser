# Parser
CI Parser Library extension (empty tags replacement, extended loops, conditional IF and SWITCH structures, CI Helper calls)
<br>
<br>
## Initial Note
> To see how to load, initialize and use standard methods of the CI Parser Library, see [Template Parser Class Documentation](http://www.codeigniter.com/user_guide/libraries/parser.html)

<br>
## Installation
### Step 1 Installation by Composer
#### Edit /composer.json
```json
{
    "require": {
        "MY_Parser": "dev-master"
    },
    "repositories": [
        {
            "type": "vcs",
            "url":  "http://195-154-184-158.rev.poneytelecom.eu:7990/scm/tool/my_parser.git"
        }
    ]
}
```
#### Run composer update
```shell
composer update
```

### Step 2 Create file
```txt
/application/libraries/MY_Parser.php
```
```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require(APPPATH.'/libraries/MY_Parser/MY_Parser.php');
```

<br>
## Basic Changes
- While parsing a view, all tags found that does not match any data variable will be replaced with an empty string
- The Parser will automatically parse any variable loaded in CI via the Loader Class

<br>
## Advanced Examples
### Declaring conditional blocks
#### Conditional IF
{<b>if</b> {<i>variable</i>} <i>condition</i> <i>value</i>}<br>
block to output if condition is TRUE<br>
{<b>/if</b>}
<br>
OR
<br>
{<b>if</b> {<i>variable</i>} <i>condition</i> <i>value</i>}<br>
block to output if condition is TRUE<br>
{<b>else</b>}<br>
block to output if condition returns FALSE<br>
{<b>/if</b>}
> the *condition* tag may take any value among ==, !=, <>, <, <=, > or >=

#### Conditional SWITCH
{<b>switch</b> {<i>variable</i>}}<br>
{<b>case</b> <i>first_value</i>}<br>
block to output if the first case matches excpected value<br>
{<b>break</b>}<br>
{<b>case</b> <i>second_value</i>}<br>
block to output if the second case matches excpected value<br>
{<b>break</b>}<br>
{<b>default</b>}<br>
default block to output<br>
{<b>break</b>}<br>
{<b>/switch</b>}
<br>
<br>
### Declaring loops
{<b>for</b> <i>index</i> <b>from</b> <i>start_value</i> <b>to</b> <i>end_value</i> <b>step</b> <i>step_value</i>}<br>
output block to loop on with the {<i>index</i>} displayed at each step<br>
{/<b>for</b>}
<br>
<br>
### Viewing indexes in an array structure
{<i>array_name</i>}<br>
{<b>index in</b> <i>array_name</i>}<br>
{/<i>array_name</i>}
<br>
<br>
### Calling a Helper in a view
{<b>helper_name</b>([<i>argument_1</i> [, <i>argument_2</i> ...]])}
<br>
For example :

```php
<form action="{site_url(controller/method/key1/value1/key2/value2)}" role="form" class="form-inline">
```

will be equivalent to :

```php
<form action="<?php echo site_url('controller/method/key1/value1/key2/value2'); ?>" role="form" class="form-inline">
```

> Note that, though arguments are passed to Helpers as strings, they don't require surrounding simple or double quotes when called via the Parser.

Helper calls can also be nested. For example :

```php
{form_open({site_url(controller/method/key1/value1/key2/value2)}, role="form" class="form-inline")}
```

will be equivalent to :

```php
<?php form_open(site_url('controller/method/key1/value1/key2/value2'), 'role="form" class="form-inline"'); ?>
```