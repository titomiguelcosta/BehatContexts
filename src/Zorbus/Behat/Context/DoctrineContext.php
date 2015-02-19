<?php

namespace Zorbus\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Doctrine\ORM\Tools\SchemaTool;
use Behat\Gherkin\Node\TableNode;
use Doctrine\ORM\EntityManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\Migration;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use PDOException;
use RuntimeException;
use Exception;

class DoctrineContext implements Context, SnippetAcceptingContext
{
    private $connection;
    private $entityManager;

    public function __construct(Connection $connection, EntityManager $entityManager)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
    }

    /**
     * @BeforeScenario @database
     */
    public function cleanDatabase(BeforeScenarioScope $scope)
    {
        $this->connection->getSchemaManager()->dropAndCreateDatabase($this->connection->getDatabase());
        $this->buildDatabase();
    }

    /**
     * @Given the repository :class has the following records:
     */
    public function theRepositoryHasTheFollowingRecords($class, TableNode $table)
    {
        foreach ($table as $entries) {
            if (false === class_exists($class)) {
                throw new RuntimeException(sprintf('The class %s does not exist.', $class));
            }
            $entity = new $class();

            foreach ($entries as $field => $value) {
                $setter = 'set' . ucfirst($field);

                if (false === method_exists($entity, $setter)) {
                    throw new RuntimeException(sprintf('The class %s does not have a method named %s.', $class, $setter));
                }
                $entity->$setter($value);
            }

            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush($entity);
    }

    /**
     * @Given I truncate the repository :repository
     */
    public function ITruncateTheRepository($class)
    {
        $table = $this->entityManager->getClassMetadata($class)->getTableName();
        $this->connection->executeQuery($this->connection->getDatabasePlatform()->getTruncateTableSQL($table, true));
    }

    /**
     * @Then the repository :class has :count records
     * @Then the repository :class has :count record
     */
    public function theRepositoryHasRecords($class, $count)
    {
        $this->theRepositoryHasRecordsMatching($class, $count);
    }

    /**
     * @Then the repository :class has :count records matching:
     * @Then the repository :class has :count record matching:
     */
    public function theRepositoryHasRecordsMatching($class, $count, TableNode $criteria = null)
    {
        $query = $this
            ->entityManager
            ->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($class, 'e');

        if ($criteria instanceof TableNode) {
            // validate table headers
            $rows = $criteria->getRows();
            $keys = array_shift($rows);
            if (false === in_array('field', $keys) || false === in_array('value', $keys)) {
                throw new RuntimeException('Missing at least one of the table headers: field, value');
            }

            $pos = 0;
            foreach ($criteria as $rows) {
                $placeholder = 'pos' . $pos;
                $query->where(sprintf('e.%s = :%s', $rows['field'], $placeholder));
                $query->setParameter($placeholder, $rows['value']);
            }
        }

        $rows = $query
            ->getQuery()
            ->getSingleScalarResult();

        if ($rows != $count) {
            throw new RuntimeException(sprintf('Expected %d rows, but got %d instead.', $count, $rows));
        }
    }

    /**
     * @Given the fixtures in the directory :dir are loaded
     */
    public function theFixturesInTheDirectoryAreLoaded($dir, $append = false)
    {
        if (false === is_dir($dir)) {
            throw new RuntimeException(sprintf('%s is not a valid directory path', $dir));
        }

        $loader = new Loader();
        $loader->loadFromDirectory($dir);

        $purger = $append ? null : new ORMPurger();
        $executor = new ORMExecutor($this->entityManager, $purger);
        $executor->execute($loader->getFixtures(), $append);
    }

    /**
     * @Given the fixtures in the directory :dir are appended
     */
    public function theFixturesInTheDirectoryAreAppended($dir)
    {
        $this->theFixturesInTheDirectoryAreLoaded($dir, true);
    }

    /**
     * @Given the fixture :file is loaded
     */
    public function theFixtureIsLoaded($file, $append = false)
    {
        $sourceFile = realpath($file);

        if (false === file_exists($sourceFile) || false === is_readable($sourceFile)) {
            throw new RuntimeException(sprintf('%s is not a valid or readable file', $file));
        }

        $loader = new Loader();
        require_once $sourceFile;
        $declared = get_declared_classes();

        foreach ($declared as $className) {
            $reflClass = new \ReflectionClass($className);

            if ($reflClass->getFileName() === $sourceFile && !$loader->isTransient($className)) {
                $fixture = new $className;
                $loader->addFixture($fixture);
            }
        }

        $purger = $append ? null : new ORMPurger();
        $executor = new ORMExecutor($this->entityManager, $purger);
        $executor->execute($loader->getFixtures(), $append);
    }

    /**
     * @Given the fixture :file is appended
     */
    public function theFixtureIsAppended($file)
    {
        $this->theFixtureIsLoaded($file, true);
    }

    private function buildDatabase()
    {
        $this->selectDatabase();
        $this->connection->query($this->dumpStructure());
    }

    private function createDatabase()
    {
        try {
            $this->connection->getSchemaManager()->createDatabase($this->connection->getDatabase());
        } catch (PDOException $e) {
            // ignore exception, the database might exist already
        }
    }

    private function dropDatabase()
    {
        $this->connection->getSchemaManager()->dropDatabase($this->connection->getDatabase());
    }

    /**
     * Runs the symfony equivalent command doctrine:schema:update --dump-sql
     *
     * @return string
     */
    private function dumpStructure()
    {
        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if (false === empty($metadatas)) {
            $schemaTool = new SchemaTool($this->entityManager);
            $sqls = $schemaTool->getUpdateSchemaSql($metadatas, true);

            if (0 === count($sqls)) {
                throw new RuntimeException("No queries to execute.");
            }

            return implode(';', $sqls) . ';';
        } else {
            throw new RuntimeException("No metadata available.");
        }
    }

    /**
     * Runs the symfony equivalent command doctrine:migrations:migrate
     */
    private function runMigrations()
    {
        $this->selectDatabase();

        $config = new Configuration($this->connection);
        $config->setMigrationsTableName('migration_versions');
        $config->setMigrationsNamespace('Application\\Migrations');
        $config->setMigrationsDirectory($this->migrationsDir);
        $config->registerMigrationsFromDirectory($config->getMigrationsDirectory());

        $migration = new Migration($config);
        try {
            $migration->migrate();
        } catch (Exception $e) {
            throw new RuntimeException(sprintf('Could not run the migrations. Error message: %s', $e->getMessage()));
        }
    }

    private function selectDatabase()
    {
        try {
            $this->connection->executeQuery(sprintf('USE %s', $this->connection->getDatabase()));
        } catch (Exception $e) {
        }
    }
}
