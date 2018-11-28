# dhtmlxScheduler with php slim-framework

Implementing backend API for dhtmlxScheduler using php Slim-framework and MySQL.

## Requirements

* php 5.5.0^
* composer
* MySQL


## Setup

* clone or download the demo

```
$ git clone https://github.com/DHTMLX/scheduler-howto-slim
$ cd ./scheduler-howto-slim
```

* import database from mysql_dump.sql 

```
$ mysql -uuser -ppass scheduler < mysql_dump.sql
```

* update the db connection settings in server.js
* install dependencies using composer
```
$ composer install
```

## Run

### PHP built-in server

`php -S localhost:8080 -t public public/index.php`

### Apache configuration

Ensure your .htaccess and index.php files are in the same public-accessible directory. The .htaccess file should contain this code:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```
This .htaccess file requires URL rewriting. Make sure to enable Apache’s mod_rewrite module and your virtual host is configured with the AllowOverride option so that the .htaccess rewrite rules can be used:
```
AllowOverride All
```

## References

- Slim Framework https://www.slimframework.com/

## Tutorial:

A complete tutorial here ...
