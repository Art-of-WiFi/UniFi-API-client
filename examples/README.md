## API client class usage examples

This directory contains some PHP code examples which demonstrate usage of the PHP API client class and can be used as a good starting point for your own custom code.

### Usage

Copy the appropriate example file to your working directory together with a copy of the config.template.php file which should be renamed to config.php.
Then update the contents of your new config.php with your controller details and credentials and modify the example file as required to fit your needs.

Also make sure to update the path for the composer autoloader file (`vendor/autoload.php`) or the file containing the Class itself (`src/Client.php`) in your `require_once()` statement as required.

#### Executing scripts from the CLI

Most of the included example scripts can be run from the CLI or shell as follows after the necessary credentials and parameters have been added or updated:


```sh
$ php list_site_health.php
```

NOTE: this does require the `php-cli` module to be installed

### Contribute

If you would like to share your own example file(s), please open an issue and include your code there or else create a pull request.

## Important Disclaimer

Use these examples at your own risk!
