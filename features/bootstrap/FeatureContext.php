<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Behat\Context\Environment\UninitializedContextEnvironment;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Zorbus\Behat\Context\DoctrineContext;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

class FeatureContext implements Context, SnippetAcceptingContext
{
    public function __construct(array $config, array $entitiesDirs)
    {

    }

    /**
     * Register the doctrine context
     * @BeforeSuite
     */
    public static function prepare(BeforeSuiteScope $scope)
    {
        $environment = $scope->getEnvironment();
        $contexts = $environment->getContextClassesWithArguments();

        $arguments = $contexts['FeatureContext'];

        $metadata = Setup::createAnnotationMetadataConfiguration($arguments['entitiesDirs']);
        $entityManager = EntityManager::create($arguments['config'], $metadata);
        $connection = $entityManager->getConnection();

        $environment->registerContextClass('Zorbus\Behat\Context\DoctrineContext', [
            'connection' => $connection,
            'entityManager' => $entityManager
        ]);
    }
}
