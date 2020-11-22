# MySQL-Obejct-Model

A PHP Object Model using MySQL as backend via PDO. Allows fast scaffoling of Models with a high performance MySQL specific backend.
First version is available, then more information regarding installation, use and namespacing will follow.
Memcache and Static cache has been added for MOMBase and MOMSimple

# Table of Contents
1. [Version](##Version)
2. [Usage](##Usage)
3. [Classes](##Classes)
4. [Caveats](##Cateats)
5. [Tools](#Tools)

## Version
This version is considered stable and will be version tagged 1.0 once its been field tested with php7.3!

Current projects using this project:
 * Solaris - mobilspejd.dk - Scout race navigation system)
 * DOG - roskilde-festival IT deployment tool
 * Intranet - Ordbogen A/S intranet

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
A small guide to filtering using where clauses on many or one object
##### Many
Fetch some User objects by SQL where clause
```php
$where = '`'.User::COLUMN_NAME.'` = '.User::escapeStatic('foo');
$someObjects = User::getAllByWhere($where);
foreach ($someObjects as $object)
{
	echo $object->name;

```
Functionality like the above example should in general be placed inside the object class like so:
```php
class User extends MOMSimple
{
	...
	/**
	  * Fetch users using name
	  * @param string $name
	  * @return array<int user_id, User>
	  */
	public static function getByName($name)
	{
		$where = '`'.self::COLUMN_NAME.'` = '.self::escapeStatic($name);
		return self::getAllByWhere($where, null, true);
	}
	...
}
```
##### One
Fetch one User object by SQL where clause
```php
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
```

## Classes / Types

### MOMBase.class.php
The generic / factory class which all object models extends from. This contains generic query function and requires that extending classes implements needed methods.

### MOMSimple.class.php
A simple scaffoling class for MySQL tables with ONE column as primary key

### MOMCompound
A compound scaffoling class for MySQL tables with SEVERAL columns as primary key

## Caveats
Here is a list of common tips, tricks and caveats currently in the MOM system.

### On update / default value columns
If you create a table with a column that has a default "current timestamp" value or "on update" property beware that this column is controlled completely by MOM and can't be updated by setting a value on public class variable. The column is omitted from any insert or update statements that MOM performs.

## Tests (PHPUnit)
Tests has been designed using PHPUnit and will create and droppe tables as needed within the docker env.
In order to run the tests, the following needs to be satisfied:

* A running docker-ce environment

Build the docker environment using
 ./docker/build_container
 ./docker/bootstrap_container /absolute/path/to/MySQL-Object-Model/

Run ./run_tests to start the tests within the docker env.
 ./run_tests

### build_mom
Shell script to "build" MOM using first provided argument as namespace.
```sh
./build_mom my\\\\name\\\\space
```
MOM files will be placed in a folder called build with namespace my\name\space. Additional slashes are needed due to shell escaping
