<?php

namespace Zorbus\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\Migration;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Exception;
use PDOException;
use RuntimeException;

class DoctrineContext implements Context, SnippetAcceptingContext
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @param Connection $connection
     * @param EntityManager $entityManager
     * @param string|null $cacheDir
     */
    public function __construct(Connection $connection, EntityManager $entityManager, $cacheDir = null)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->cacheDir = $cacheDir;
    }

    /**
     * @BeforeScenario @database
     */
    public function cleanDatabase(BeforeScenarioScope $scope)
    {
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
                $setter = 'set'.ucfirst($field);

                if (false === method_exists($entity, $setter)) {
                    throw new RuntimeException(
                        sprintf('The class %s does not have a method named %s.', $class, $setter)
                    );
                }
                $entity->$setter($value);
            }

            $this->entityManager->persist($entity);
            $this->entityManager->flush($entity);
        }
    }

    /**
     * @Given the repository :repository is truncated
     */
    public function theRepositoryIsTruncated($class)
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
                $placeholder = 'pos'.$pos;
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
        if ($this->cacheDir && $this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $cachePath = $this->createSqliteCachePath($this->connection->getDatabase());

            if (file_exists($cachePath)) {
                copy($cachePath, $this->connection->getDatabase());

                return;
            } else {
                $this->dropCreateAndSchemaUp();

                $cacheDir = dirname($cachePath);

                if (!is_dir($cacheDir)) {
                    if (!@mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
                        throw new RuntimeException('Failed to create SQLite cache directory: '.$cacheDir);
                    }
                }

                copy($this->connection->getDatabase(), $cachePath);
            }
        } else {
            $this->dropCreateAndSchemaUp();
        }
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
     * @return string[]
     */
    private function dumpStructure()
    {
        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if (false === empty($metadatas)) {
            $schemaTool = new SchemaTool($this->entityManager);
            $sqls = $schemaTool->getUpdateSchemaSql($metadatas, false);

            if (0 === count($sqls)) {
                throw new RuntimeException("No queries to execute.");
            }

            return $sqls;
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

    /**
     * @param string $databasePath
     *
     * @return string
     */
    private function createSqliteCachePath($databasePath)
    {
        return $this->cacheDir.DIRECTORY_SEPARATOR.(new \SplFileInfo($databasePath))->getFilename().'.cache';
    }

    /**
     * Drops, creates the database and builds the schema.
     */
    private function dropCreateAndSchemaUp()
    {
        $this->connection->getSchemaManager()->dropAndCreateDatabase($this->connection->getDatabase());
        $this->selectDatabase();

        $queries = $this->dumpStructure();

        foreach ($queries as $query) {
            $this->connection->exec($query);
        }
    }
}
