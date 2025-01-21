<?php

require_once 'symfony_mailer.civix.php';

use CRM_SymfonyMailer_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function symfony_mailer_civicrm_config(&$config): void {
  _symfony_mailer_civix_civicrm_config($config);
  if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Include it if located in vendor.
    require_once 'vendor/autoload.php';
  }
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function symfony_mailer_civicrm_install(): void {
  _symfony_mailer_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function symfony_mailer_civicrm_enable(): void {
  _symfony_mailer_civix_civicrm_enable();
}

function symfony_mailer_civicrm_alterMailer(&$mailer, $driver, $params) {
  $mailer = new \Civi\SymfonyBridge($driver, $params, $mailer);
}
