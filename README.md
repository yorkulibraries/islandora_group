# Access control with Group

Islandora Group extends access control with Drupal's [Group module](https://www.drupal.org/project/group)

## Installation

- By composer (recommended):
  ````
    composer require digitalutsc/islandora_group
  ````

- By manually, run `git clone git@github.com:digitalutsc/islandora-group.git` to Drupal module directory. Then download the dependency modules in the next section bellow

## To Configure Group

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

## How to setup this module

1. In content type(s) and media typ(s) which need to be applied access control, create an Entity Reference field which is referenced to Islandora Access Taxonomy Vocabulary.
2. Visit `/admin/config/access-control/islandora_group` to select access control field for each entity of Drupal site. 

### In Node

1. In any node(s) which need to be applied access control, click on a tab "Access Control With Group".
2. Select a Group for this node. 
   * If a node has media, check the media which need to add them to the same Group along with the node.
   * If a node has children nodes, check the child node which need to add them to the same Group along with the node.

### In Media

1. In media types(s) which need to be applied access control, create an Entity Reference field which reference to Islandora Access Taxonomy Vocabulary.
2. In any media(s) which need to be applied access control, click on a tab "Access Control With Group".

### In Bulk Batch Update

1. How to setup: https://www.youtube.com/watch?v=ZMp0lPelOZw
2. Bulk batch update on the access control field which you are setup for entities.

### Work with Federated Search

- Required modules: 
  * Federated Search Front-end user interface: https://github.com/digitalutsc/drupal_ajax_solr 
  * Add a Search Api Solr field for Access Control with Group: https://github.com/digitalutsc/group_solr

- In `/admin/config/search/search-api/index/default_solr_index/fields`, Click Add fields > General > Group: Access Control (search_api_group_access_control) 
- **How does it work ?** 
  - Every time a node or media is indexed to Solr, this field will be processed by checking the access control configuration which is setup with Group module. It will determine the entity to be public or private for annonymous users
  - Field's values to be indexed to Solr:
    - Public: 200 
    - Private: 403
