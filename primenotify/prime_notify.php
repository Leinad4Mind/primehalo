<?php
/**
*
* @package phpBB3
* @version $Id: prime_notify.php,v 1.0.10 2016/08/19 11:35:00 primehalo Exp $
* @copyright (c) 2007-2016 Ken F. Innes IV
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/*
* Include only once.
*/
global $prime_notify;
if (!class_exists('prime_notify'))
{
	/**
	* Options
	*/
	define('PRIME_NOTIFY_POST_ENABLED', true);	// Insert the post message into the notification e-mail?
	define('PRIME_NOTIFY_PM_ENABLED', true);	// Insert the private message into the notification e-mail?
	define('PRIME_NOTIFY_BBCODES', true);		// Keep BBCodes (helps show how the message is supposed to be formatted)?
	define('PRIME_NOTIFY_ALWAYS', true);		// Notify user even if they've already received a previous notification and have not yet visited the forum to read it?

	/**
	* Class declaration
	*/
	class prime_notify
	{
		//var $enabled = false;
		var $message = '';
		var $visit_msg = array();
		//var $verified_paths = array();

		/**
		* Constructor
		*/
		function prime_notify()
		{
			$this->message = '';
			$this->visit_msg = array();
			//$this->verified_paths = array();
		}

		/**
		* Format the message for e-mail
		*/
		private function format_message(&$text, $uid_param = '', $keep_bbcodes = true)
		{
			global $user;

			$uid = $uid_param ? $uid_param : '[0-9a-z]{5,}';

			// If there is a spoiler, remove the spoiler content.
			$search = '@\[spoiler(?:=[^]]*)?:' . $uid . '\](.*?)\[/spoiler:' . $uid . '\]@s';
			$replace = '[spoiler](' . $user->lang['NA'] . ')[/spoiler]';
			$text = preg_replace($search, $replace, $text);

			if ($keep_bbcodes)
			{
				// Strip unique ids out of BBCodes
				$text = preg_replace("#\[(\/?[a-z0-9\*\+\-]+(?:=.*?)?(?::[a-z])?)(\:?$uid)\]#", '[\1]', $text);

				// If there is a URL between BBCode URL tags, then add spacing so
				// the email program won't think the BBCode is part of the URL.
				$text = preg_replace('@](http(?::|&#58;)//.*?)\[@', '] $1 [', $text);
			}
			else
			{
				// Change quotes
				$text = preg_replace('@\[quote=(?:"|&quot;)([^"]*)(?:"|&quot;):' . $uid . '\]@', "[quote=\"$1\"]", $text);
				$text = preg_replace('@\[code=([a-z]+):' . $uid . '\]@', "[code=$1]", $text);
				$text = preg_replace('@\[(/)?(quote|code):' . $uid . '\]@', "[$1$2]", $text);

				// Change lists (quick & dirty, no checking if we're actually in a list, much less if it's ordered or unordered)
				$text = str_replace('[*]', '* ', $text);
				$text = $uid_param ? str_replace('[*:' . $uid . ']', '* ', $text) : preg_replace('/\[\*:' . $uid . ']/', '* ', $text);

				// Change [url=http://www.example.com]Example[/url] to Example (http://www.example.com)
				$text = preg_replace('@\[url=([^]]*):' . $uid . '\]([^[]*)\[/url:' . $uid . '\]@', '$2 ($1)', $text);

				// Remove all remaining BBCodes
				//strip_bbcode($text, $uid_param); // This function replaces BBCodes with spaces, which we don't want
				$text = preg_replace("#\[\/?[a-z0-9\*\+\-]+(?:=(?:&quot;.*&quot;|[^\]]*))?(?::[a-z])?(\:$uid)\]#", '', $text);
				$match = get_preg_expression('bbcode_htm');
				$replace = array('\1', '\1', '\2', '\1', '', '');
				$text = preg_replace($match, $replace, $text);
			}

			// Change HTML smiley images to text smilies
			$text = preg_replace('#<!-- s[^ >]* --><img src="[^"]*" alt="([^"]*)" title="[^"]*" /><!-- s[^ >]* -->#', ' $1 ', $text);

			// Change HTML links to text links
			$text = preg_replace('#<!-- [lmw] --><a .*?href="([^"]*)"[^>]*>.*?</a><!-- [lmw] -->#', '$1', $text);

			// Change HTML e-mail links to text links
			$text = preg_replace('#<!-- e --><a .*?href="[^"]*"[^>]*>(.*?)</a><!-- e -->#', '$1', $text);

			// Transform special BBCode characters into human-readable characters
			$transform = array('&lt;' => '<', '&gt;' => '>', '&#91;' => '[', '&#93;' => ']', '&#46;' => '.', '&#58;' => ':');
			$text = str_replace(array_keys($transform), array_values($transform), $text);

			// Remove backslashes that appear directly before single quotes
			$text = stripslashes(trim($text));
		}

		/**
		* Should we always notify even if they've already received a notification and haven't yet read the new post?
		*/
		public function should_always_notify()
		{
			return PRIME_NOTIFY_POST_ENABLED && PRIME_NOTIFY_ALWAYS;
		}

		/**
		* Get processed text
		*/
		function get_processed_text($data)
		{
			if (!empty($this->message))
			{
				return $this->message;
			}

			$this->message = '';
			if (isset($data['post_text']))
			{
				$this->message = $data['post_text'];
			}
			else if (isset($data['message']))
			{
				$this->message = $data['message'];
			}
			if (!empty($this->message))
			{
				// If BBCodes are not enabled for this post, then we keep them because they do not represent formatting
				$enable_bbcode = $data['enable_bbcode'];
				$keep_bbcodes = empty($enable_bbcode) ? true : PRIME_NOTIFY_BBCODES;

				// Format the message
				$uid = !empty($data['bbcode_uid']) ? $data['bbcode_uid'] : '';
				$this->format_message($this->message, $uid, $keep_bbcodes);
			}
			return $this->message;
		}

		/**
		*/
		function template($messenger, $notification, $user, $template_dir_prefix = '')
		{
			$template = $notification->get_email_template();
			$enabled = false;
			switch ($template)
			{
				case 'forum_notify':
					$enabled = PRIME_NOTIFY_POST_ENABLED;
					$msg_type = 'PRIME_NOTIFY_FORUM_VISIT_MSG';
					break;
				case 'newtopic_notify':
				case 'topic_notify':
					$enabled = PRIME_NOTIFY_POST_ENABLED;
					$msg_type = 'PRIME_NOTIFY_TOPIC_VISIT_MSG';
					break;
				case 'privmsg_notify':
					$enabled = PRIME_NOTIFY_PM_ENABLED;
					break;
			}
			$lang_path = $enabled ? $this->lang_path($user) : false;

			// Use the default template
			if (!$enabled || !$lang_path || empty($this->message) || !empty($template_dir_prefix))
			{
				$messenger->template($template_dir_prefix . $template, $user['user_lang']);
				return;
			}

			// Setup our the template
			$messenger->template($template_dir_prefix . $template, $user['user_lang'], $lang_path . 'email');

			// Assign extra template variables
			if (!PRIME_NOTIFY_ALWAYS)
			{
				$visit_msg = '';
				if (!empty($msg_type))
				{
					if (!isset($this->visit_msg[$lang_path][$msg_type]))
					{
						global $phpEx;
						@include("{$lang_path}prime_notify.$phpEx");
						$this->visit_msg[$lang_path][$msg_type] = isset($lang[$msg_type]) ? $lang[$msg_type] : '';
					}
					$visit_msg = $this->visit_msg[$lang_path][$msg_type];
				}
				$messenger->assign_vars(array(
					'VISIT_MSG'	=> htmlspecialchars_decode($visit_msg),
				));
			}
		}

		/**
		*/
		function lang_path($user)
		{
			global $config, $phpbb_root_path;

			if (!empty($this->message))
			{
				$template_lang = $user['user_lang'];
				$path = "{$phpbb_root_path}ext/primehalo/primenotify/language/{$template_lang}/";
				if (!empty($this->verified_path[$path]) || is_dir($path))
				{
					$this->verified_path[$path] = true;
					return $path;
				}
				$alt_path = '{$phpbb_root_path}ext/primehalo/primenotify/language/' . basename($config['default_lang']) . '/';
				if (!empty($this->verified_path[$alt_path]) || ($alt_path !== $path && is_dir($alt_path)))
				{
					$this->verified_path[$alt_path] = true;
					return $alt_path;
				}
			}
			return false;
		}

		/**
		* Alter the SQL statement to fit our needs
		*/
		function alter_post_sql(&$sql)
		{
			if ($this->should_always_notify())
			{
				// Always notify, so don't check if a notification was already sent
				$sql = str_replace('AND notify_status = ' . NOTIFY_YES, '', $sql);

				// Check for user's choice
				/*
				if (PRIME_NOTIFY_USER_CHOICE)
				{
					$sql = substr_replace($sql, ', u.user_notify_content ', strpos($sql, 'FROM'), 0);
				}
				*/
			}
		}
	}
	// End class

	$prime_notify = new prime_notify();
}
