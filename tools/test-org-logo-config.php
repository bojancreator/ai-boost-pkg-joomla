<?php

/**
 * Test org_logo field value from JoomlaBoost plugin config
 * Upload to Joomla root and access: yourdomain.com/test-org-logo-config.php
 */

define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

$app = Factory::getApplication('site');

echo "<h2>JoomlaBoost org_logo Config Test</h2>";
echo "<hr>";

// Get plugin params
$db = Factory::getDbo();
$query = $db->getQuery(true)
    ->select('params, enabled')
    ->from('#__extensions')
    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
    ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
    ->where($db->quoteName('element') . ' = ' . $db->quote('joomlaboost'));

$db->setQuery($query);
$result = $db->loadObject();

if (!$result) {
    echo "<p style='color:red'>❌ Plugin not found in database!</p>";
    exit;
}

echo "<h3>Plugin Status</h3>";
echo "<ul>";
echo "<li><strong>Enabled:</strong> " . ($result->enabled ? '✅ Yes' : '❌ No') . "</li>";
echo "</ul>";

$params = new \Joomla\Registry\Registry($result->params);

echo "<h3>Organization Settings</h3>";
echo "<ul>";
echo "<li><strong>org_name:</strong> <code>" . htmlspecialchars($params->get('org_name', '(empty)')) . "</code></li>";
echo "<li><strong>org_logo:</strong> <code>" . htmlspecialchars($params->get('org_logo', '(empty)')) . "</code></li>";
echo "</ul>";

$orgLogo = $params->get('org_logo', '');

if (empty($orgLogo)) {
    echo "<h3 style='color:red'>❌ Problem Found!</h3>";
    echo "<p><strong>org_logo field is EMPTY in database!</strong></p>";
    echo "<p>Go to: Extensions → Plugins → System - JoomlaBoost → OpenGraph Settings</p>";
    echo "<p>Fill in the 'Organization Logo' field with image path like:</p>";
    echo "<pre>images/LOGO-SERBIA-CREW.png</pre>";
} else {
    echo "<h3 style='color:green'>✅ org_logo is SET</h3>";

    // Test URL conversion
    $baseUrl = rtrim(\Joomla\CMS\Uri\Uri::root(), '/');

    if (!str_starts_with($orgLogo, 'http')) {
        $absoluteUrl = rtrim($baseUrl, '/') . '/' . ltrim($orgLogo, '/');
    } else {
        $absoluteUrl = $orgLogo;
    }

    echo "<p><strong>Raw value:</strong> <code>" . htmlspecialchars($orgLogo) . "</code></p>";
    echo "<p><strong>Converted to absolute URL:</strong> <code>" . htmlspecialchars($absoluteUrl) . "</code></p>";

    // Test if image exists
    if (str_starts_with($orgLogo, 'http')) {
        echo "<p><strong>URL Test:</strong> External URL (cannot test locally)</p>";
    } else {
        $imagePath = JPATH_ROOT . '/' . ltrim($orgLogo, '/');
        if (file_exists($imagePath)) {
            echo "<p style='color:green'><strong>✅ Image file EXISTS on server</strong></p>";
            echo "<p>Path: <code>" . htmlspecialchars($imagePath) . "</code></p>";
        } else {
            echo "<p style='color:red'><strong>❌ Image file NOT FOUND on server</strong></p>";
            echo "<p>Expected path: <code>" . htmlspecialchars($imagePath) . "</code></p>";
        }
    }

    echo "<h4>Preview:</h4>";
    echo "<img src='" . htmlspecialchars($absoluteUrl) . "' alt='Logo' style='max-width:300px; border:1px solid #ccc; padding:10px;'>";
}

echo "<hr>";
echo "<h3>OpenGraph Settings</h3>";
echo "<ul>";
echo "<li><strong>og_site_name:</strong> <code>" . htmlspecialchars($params->get('og_site_name', '(empty)')) . "</code></li>";
echo "<li><strong>og_image:</strong> <code>" . htmlspecialchars($params->get('og_image', '(empty)')) . "</code></li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Next Step:</strong> If org_logo is empty, fill it in plugin settings and save.</p>";
echo "<p style='color:red'><strong>Delete this file after testing!</strong></p>";
