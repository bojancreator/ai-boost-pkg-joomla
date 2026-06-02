<?php
/**
 * PHPUnit bootstrap for AI Boost service injection tests.
 *
 * Defines the _JEXEC constant required by all plugin service files,
 * loads the Composer autoloader, and registers minimal Joomla stubs so
 * that the service classes can be loaded and instantiated without a live
 * Joomla installation.
 */

define('_JEXEC', 1);

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/stubs/JoomlaDatabase.php';
require_once __DIR__ . '/stubs/JoomlaRegistry.php';
require_once __DIR__ . '/stubs/JoomlaFactory.php';
require_once __DIR__ . '/stubs/JoomlaPlugin.php';
