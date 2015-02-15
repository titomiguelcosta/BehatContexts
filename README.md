Zorbus Behat Contexts
=====================

Contexts for Behat 3.

At the moment, just a Doctrine 2 context is included.

Instalation
-----------

composer require zorbus/behat-contexts

Example
-------

Check the FeatureContext.php for an example on how to instantiate the database context. 

In case you are using the Symfony2 extension, you can pass the services defined in the container, no need to create the connection and entity manager manually.

Check database.feature for a real example of the defined steps.

Usage
-----

Tag your scenarios with @database to make clean the database.

Test
----

In the root of the package, run:

touch data/db.sqlite
php vendor/bin/behat --suite=sqlite
