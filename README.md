# Islandora Group

Islandora Group extends access control with Drupal's [Group module](https://www.drupal.org/project/group)

## Installation

- By composer (recommended):
  ````
    composer require digitalutsc/islandora_group
  ````

- By manually, run `git clone git@github.com:digitalutsc/islandora-group.git` to Drupal module directory. Then download the dependency modules in the next section bellow

## To Configure

[More detail at here](https://docs.google.com/document/d/1fy2KyjlURBpseLbwqspD3Yv5iFPpv1HQF_qKClV7zso/edit?usp=sharing)

## Dependencies
This module has the following dependencies:
- [Drupal Group](https://www.drupal.org/project/group)
- [Group Media](https://www.drupal.org/project/groupmedia)
- [Group Permission](https://www.drupal.org/project/group_permissions)
- [Rules](https://www.drupal.org/project/rules)

## Credits
This module is based on work completed by Danny Lamb at [Born-Digital](https://www.born-digital.com/).

## Default Groups Setup for Testing.

Install the module the following module: https://github.com/digitalutsc/islandora_group_defaults 

## Status

- Currently, by tagging the node, it can add node and its media to a Group, then Group with configuration can take care of access control. 
- **Automated**: 
  - When a group is created, a taxonomy term with the same name as the group's name is created in `Islandora Access` vocabulary. 
  - Experimenting with [Rules](https://www.drupal.org/project/rules) to have the automation of creating a group when Islandora object is created with Model field is set to "Collection", [more detail](https://docs.google.com/document/d/1Amof3KKEqe8EIjUiPQVVRQ8mqnhQQs1wTi_GnDhjYH8/edit?usp=sharing)
- **Ongoing Issues**: 
  - Unable to implement access control from Media level only, ie. restrict media only, but metadata can be opened for public. 
  - Remove node/media from Group UI instead can be buggy which may lead to 500 error.  
  - Effecting the search count of Search results in a Solr View (possibly from Group). 
