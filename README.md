MySQL-Obejct-Model
==================

A PHP Object Model using MySQL as backend via Mysqli. Allows fast scaffoling of Models with a high performance MySQL specific backend.
First version will be available soon, then more information regarding installation, use and namespacing will follow.

### MOMBase
The generic / factory class which all object models extends from. This contains generic query function and requires that extending classes implements needed methods.

### MOMSimple
A simple scaffoling class for MySQL PRIMARY KEY tables

### Tests (PHPUnit)
Tests has been designed using PHPUnit and will create and dropped tables as needed. 
Inorder to run the tests, the following needs to be satisfied:
1. A running mysql server
2. A database with user and password (test tables will be CREATED and DROPPED here)
3. An install of PHPUnit
4. Setting environment variables for database, user and password

```sh
export MYSQLI_HOST="YOUR_HOST"
export MYSQLI_USERNAME="YOUR_USERNAME"
export MYSQLI_PASSWD="YOUR_PASSWORD"

phpunit --bootstrap autoload.php tests/MOMSimpleTest.class.php
```

### Tools 
Comming soon
