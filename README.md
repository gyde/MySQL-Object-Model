# MySQL-Obejct-Model

A PHP Object Model using MySQL as backend via PDO. Allows fast scaffoling of Models with a high performance MySQL specific backend.
First version is available, then more information regarding installation, use and namespacing will follow.
Memcache and Static cache has been added for MOMBase and MOMSimple

## Version - beta
This project is currently begin used in small and medium projects by myself, Solaris (scout group), IT-Infrastructure @ Roskilde-festival and is considered quite stable.
No guareentes are given when using this code, but all suggestions and bugreports are welcome.

## Usage
A simple example on the usage of MOMSimple
```php
class MyTable extends \namespace\MOMSimple
{
	const DB = 'mydatabase';
	const TABLE = 'mytable';

	// Primary column name (required)
	const COLUMN_PRIMARY_KEY = 'mytable_id';

	// Other column names
	const COLUMN_NAME = 'name';
}

$connection = new \PDO('mysql:host=127.0.0.1', 'myuser', 'mypasswd');

// Sets a global PDO connection for MOMBase to use
\namespace\MOMBase::setConnection($connection, TRUE);

// Sets a specific PDO connection for MyTable to use
MyTable::setConnection($connection);

// Create a new object
$object1 = new MyTable();

// Set object properties (all properties are public by default)
$object1->name = 'foo';
$object1->description = 'imfulloffoo';

// Save the object to the database and refetch it (update autoincrements, timestamps and other default values
$object1->save();

// Fetch all MyTable objects
$allObjects = MyTable::getAll();
foreach ($allObjects as $object)
{
	echo $object->name;
}

// Fetch some MyTable objects by SQL where clause
$where = '`'.MyTable::COLUMN_NAME.'` = \'foo\'';
$someObjects = MyTable::getAllByWhere($where);
foreach ($someObjects as $object)
{
	echo $object->name;
}

// Fetch single object using primary id
$object2 = MyTable::getById(2);
if ($object2 instanceOf MyTable)
{
	echo $object2->name;
}
```

## Classes / Types

### MOMBase.class.php
The generic / factory class which all object models extends from. This contains generic query function and requires that extending classes implements needed methods.

### MOMSimple.class.php
A simple scaffoling class for MySQL tables with ONE column as primary key

### MOMCompound
A compound scaffoling class for MySQL tables with SEVERAL columns as primary key

## Tests (PHPUnit)
Tests has been designed using PHPUnit and will create and dropped tables as needed. 
In order to run the tests, the following needs to be satisfied:

* A running mysql server
* A database named mom and mom2 (test tables will be CREATED and DROPPED here)
* A User with full privileges and grant to both databases (New users will be CREATED and REVOKED)
* An installation of PHPUnit
* Setting environment variables for database, user and password


```sh
export MEMCACHE_HOST="YOUR_HOST"
export MYSQL_HOST="YOUR_HOST"
export MYSQL_USERNAME="YOUR_USERNAME"
export MYSQL_PASSWD="YOUR_PASSWORD"

./phpunit-4.8.phar --bootstrap autoload.php --configuration tests/phpunit.xml --colors -v --debug
```

## Tools 
### build_mom 
Shell script to "build" MOM using first providede argument as namespace.
```sh
./build_mom \\\\my\\\\name\\\\space
```
MOM files will be placed in a folder called build
