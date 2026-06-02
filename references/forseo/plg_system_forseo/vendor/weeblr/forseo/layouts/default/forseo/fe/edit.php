<?php
/**
 * Project: 4SEO
 *
 * @package          4SEO
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 *
 */

use Weeblr\Wblib\Forseo\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

$language    = $this->getInArray('config', 'language');
$languageDir = $this->getInArray('config', 'languageDirection');
$pageStatus  = $this->getInArray('config', 'pageStatus');
$urls        = $this->getInArray('config', 'urls');
$baseCssfile = $this->getInArray('config', 'baseCssFile');
$isDev       = $this->isTruthy('config', 'isDev');

?>
<html lang="<?php echo $language ?>" dir="<?php echo $languageDir; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>4SEO Frontend Edit</title>
	<?php if ('ok' == $pageStatus): ?>
		<?php if (!empty($baseCssfile)): ?>
    <link href="<?php echo $this->getInArray('config', 'baseCssFile'); ?>" rel="stylesheet"/>
	<?php endif; ?>
        <script {{nonce_attribute}}>
            var forSeoConfig = <?php echo $this->getAsJson('config') . "\n"; ?>
            var forSeoLanguageStrings = <?php echo $this->getAsJson('languageStrings') . "\n"; ?>
            var forSeoPage = <?php echo $this->getAsJson('page') . "\n"; ?>
        </script>
        <link href="<?php echo $this->getInArray('config', 'cssFile'); ?>" rel="stylesheet"/>
	<?php endif; ?>
</head>
<body>
<div id="forseo_app"></div>
<script <?php if($isDev): ?>type="module"<?php endif;?> src="<?php echo $this->getInArray('config', 'bundleFile'); ?>" defer {{nonce_attribute}}></script>
</body>
</html>

