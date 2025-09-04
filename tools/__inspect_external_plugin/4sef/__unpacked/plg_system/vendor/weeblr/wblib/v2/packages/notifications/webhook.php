<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Notifications;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use function Symfony\Component\String\b;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Manages sending out notifications to the outside world: messaging apps, email
 *
 * System configuration: we may have to notify multiple destinations for each type.
 *
 * For instance, multiple slack or teams channels, or multiple emails addresses.
 * - email: do a BCC to each address
 * - messaging apps: send to each channel in sequence, the exact same message
 *
 * So in System configuration, we could have 2 or 3 alert levels:
 * - admin 1 alerts: name: "a friendly name", destination: email, slack, teams, options: contact@example.com | webhook URL
 * - admin 2 alerts: name: "a friendly name", destination: email, slack, teams, options: contact@example.com | webhook URL
 * - admin 3 alerts: name: "a friendly name", destination: email, slack, teams, options: contact@example.com | webhook URL
 * - user 1 alerts: name: "a friendly name", destination: email, slack, teams, options: contact@example.com | webhook URL
 * - user 2 alerts: name: "a friendly name", destination: email, slack, teams, options: contact@example.com | webhook URL
 * - user 3 alerts: name: "a friendly name", destination: email, slack, teams, options: contact@example.com | webhook URL
 *
 * Then in various locations, admin can select which alert targets to use, just one or several of them.
 *
 * NB: emails list and webhook URLs are still be textarea, so that several can be entered at each level
 */
class Webhook extends Base\Base
{
	// target types
	const EMAIL = 'email';
	const SLACK = 'slack';
	const TEAMS = 'teams';
	const MATTERMOST = 'mattermost';

	// notification types
	const INFO = 'info';
	const ERROR = 'error';
	const WARNING = 'warning';

	// notification priorities
	const NORMAL = 'normal';
	const IMPORTANT = 'important';
	const EMERGENCY = 'emergency';

	const DEV_CONFIGS = [

	];
	/**
	 * @var array
	 */
	private $config;

	/**
	 * @var bool
	 */
	private $isDev;

	public function __construct($configuration = [])
	{
		parent::__construct();

		$this->isDev  = Wb\contains('Forsef', '__DEV__');
		$this->config = array_merge(
			$this->isDev ? include __DIR__ . '/../../../devtest/dev_config.php' : [],
			$configuration
		);
	}

	/**
	 *  A notification is an array:
	 *
	 *  destination array List of destinations to send to
	 *  title string
	 *  message string
	 *  type string
	 *  priority string
	 *  format string md | html
	 *
	 * @param   array  $notification
	 * @param   array  $targets  admin_1 | admin_2 | admin_3 | user_1 | user_2 | user_3
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function notify($notification, $targets = [])
	{
		if (Wb\arrayIsFalsy($this->config, 'enabled'))
		{
			return;
		}

		$notification      = Wb\arrayEnsure($notification);
		$targets           = Wb\arrayEnsure($targets);
		$configuredTargets = Wb\arrayGet($this->config, 'configuredTargets', []);

		foreach ($targets as $target)
		{
			$configuredTarget = Wb\arrayGet($configuredTargets, $target);
			if (
				empty($configuredTarget)
				||
				Wb\arrayIsFalsy($configuredTarget, 'enabled')
			)
			{
				continue;
			}

			$targetMedium         = Wb\arrayGet($configuredTarget, 'medium');
			$targetDefaultOptions = Wb\arrayGet($configuredTarget, 'defaultOptions', []);
			$targetOptions        = Wb\arrayGet($configuredTarget, 'options', []);
			$targetOptions        = Wb\arrayEnsure($targetOptions);
			$options              = array_merge(
				$targetDefaultOptions,
				$targetOptions
			);

			switch ($targetMedium)
			{
				case self::EMAIL:
				case self::SLACK:
				case self::TEAMS:
				case self::MATTERMOST:
					$methodName = 'notify' . ucfirst(strtolower($targetMedium));
					if (
						is_callable(
							[
								$this, $methodName
							]
						)
					)
					{
						$this->$methodName(
							$notification,
							$options
						);
					}
					break;
				default:
					throw new \Exception('wbLib notification: Unknown destination type: ' . print_r($targetMedium, true));
			}
		}
	}

	/**
	 * @param   array  $notification
	 * @param   array  $options
	 *
	 * @return void
	 */
	private function notifyEmail($notification, $options)
	{
		$emailData      = $this->formatEmail($notification);
		$addresses      = Wb\arrayGet($options, 'addresses', []);
		$platformConfig = $this->platform->getConfig();
		$mailer         = $this->platform->getMailer();
		$firstRecipient = array_shift($addresses);
		$sent           = $mailer->sendMail(
			$platformConfig->get('mailfrom'),
			$platformConfig->get('fromname'),
			$firstRecipient,
			$emailData['title'],
			$emailData['message'],
			true,        //mode
			$addresses,  // $cc
			null,        // $bcc
			null,        // $attachment
			null,        // $replyTo
			null         // $replyToName
		);

		if (empty($sent))
		{
			System\Log::libraryError(
				'Failed to send email notifications to: ' . print_r(Wb\arrayGet($options, 'addresses', []), true) . "\nNotification: " . Wb\arrayGet($notification, 'title', '')
			);
		}
	}

	private function notifySlack($notification, $options)
	{
		$this->postWebhook(
			Wb\arrayGet($options, 'webhook'),
			json_encode(
				[
					'blocks' => [
						[
							'type' => 'section',
							'text' => [
								'type' => 'mrkdwn',
								'text' => $this->getSubjectLine($notification)
							],
						],
						[
							'type' => 'header',
							'text' => [
								'type' => 'plain_text',
								'text' => Wb\arrayGet($notification, 'title')
							]
						],
						[
							'type' => 'section',
							'text' => [
								'type' => 'mrkdwn',
								'text' => Wb\arrayGet($notification, 'message')
							],
						],
						[
							"type" => "divider"
						],
						[
							'type'     => 'context',
							'elements' => [
								'text' => [
									'type' => 'plain_text',
									'text' => $this->footer()
								]
							]
						]
					]
				]
			)
		);
	}

	private function notifyTeams($notification, $options)
	{
		$this->postWebhook(
			Wb\arrayGet($options, 'webhook'),
			json_encode(
				[
					'@type'    => 'MessageCard',
					'@context' => 'http://schema.org/extensions',
					'title'    => Wb\arrayGet($notification, 'title'),
					'text'     => Wb\arrayGet($notification, 'message'),
					'username' => Wb\arrayGet($this->config, 'username')
				]
			)
		);
	}

	private function notifyMattermost($notification, $options)
	{
		$this->postWebhook(
			Wb\arrayGet($options, 'webhook'),
			json_encode(
				[
					'text'     => $this->getSubjectLine($notification)
						. "\n"
						. '# ' . Wb\arrayGet($notification, 'title')
						. "\n"
						. Wb\arrayGet($notification, 'message')
						. "\n\n" . $this->footer(),
					'username' => Wb\arrayGet($this->config, 'username')
				]
			)
		);
	}

	private function footer()
	{
		return $this->platform->getSitename() . ' | ' . Wb\arrayGet($this->config, 'userName', 'wbLib bot') . ' | ' . $this->platform->getRootUrl(false);
	}

	private function postWebhook($url, $payload)
	{
		$client = $this->platform->getHttpClient(
			[
				'follow_location' => true,
				'timeout'         => 10,
				'userAgent'       => Wb\arrayGet($this->config, 'userAgent', 'wbLib')
			]
		);

		try
		{
			$client->post(
				$url,
				$payload,
				[
					'Content-Type' => 'application/json'
				]
			);
		}
		catch (\Exception $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Build email message, including converting markdown to html if needed.
	 *
	 * @param   array  $notification
	 *
	 * @return array
	 */
	private function formatEmail($notification)
	{
		$html    = [];
		$subject = $this->getSubjectLine($notification);

		$html[] = '<h1 style="font-size:32px;line-height:36px;color:#000000;font-weight:700;margin: 16px 0;text-align: center;">' . Wb\arrayGet($notification, 'title') . '</h1>';

		$message          = Wb\arrayGet($notification, 'message');
		$lines            = explode("\n", $message);
		$codeBlockStarted = false;
		$blockStarted     = false;
		$listStarted      = false;
		foreach ($lines as $line)
		{
			$trimmedLine      = trim($line);
			$isCodeBlockStart = Wb\startsWith($trimmedLine, '```');
			if (
				$codeBlockStarted
				&&
				!$isCodeBlockStart
			)
			{
				$html[] = $trimmedLine;
				continue;
			}

			if ($isCodeBlockStart)
			{
				$html[]           = $codeBlockStarted
					? '</pre>'
					: '<pre style="padding: 16px 8px;margin: 16px 0;background: #FFFFFF; border-radius: 4px;">';
				$codeBlockStarted = !$codeBlockStarted;
				continue;
			}

			$isListStart = Wb\startsWith($trimmedLine, '- ');
			if (
				$listStarted
				&&
				!$isListStart
			)
			{
				// close existing block
				$html[]      = '</ul>';
				$listStarted = false;
				// do not continue, that new line must be processed normally
			}

			if (
				$listStarted
				&&
				$isListStart
			)
			{
				// close existing block
				$html[] = '<li style="margin-left:16px">' . $this->mdToHtmlInline(Wb\lTrim($trimmedLine, '- ')) . '</li>';
				continue;
			}

			if (
				$isListStart
				&&
				!$listStarted
			)
			{
				// start new one
				$html[] = '<ul style="padding: 0;">';
				$html[] = '<li style="margin-left:16px">' . $this->mdToHtmlInline(Wb\lTrim($trimmedLine, '- ')) . '</li>';

				$listStarted = true;

				continue;
			}

			if (Wb\startsWith($trimmedLine, '#### '))
			{
				$html[] = '<h4>' . Wb\lTrim($trimmedLine, '#### ') . '</h4>';
				continue;
			}
			if (Wb\startsWith($trimmedLine, '### '))
			{
				$html[] = '<h3>' . Wb\lTrim($trimmedLine, '### ') . '</h3>';
				continue;
			}

			$isBlockStart = Wb\startsWith($trimmedLine, '## ');
			if ($isBlockStart)
			{
				if ($blockStarted)
				{
					// close existing block
					$html[] = '</div>';
				}

				// start new one
				$html[] = '<div class="block" style="background-color: #E7E7E7; color: #222222; border-radius: 8px; padding: 16px 24px; margin: 32px 0;">';
				$html[] = '<h2>' . Wb\lTrim($trimmedLine, '## ') . '</h2>';

				$blockStarted = true;

				continue;
			}
			if (Wb\startsWith($trimmedLine, '# '))
			{
				if ($blockStarted)
				{
					// close existing block
					$html[]       = '</div>';
					$blockStarted = false;
				}

				$html[] = '<h1 style="font-size:32px;line-height:36px;color:#000000;font-weight:700;margin: 24px 0;">' . Wb\lTrim($trimmedLine, '# ') . '</h1>';
				continue;
			}

			if (Wb\startsWith($trimmedLine, '> '))
			{
				$html[] = '<blockquote style="font-style: italic; padding: 16px 8px;margin: 16px 0;background: #EEEEEE;border: 1px solid #bbbbbb; border-radius: 4px;">'
					. $this->mdToHtmlInline(Wb\lTrim($trimmedLine, '> '))
					. '</blockquote>';
				continue;
			}

			$html[] = '<p>' . $this->mdToHtmlInline($trimmedLine) . '</p>';
		}

		if ($blockStarted)
		{
			// close last block
			$html[] = '</div>';
		}

		$html[] = '<div style="text-align: center;padding: 32px 0;"><a href="' . $this->platform->getRootUrl(false) . 'administrator/" style="padding: 16px 32px;color:#000000;background:#9FA0FF; border-radius: 4px;">Go to site admin</a></div>';
		$html[] = '<p>&nbsp;</p><hr><small>' . $this->footer() . '</small>';

		return [
			'title'   => $subject,
			'message' => $this->createDocument(
				implode("\n", $html),
				$subject
			)
		];
	}

	private function createDocument($content, $title)
	{
		$template = <<<TMPL
<!DOCTYPE html><html xml:lang="{{LANGUAGE}}" lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="X-UA-Compatible" content="IE=Edge">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark only">
<title>{{TITLE}}</title>
<!--[if mso]>
<noscript>
    <xml>
      <o:OfficeDocumentSettings>
        <o:AllowPNG/>
        <o:PixelsPerInch>96</o:PixelsPerInch>
      </o:OfficeDocumentSettings>
    </xml>
</noscript>
<![endif]-->
<style type="text/css">
body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { -ms-interpolation-mode: bicubic; }

/* RESET STYLES */
img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
table { border-collapse: collapse !important; }
body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }

/* iOS BLUE LINKS */
a[x-apple-data-detectors] {
color: inherit !important;
	text-decoration: none !important;
	font-size: inherit !important;
	font-family: inherit !important;
	font-weight: inherit !important;
	line-height: inherit !important;
}

/* GMAIL BLUE LINKS */
u + #body a {
color: inherit;
	text-decoration: none;
	font-size: inherit;
	font-family: inherit;
	font-weight: inherit;
	line-height: inherit;
}

/* SAMSUNG MAIL BLUE LINKS */
#MessageViewBody a {
	color: inherit;
	text-decoration: none;
	font-size: inherit;
	font-family: inherit;
	font-weight: inherit;
	line-height: inherit;
}

a { color: #222222;; font-weight: 400; text-decoration: underline; }
a:hover { color: #000000 !important; text-decoration: none !important; }

p {
  font-size: 14px; font-style: normal; font-weight: 400; line-height: 22px; color: #222222;
}

.center {text-align: center;}

.block {background-color: #E7E7E7; color: #222222; border-radius: 8px; padding: 16px 24px; margin: 16px 0;}

</style>
</head>
<body>
<div role="article" style="background-color: #D6D9CE; color: #222222; font-family: 'Avenir Next', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; font-size: 16px; font-weight: 400; line-height: 24px; margin: 0 auto; max-width: 600px; padding: 40px 20px 40px 20px;border-radius:8px;">

{{BODY}}

</div>
</body>
</html>
TMPL;

		return str_replace(
			['{{TITLE', '{{BODY}}', '{{LANGUAGE}}'],
			[$title, $content, $this->platform->getCurrentLanguageTag()],
			$template
		);
	}

	private function getSubjectLine($notification)
	{
		$priority = Wb\arrayGet(
			$notification,
			'priority',
			self::NORMAL
		);

		return $this->platform->tprintf(
			'WBLIB_NOTIFICATION_SUBJECT_LINE_' . strtoupper($priority),
			$this->platform->getSiteName()
		);
	}

	private function mdToHtmlInline($text)
	{
		$text = preg_replace(
			'~\*\*(.*)\*\*~',
			'<strong>$1</strong>',
			$text
		);

		$text = preg_replace(
			'~\*(.*)\*~',
			'<em>$1</em>',
			$text
		);

		return $text;
	}
}