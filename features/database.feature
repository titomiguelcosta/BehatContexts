Feature:
  As a developer
  I can test the database
  Using the zorbus database context

  @database
  Scenario: clean the database
    Given the repository "Zorbus\Behat\Test\Entity\Post" has 0 records
    And the fixtures in the directory "tests/Zorbus/Behat/Test/DataFixtures" are loaded
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 2 records
    And the fixtures in the directory "tests/Zorbus/Behat/Test/DataFixtures" are appended
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 4 records
    And the repository "Zorbus\Behat\Test\Entity\Post" is truncated
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 0 records
    And the fixtures in the directory "tests/Zorbus/Behat/Test/DataFixtures" are appended
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 2 records
    And the fixture "tests/Zorbus/Behat/Test/DataFixtures/LoadPostData.php" is loaded
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 2 records
    And the fixture "tests/Zorbus/Behat/Test/DataFixtures/LoadPostData.php" is appended
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 4 records
    And the repository "Zorbus\Behat\Test\Entity\Post" has the following records:
      | title         |
      | Amazing day   |
      | Great record  |
      | Heavy showers |
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 7 records
    And the repository "Zorbus\Behat\Test\Entity\Post" has 1 record matching:
      | field | value       |
      | title | Amazing day |
