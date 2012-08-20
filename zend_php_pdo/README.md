# Zend Framework Data Adapter for NuoDB #

This exposes a Zend_DB_Adapter for NuoDB.  This adapter is developed and tested with the Zend Framework Version 1.11.12.

This interface is written as a PHP PDO extension.  

## PREREQUISITES ##

PHP installation that conforms to the PHP API version 20090626.
NuoDB PHP PDO Driver extension that is installed and configured.
Zend Framework Version 1.11.12.


## BUILDING ##

The Zend Framework Data Adapter for NuoDB is delivered as PHP source code, so there is nothing to build.


## INSTALL ##

To install the NuoDB Zend Framework Data Adapter, copy the file Nuodb.php to your Zend Framework library/Zend/Db/Adapter/Pdo directory.


## QUICK START ##

The zf_hockey directory contains an example Zend Framework application that uses the NuoDB Zend Framework Data Adapter to perform CRUD operations on the NuoDB example Hockey database.

Following is a example Zend Framework resource.db configuration, from the application.ini file for the zf_hockey application:

resources.db.adapter = PDO_NUODB
resources.db.params.host = localhost
resources.db.params.username = dba
resources.db.params.password = goalie
resources.db.params.dbname = test@localhost
resources.db.params.schema = hockey
resources.db.params.options.autoQuoteIdentifiers = false



## LICENSE ##

This module is released under the [NUODB License] [license].

Related Pages:

[homepage]: http://www.nuodb.com
[license]: https://github.com/nuodb/nuodb-drivers/blob/master/LICENSE
