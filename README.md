# MySQL-Obejct-Model

A PHP Object Model using MySQL as backend via PDO. Allows fast scaffoling of Models with a high performance MySQL specific backend.
First version is available, then more information regarding installation, use and namespacing will follow.
Memcache and Static cache has been added for MOMBase and MOMSimple

## Version - beta
This project is currently begin used in small and medium projects by myself, Solaris (scout group), IT-Infrastructure @ Roskilde-festival and is considered quite stable.
No guareentes are given when using this code, but all suggestions and bugreports are welcome.

## Usage
### Example table
```sql
CREATE TABLE `admin`.`userstest` (
	`user_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	`active` BOOLEAN UNSIGNED NOT NULL DEFAULT TRUE,
	`created` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`user_id`), INDEX (`name`))
	ENGINE = INNODB;
```
### Class definition
A simple example on the usage of MOMSimple
```php
class User extends \namespace\MOMSimple
{
	const DB = 'administration';
	const TABLE = 'users';

	// Primary column name (required)
	const COLUMN_PRIMARY_KEY = 'user_id';

	// Other column names (optional)
	const COLUMN_NAME = 'name';
	const COLUMN_ACTIVE = 'active';
	const COLUMN_CREATED = 'created';
}
```
You only need to define the optional columns you want to use for easy (constant safe) access, object member variables will be created from database table definition using SQL lookup.

### Connecting SQL using PDO
```php
$connection = new \PDO('mysql:host=127.0.0.1', 'myuser', 'mypasswd');

// Sets a global PDO connection for MOMBase to use
\namespace\MOMBase::setConnection($connection, TRUE);

// Sets a specific PDO connection for User to use
User::setConnection($connection);
```

### Creating and saving objects
```php
// Create a new object
$object1 = new User();

// Set object properties (all properties are public by default)
$object1->name = 'foo';
$object1->description = 'imfulloffoo';

// Save the object to the database and refetch it (update autoincrements, timestamps and other default values
$object1->save();
```
#### Get object by primary key
```php
// Fetch the object we just created using primary id (assuming user_id is 1)
$object1 = User::getById(1);
if ($object1 instanceOf User)
{
	echo $object1->name;
}
```

### Fetching objects
#### All
```php
// Fetch all User objects
$users = User::getAll();
foreach ($users as $user)
{
	echo $user->name;
}
```

#### Filtering
##### Many
```php
Fetch some User objects by SQL where clause
$where = '`'.User::COLUMN_NAME.'` = '.User::escapeStatic('foo');
$someObjects = User::getAllByWhere($where);
foreach ($someObjects as $object)
{
	echo $object->name;
}
```
##### One
```php
Fetch one User object by SQL where clause
$where = '`'.User::COLUMN_NAME.'` = '.User::escapeStatic('foo');
$order = '`'
$someObjects = User::getOne($where);
foreach ($someObjects as $object)
{
	echo $object->name;
}
```
### Modifying object
#### When fetching objects
You can overwrite the default load method to change member variables after an object is fetched from the database, you might want to "cast" all timestamps and datetimes into DateTime objects
```
class User extends MOMSimple
{
	...
	/**
	  * Fills object
	  * Creates public vars on object with name of row key, and value of row value
	  * Sets object to be old, meaning that its been fetched from database
	  * @param string[] $row \PDO_result->fetch_assoc
	  */
	protected function fill($row)
	{
		parent::fill($row);

		$this->created = DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
	}
	...
}

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
