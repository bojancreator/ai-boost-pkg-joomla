<?php
/**
 * Minimal stub of \Joomla\CMS\MVC\Controller\BaseController for unit tests.
 *
 * Lets the admin controller classes (SettingsController, ImportController)
 * be loaded outside a live Joomla install so their class constants and pure
 * static helpers can be exercised directly. Runtime behaviour (app, input,
 * session, headers) is NOT stubbed — tests must stick to static surfaces.
 *
 * Not loaded by bootstrap.php; tests that need it require_once it first.
 */

namespace Joomla\CMS\MVC\Controller;

class BaseController
{
    public function __construct()
    {
    }
}
