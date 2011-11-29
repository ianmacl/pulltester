## Ian's PullTester
Disclaimer? This is a **personal** project not supported by or affiliated to the [Joomla! Project](http://joomla.org) or [Open Source Matters](osm.org).

The purpose of this script is running the Joomla! platform code style and unit tests against the currently open pull requests.

### Requirements:

* [PHP CodeSniffer](http://pear.php.net/package/PHP_CodeSniffer)
* [PHPUnit](https://github.com/sebastianbergmann/phpunit)
* The [Joomla! Platform](https://github.com/joomla/joomla-platform) (the more recent the better)

### Setup
Edit the config.php and set the required paths.

run

```pulltester.php

Optional arguments:
--update Update the repository
--reset [hard] Reset the data. **hard** will nuke everything !

-v Verbose
```

have Fun ```=;)```
