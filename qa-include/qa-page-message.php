<?php
	
/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-page-message.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for private messaging page


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once QA_INCLUDE_DIR.'qa-db-selects.php';
	require_once QA_INCLUDE_DIR.'qa-app-users.php';
	require_once QA_INCLUDE_DIR.'qa-app-format.php';
	require_once QA_INCLUDE_DIR.'qa-app-limits.php';
	
	$handle=qa_request_part(1);
	$loginuserid=qa_get_logged_in_userid();


//	Check we have a handle, we're not using Q2A's single-sign on integration and that we're logged in

	if (QA_FINAL_EXTERNAL_USERS)
		qa_fatal_error('User accounts are handled by external code');
	
	if (!strlen($handle))
		qa_redirect('users');

	if (!isset($loginuserid)) {
		$qa_content=qa_content_prepare();
		$qa_content['error']=qa_insert_login_links(qa_html(_('Please ^1log in^2 or ^3register^4 to send private messages.')), qa_request());
		return $qa_content;
	}


//	Find the user profile and questions and answers for this handle
	
	list($toaccount, $torecent, $fromrecent)=qa_db_select_with_pending(
		qa_db_user_account_selectspec($handle, false),
		qa_db_recent_messages_selectspec($loginuserid, true, $handle, false),
		qa_db_recent_messages_selectspec($handle, false, $loginuserid, true)
	);


//	Check the user exists and work out what can and can't be set (if not using single sign-on)
	
	if ( (!qa_opt('allow_private_messages')) || (!is_array($toaccount)) || ($toaccount['flags'] & QA_USER_FLAGS_NO_MESSAGES) )
		return include QA_INCLUDE_DIR.'qa-page-not-found.php';
	

//	Check that we have permission and haven't reached the limit

	$errorhtml=null;
	
	switch (qa_user_permit_error(null, QA_LIMIT_MESSAGES)) {
		case 'limit':
			$errorhtml=qa_html(_('You cannot send more private messages this hour'));
			break;
			
		case false:
			break;
			
		default:
			$errorhtml=qa_html(_('You do not have permission to perform this operation'));
			break;
	}

	if (isset($errorhtml)) {
		$qa_content=qa_content_prepare();
		$qa_content['error']=$errorhtml;
		return $qa_content;
	}


//	Process sending a message to user

	$messagesent=(qa_get_state()=='message-sent');
	
	if (qa_post_text('domessage')) {
		$inmessage=qa_post_text('message');
		
		if (!qa_check_form_security_code('message-'.$handle, qa_post_text('code')))
			$pageerror=qa_html(_('Please click again to confirm'));
			
		else {
			if (empty($inmessage))
				$errors['message']=_('Please enter your message to send to this user');
			
			if (empty($errors)) {
				require_once QA_INCLUDE_DIR.'qa-db-messages.php';
				require_once QA_INCLUDE_DIR.'qa-app-emails.php';
	
				if (qa_opt('show_message_history'))
					$messageid=qa_db_message_create($loginuserid, $toaccount['userid'], $inmessage, '', false);
				else
					$messageid=null;
	
				$fromhandle=qa_get_logged_in_handle();
				$canreply=!(qa_get_logged_in_flags() & QA_USER_FLAGS_NO_MESSAGES);
				
				$more=sprintf(($canreply ? _("Click below to reply to %s by private message:\n\n%s\n\n") : _("More information about %s:\n\n%s\n\n")),
					$fromhandle,
					qa_path_absolute($canreply ? ('message/'.$fromhandle) : ('user/'.$fromhandle))
				);
	
				$subs=array(
					'^message' => $inmessage,
					'^f_handle' => $fromhandle,
					'^f_url' => qa_path_absolute('user/'.$fromhandle),
					'^more' => $more,
					'^a_url' => qa_path_absolute('account'),
				);
				
				if (qa_send_notification($toaccount['userid'], $toaccount['email'], $toaccount['handle'],
						_('Message from ^f_handle on ^site_title'), _("You have been sent a private message by ^f_handle on ^site_title:\n\n^open^message^close\n\n^moreThank you,\n\n^site_title\n\n\nTo block private messages, visit your account page:\n^a_url"), $subs))
					$messagesent=true;
				else
					$pageerror=qa_html(_('A server error occurred - please try again.'));
	
				qa_report_event('u_message', $loginuserid, qa_get_logged_in_handle(), qa_cookie_get(), array(
					'userid' => $toaccount['userid'],
					'handle' => $toaccount['handle'],
					'messageid' => $messageid,
					'message' => $inmessage,
				));
				
				if ($messagesent && qa_opt('show_message_history')) // show message as part of general history
					qa_redirect(qa_request(), array('state' => 'message-sent'));
			}
		}
	}


//	Prepare content for theme
	
	$qa_content=qa_content_prepare();
	
	$qa_content['title']=qa_html(_('Send a private message'));

	$qa_content['error']=@$pageerror;

	$qa_content['form_message']=array(
		'tags' => 'method="post" action="'.qa_self_html().'"',
		
		'style' => 'tall',
		
		'fields' => array(
			'message' => array(
				'type' => $messagesent ? 'static' : '',
				'label' => sprintf(qa_html(_('Your message for %s:')), qa_get_one_user_html($handle, false)),
				'tags' => 'name="message" id="message"',
				'value' => qa_html(@$inmessage, $messagesent),
				'rows' => 8,
				'note' => qa_html(sprintf(_('This will be sent as a notification from %s. Your email address will not be revealed unless you include it in the message.'), qa_opt('site_title'))),
				'error' => qa_html(@$errors['message']),
			),
		),
		
		'buttons' => array(
			'send' => array(
				'tags' => 'onclick="qa_show_waiting_after(this, false);"',
				'label' => qa_html(_('Send')),
			),
		),
		
		'hidden' => array(
			'domessage' => '1',
			'code' => qa_get_form_security_code('message-'.$handle),
		),
	);
	
	$qa_content['focusid']='message';

	if ($messagesent) {
		$qa_content['form_message']['ok']=qa_html(_('Your private message below was sent'));
		unset($qa_content['form_message']['buttons']);

		if (qa_opt('show_message_history'))
			unset($qa_content['form_message']['fields']['message']);
		else {
			unset($qa_content['form_message']['fields']['message']['note']);
			unset($qa_content['form_message']['fields']['message']['label']);
		}
	}
	

//	If relevant, show recent message history

	if (qa_opt('show_message_history')) {
		$recent=array_merge($torecent, $fromrecent);
		
		qa_sort_by($recent, 'created');
		
		$showmessages=array_slice(array_reverse($recent, true), 0, QA_DB_RETRIEVE_MESSAGES);
		
		if (count($showmessages)) {
			$qa_content['message_list']=array(
				'title' => qa_html(sprintf(_('Recent correspondence with %s'), $toaccount['handle'])),
			);
			
			$options=qa_message_html_defaults();
			
			foreach ($showmessages as $message)
				$qa_content['message_list']['messages'][]=qa_message_html_fields($message, $options);
		}
	}


	$qa_content['raw']['account']=$toaccount; // for plugin layers to access
	

	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/
