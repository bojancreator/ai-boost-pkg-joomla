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

use Weeblr\Wblib\Forseo\Wb;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

$rule            = $this->get('rule')->getRule();
$error           = $this->get('error');
$suggested       = $this->get('suggested');
$noSuggestedText = Wb\arrayGet($rule, 'actionErrorNoSuggestText', '');

$header = str_replace('&apos;', '\'', Wb\arrayGet($rule, 'actionErrorTitle', ''));
$text   = str_replace('&apos;', '\'', Wb\arrayGet($rule, 'actionErrorContent', ''));

?>
<div id="forseo_error_page_content">
	<?php if (!empty($header)): ?>
        <h1 class="forseo_error_page_title"><?php echo $this->escape($header); ?></h1>
	<?php endif; ?>
	<?php if (!empty($text)): ?>
		<?php echo $text; ?>
	<?php endif; ?>
	<?php if (Wb\arrayIsTruthy($rule, 'actionErrorSuggest') && !empty($suggested)): ?>
        <hr/>
		<?php echo Wb\arrayGet($rule, 'actionErrorSuggestTitle', ''); ?>
        <ul class="forseo_error_page_suggested">
			<?php foreach ($suggested as $url => $title): ?>
                <li class="forseo_error_page_suggested">
                    <a href="<?php echo $url; ?>"
                       title="<?php echo $this->escape($title); ?>"><?php echo $this->escape($title); ?></a>
                </li>
			<?php endforeach; ?>
        </ul>
	<?php elseif (Wb\arrayIsTruthy($rule, 'actionErrorSuggest') && !empty($noSuggestedText)): ?>
		<?php echo $noSuggestedText; ?>
	<?php endif; ?>
	<?php if (Wb\arrayIsTruthy($rule, 'actionErrorShowDetails')): ?>
        <hr/>
        <small class="forseo_error_page_details">
            <span class="forseo_error_page_details">
                <?php echo (int)$error->getCode(); ?>: <em><?php echo $this->escape($error->getMessage()); ?></em>
            </span>
        </small>
        <hr/>
	<?php endif; ?>
</div>