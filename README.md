# Webform IP Delete

This module helps delete collected IP addresses by the [Webform](https://www.drupal.org/project/webform) module.
When webform allow site administrators to disable this ip collection, sometime we miss enabling this functionnality 
at the right time and removing these informations become necessary for our site.

The module add an action plugins to remove collected IP address for each webform.


For a full description of the module, visit the
[project page](https://www.drupal.org/project/webform_ip_delete).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/webform_ip_delete).

## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers

## Requirements

This module requires the following module:

- [Webform](https://www.drupal.org/project/webform)

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

To use this action plugin you need to activate "Delete collected IP addresses" action at 
/admin/structure/webform/config/#edit-bulk-form-settings. Then this action plugin will be available at the bulk operations 
dropdown under /admin/structure/webforms.

## Maintainers

- Mamadou Diao Diallo - [diaodiallo](https://www.drupal.org/u/diaodiallo)
- Daniel Cothran - [andileco](https://www.drupal.org/u/andileco)
- Nia Kathoni - [nikathone](https://www.drupal.org/u/nikathone)