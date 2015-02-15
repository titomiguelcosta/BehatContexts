Feature:
  As a developer
  I can test the database
  Using the zorbus database context

  @database
  Scenario: clean the database
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 0 records
    Given I run the fixtures in the directory "tests/Zorbus/Behat/Test/DataFixtures"
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 2 records
    And I append the fixtures in the directory "tests/Zorbus/Behat/Test/DataFixtures"
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 4 records
    And I truncate the repository "Zorbus\Behat\Test\Entity\Post"
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 0 records
    And I append the fixtures in the directory "tests/Zorbus/Behat/Test/DataFixtures"
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 2 records
    And the repository "Zorbus\Behat\Test\Entity\Post" has the following records:
      | title         |
      | Amazing day   |
      | Great record  |
      | Heavy showers |
    Then the repository "Zorbus\Behat\Test\Entity\Post" has 5 records
    And the repository "Zorbus\Behat\Test\Entity\Post" has 1 record matching:
      | field | value       |
      | title | Amazing day |