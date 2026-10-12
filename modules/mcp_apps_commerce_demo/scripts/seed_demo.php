<?php

/**
 * @file
 * Creates the explicit, repeatable Commerce test catalog.
 */

print json_encode(\Drupal::service('mcp_apps_commerce_demo.seed')->seed(), JSON_THROW_ON_ERROR) . PHP_EOL;
