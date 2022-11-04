@tool @tool_mhacker
Feature: Use tool_mhacker
  In order to check my dev
  As an administrator/developer
  I need to use mhacker

  Scenario: Basic navigation
    Given I log in as "admin"
    And I navigate to "Development > Moodle hacker" in site administration
    And I follow "DB hacker"
    And I follow "user (2)"
    And I should see "admin"
    And I follow "Strings hacker"
    And I follow "tool_mhacker"
    And I should see "pluginname"
    And I follow "Test coverage"
    And I follow "tool_mhacker"
    And I should see "Add checkpoints to all files"
