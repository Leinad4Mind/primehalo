<?php
namespace primehalo\primeloginlogoutreturn\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\template\template;
use phpbb\user;
class listener implements EventSubscriberInterface
{
	private $enable_login			= true;				// Enable this MOD for logging in?

	private $enable_logout			= true;				// Enable this MOD for logging out?

	// Variables
	protected $template;

	protected $user;

	protected $board_url;

	public function __construct(
		template $template,
		user $user
	)
	{
		$this->template = $template;
		$this->user = $user;
		$this->board_url = generate_board_url(true);
		$this->board_url = utf8_case_fold_nfc($this->board_url);
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.page_header_after'	=> 'page_header_after',
			'core.functions.redirect'	=> 'redirection',
		);
	}

	/**
	* Update the login/logout link to include a  query string redirect variable.
	*/
	public function page_header_after($event)
	{
		global $phpbb_root_path, $phpEx;
		if ($this->user->page['page_name'] == "ucp.$phpEx" || $this->user->page['page_dir'])
		{
			return;
		}
		if ($this->user->data['user_id'] != ANONYMOUS)
		{
			if (!$this->enable_logout)
			{
				return;
			}
			$u_login_logout = append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=logout', true, $this->user->session_id);
		}
		else
		{
			if (!$this->enable_login)
			{
				return;
			}
			$u_login_logout = append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=login');
		}
		$redirect = 'redirect=' . urlencode(str_replace('&amp;', '&', build_url(array('_f_'))));
		$seperator = strpos($u_login_logout, '?') === false ? '?' : '&amp;';
		$u_login_logout .= $seperator . $redirect;
		$this->template->assign_var('U_LOGIN_LOGOUT', $u_login_logout);
	}

	/**
	* On a redirect check to see if this is a logout. Login automatically
	* redirects if there's a redirect query var, so this is just for logout.
	*/
	public function redirection($event)
	{
		global $request;
		$mode	= $request->variable('mode', '');
		$redirect = $request->variable('redirect', '');
		$redirect = str_replace('&amp;', '&', $redirect);
		if ($mode === 'logout' && $redirect)
		{
			$event['url'] = $redirect;
		}
	}
}
