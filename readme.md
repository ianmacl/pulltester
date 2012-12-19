## Ian's PullTester
Disclaimer? This is a **personal** project **<big>!</big>** It is **not** supported by or affiliated to the [Joomla! Project](http://joomla.org) or [Open Source Matters](osm.org).

The purpose of this script is running the Joomla! platform code style and unit tests against the currently open pull requests.

This is based on [Ian McLennan's PullTester](https://github.com/ianmacl/pulltester). I really admire him for his innovative ideas (like this thingy).

### Requirements:

* [PHP CodeSniffer](http://pear.php.net/package/PHP_CodeSniffer)
* [PHPUnit](https://github.com/sebastianbergmann/phpunit)
* The [Joomla! Platform](https://github.com/joomla/joomla-platform) (the more recent the better)

### Setup
1. Edit the ```config.dist.php```, set the required paths and rename it to ```config.php```.
2. Run the appropriate query from `pulltester/src/sql`

### Execute
```cd``` to the ```src``` path and run

```
pulltester.php
```

**Optional arguments:**

* ```--update``` Update the repository
* ```--reset [hard]``` Reset the data. **hard** will nuke everything !
* ```--pull <number>``` Process only a specific pull

* ```-v``` Verbose

have Fun ```=;)```

