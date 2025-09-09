<?php

/**
 * JoomlaBoost System Plugin - MINIMAL SAFE VERSION
 *
 * @package     Joomla.Plugin
 * @subpackage  System.joomlaboost
 * @version     0.1.21-minimal
 * @author      JoomlaBoost Team
 * @since       4.0.0
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\Event\SubscriberInterface;

/**
 * JoomlaBoost System Plugin - Minimal Safe Version
 * Only loads basic functionality to test plugin installation
 */
class PlgSystemJoomlaboost extends CMSPlugin implements SubscriberInterface
{
  /**
   * Load the language file on instantiation
   *
   * @var    boolean
   * @since  3.1
   */
  protected $autoloadLanguage = true;

  /**
   * Constructor
   */
  public function __construct(&$subject, $config = [])
  {
    parent::__construct($subject, $config);
    $this->autoloadLanguage = true;
  }

  /**
   * Returns an array of events this subscriber will listen to.
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'onAfterInitialise' => 'onAfterInitialise',
      'onBeforeCompileHead' => 'onBeforeCompileHead',
    ];
  }

  /**
   * System initialization (MINIMAL - only log that plugin loaded)
   */
  public function onAfterInitialise(): void
  {
    // MINIMAL SAFE MODE: Only basic check, no complex operations
    try {
      $app = $this->getApp();
      if ($app && $app->isClient('administrator')) {
        // Only log in admin for debugging
        $app->enqueueMessage('JoomlaBoost v0.1.21-minimal loaded successfully (SAFE MODE)', 'info');
      }
    } catch (Exception $e) {
      // Silent fail - don't break anything
    }
  }

  /**
   * Modify head (MINIMAL - only add comment)
   */
  public function onBeforeCompileHead(): void
  {
    // MINIMAL SAFE MODE: Only add simple comment to confirm plugin works
    try {
      $app = $this->getApp();
      if (!$app || !$app->isClient('site')) {
        return;
      }

      $document = $app->getDocument();
      if (!$document instanceof HtmlDocument) {
        return;
      }

      // Only add a simple comment - no complex operations
      $document->addCustomTag('<!-- JoomlaBoost v0.1.21-minimal SAFE MODE Active -->');
    } catch (Exception $e) {
      // Absolutely silent fail - don't break anything
    }
  }
}
