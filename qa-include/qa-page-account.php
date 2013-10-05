<?php
	
/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-page-account.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for user account page


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

	require_once QA_INCLUDE_DIR.'qa-db-users.php';
	require_once QA_INCLUDE_DIR.'qa-app-format.php';
	require_once QA_INCLUDE_DIR.'qa-app-users.php';
	require_once QA_INCLUDE_DIR.'qa-db-selects.php';
	require_once QA_INCLUDE_DIR.'qa-util-image.php';
	
	
//	Check we're not using single-sign on integration, that we're logged in
	
	if (QA_FINAL_EXTERNAL_USERS)
		qa_fatal_error('User accounts are handled by external code');
	
	$userid=qa_get_logged_in_userid();
	
	if (!isset($userid))
		qa_redirect('login');
		

//	Get current information on user

	list($useraccount, $userprofile, $userpoints, $userfields)=qa_db_select_with_pending(
		qa_db_user_account_selectspec($userid, true),
		qa_db_user_profile_selectspec($userid, true),
		qa_db_user_points_selectspec($userid, true),
		qa_db_userfields_selectspec()
	);
	
	$changehandle=qa_opt('allow_change_usernames') || ((!$userpoints['qposts']) && (!$userpoints['aposts']) && (!$userpoints['cposts']));
	$doconfirms=qa_opt('confirm_user_emails') && ($useraccount['level']<QA_USER_LEVEL_EXPERT);
	$isconfirmed=($useraccount['flags'] & QA_USER_FLAGS_EMAIL_CONFIRMED) ? true : false;
	$haspassword=isset($useraccount['passsalt']) && isset($useraccount['passcheck']);

	
//	Process profile if saved

	if (qa_clicked('dosaveprofile')) {
		require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';
		
		$inhandle=$changehandle ? qa_post_text('handle') : $useraccount['handle'];
		$inemail=qa_post_text('email');
		$inmessages=qa_post_text('messages');
		$inwallposts=qa_post_text('wall');
		$inmailings=qa_post_text('mailings');
		$inavatar=qa_post_text('avatar');

		$inprofile=array();
		foreach ($userfields as $userfield)
			$inprofile[$userfield['fieldid']]=qa_post_text('field_'.$userfield['fieldid']);		
		
		if (!qa_check_form_security_code('account', qa_post_text('code')))
			$errors['page']=qa_html(_('Please click again to confirm'));
		
		else {
			$errors=qa_handle_email_filter($inhandle, $inemail, $useraccount);
	
			if (!isset($errors['handle']))
				qa_db_user_set($userid, 'handle', $inhandle);
	
			if (!isset($errors['email']))
				if ($inemail != $useraccount['email']) {
					qa_db_user_set($userid, 'email', $inemail);
					qa_db_user_set_flag($userid, QA_USER_FLAGS_EMAIL_CONFIRMED, false);
					$isconfirmed=false;
					
					if ($doconfirms)
						qa_send_new_confirm($userid);
				}
				
			if (qa_opt('allow_private_messages'))
				qa_db_user_set_flag($userid, QA_USER_FLAGS_NO_MESSAGES, !$inmessages);
			
			if (qa_opt('allow_user_walls'))
				qa_db_user_set_flag($userid, QA_USER_FLAGS_NO_WALL_POSTS, !$inwallposts);
			
			if (qa_opt('mailing_enabled'))
				qa_db_user_set_flag($userid, QA_USER_FLAGS_NO_MAILINGS, !$inmailings);
			
			qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_AVATAR, ($inavatar=='uploaded'));
			qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_GRAVATAR, ($inavatar=='gravatar'));
	
			if (is_array(@$_FILES['file']) && $_FILES['file']['size']) {
				require_once QA_INCLUDE_DIR.'qa-app-limits.php';
				
				switch (qa_user_permit_error(null, QA_LIMIT_UPLOADS))
				{
					case 'limit':
						$errors['avatar']=_('Too many uploads - please try again in an hour');
						break;
					
					default:
						$errors['avatar']=_('You do not have permission to perform this operation');
						break;
						
					case false:
						qa_limits_increment($userid, QA_LIMIT_UPLOADS);
						
						$toobig=qa_image_file_too_big($_FILES['file']['tmp_name'], qa_opt('avatar_store_size'));
						
						if ($toobig)
							$errors['avatar']=sprintf(_('This image is too big. Please scale to %d%% then try again.'), (int)($toobig*100));
						elseif (!qa_set_user_avatar($userid, file_get_contents($_FILES['file']['tmp_name']), $useraccount['avatarblobid']))
							$errors['avatar']=sprintf(_('The image could not be read. Please upload one of: %s'), implode(', ', qa_gd_image_formats()));
						break;
				}
			}
	
			$filtermodules=qa_load_modules_with('filter', 'filter_profile');
			foreach ($filtermodules as $filtermodule)
				$filtermodule->filter_profile($inprofile, $errors, $useraccount, $userprofile);
		
			foreach ($userfields as $userfield)
				if (!isset($errors[$userfield['fieldid']]))
					qa_db_user_profile_set($userid, $userfield['title'], $inprofile[$userfield['fieldid']]);
			
			list($useraccount, $userprofile)=qa_db_select_with_pending(
				qa_db_user_account_selectspec($userid, true),
				qa_db_user_profile_selectspec($userid, true)
			);
	
			qa_report_event('u_save', $userid, $useraccount['handle'], qa_cookie_get());
			
			if (empty($errors))
				qa_redirect('account', array('state' => 'profile-saved'));
	
			qa_logged_in_user_flush();
		}
	}


//	Process change password if clicked

	if (qa_clicked('dochangepassword')) {
		require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';
		
		$inoldpassword=qa_post_text('oldpassword');
		$innewpassword1=qa_post_text('newpassword1');
		$innewpassword2=qa_post_text('newpassword2');
		
		if (!qa_check_form_security_code('password', qa_post_text('code')))
			$errors['page']=qa_html(_('Please click again to confirm'));
		
		else {
			$errors=array();
			
			if ($haspassword && (strtolower(qa_db_calc_passcheck($inoldpassword, $useraccount['passsalt'])) != strtolower($useraccount['passcheck'])))
				$errors['oldpassword']=_('Password not correct');
			
			$useraccount['password']=$inoldpassword;
			$errors=$errors+qa_password_validate($innewpassword1, $useraccount); // array union
	
			if ($innewpassword1 != $innewpassword2)
				$errors['newpassword2']=_('New passwords do not match');
				
			if (empty($errors)) {
				qa_db_user_set_password($userid, $innewpassword1);
				qa_db_user_set($userid, 'sessioncode', ''); // stop old 'Remember me' style logins from still working
				qa_set_logged_in_user($userid, $useraccount['handle'], false, $useraccount['sessionsource']); // reinstate this specific session
	
				qa_report_event('u_password', $userid, $useraccount['handle'], qa_cookie_get());
			
				qa_redirect('account', array('state' => 'password-changed'));
			}
		}
	}


//	Prepare content for theme

	$qa_content=qa_content_prepare();

	$qa_content['title']=qa_html(_('My account details'));	
	$qa_content['error']=@$errors['page'];
	
	$qa_content['form_profile']=array(
		'tags' => 'enctype="multipart/form-data" method="post" action="'.qa_self_html().'"',
		
		'style' => 'wide',
		
		'fields' => array(
			'duration' => array(
				'type' => 'static',
				'label' => qa_html(_('Member for:')),
				'value' => qa_time_to_string(qa_opt('db_time')-$useraccount['created']),
			),
			
			'type' => array(
				'type' => 'static',
				'label' => qa_html(_('Type:')),
				'value' => qa_html(qa_user_level_string($useraccount['level'])),
			),
			
			'handle' => array(
				'label' => qa_html(_('Username:')),
				'tags' => 'name="handle"',
				'value' => qa_html(isset($inhandle) ? $inhandle : $useraccount['handle']),
				'error' => qa_html(@$errors['handle']),
				'type' => $changehandle ? 'text' : 'static',
			),
			
			'email' => array(
				'label' => qa_html(_('Email:')),
				'tags' => 'name="email"',
				'value' => qa_html(isset($inemail) ? $inemail : $useraccount['email']),
				'error' => isset($errors['email']) ? qa_html($errors['email']) :
					(($doconfirms && !$isconfirmed) ? qa_insert_login_links(qa_html(_('Please ^5confirm^6'))) : null),
			),
			
			'messages' => array(
				'label' => qa_html(_('Private messages:')),
				'tags' => 'name="messages"',
				'type' => 'checkbox',
				'value' => !($useraccount['flags'] & QA_USER_FLAGS_NO_MESSAGES),
				'note' => qa_html(_('Allow users to email you (without seeing your address)')),
			),
			
			'wall' => array(
				'label' => qa_html(_('Wall posts:')),
				'tags' => 'name="wall"',
				'type' => 'checkbox',
				'value' => !($useraccount['flags'] & QA_USER_FLAGS_NO_WALL_POSTS),
				'note' => qa_html(_('Allow users to post on your wall (you will also be emailed)')),
			),
			
			'mailings' => array(
				'label' => qa_html(_('Mass mailings:')),
				'tags' => 'name="mailings"',
				'type' => 'checkbox',
				'value' => !($useraccount['flags'] & QA_USER_FLAGS_NO_MAILINGS),
				'note' => qa_html(_('Subscribe to emails sent out to all users')),
			),
			
			'avatar' => null, // for positioning
		),
		
		'buttons' => array(
			'save' => array(
				'tags' => 'onclick="qa_show_waiting_after(this, false);"',
				'label' => qa_html(_('Save Profile')),
			),
		),
		
		'hidden' => array(
			'dosaveprofile' => '1',
			'code' => qa_get_form_security_code('account'),
		),
	);
	
	if (qa_get_state()=='profile-saved')
		$qa_content['form_profile']['ok']=qa_html(_('Profile saved'));
	
	if (!qa_opt('allow_private_messages'))
		unset($qa_content['form_profile']['fields']['messages']);
		
	if (!qa_opt('allow_user_walls'))
		unset($qa_content['form_profile']['fields']['wall']);
		
	if (!qa_opt('mailing_enabled'))
		unset($qa_content['form_profile']['fields']['mailings']);
		

//	Avatar upload stuff

	if (qa_opt('avatar_allow_gravatar') || qa_opt('avatar_allow_upload')) {
		$avataroptions=array();
		
		if (qa_opt('avatar_default_show') && strlen(qa_opt('avatar_default_blobid'))) {
			$avataroptions['']='<span style="margin:2px 0; display:inline-block;">'.
				qa_get_avatar_blob_html(qa_opt('avatar_default_blobid'), qa_opt('avatar_default_width'), qa_opt('avatar_default_height'), 32).
				'</span> '.qa_html(_('Default'));
		} else
			$avataroptions['']=qa_html(_('None'));

		$avatarvalue=$avataroptions[''];
	
		if (qa_opt('avatar_allow_gravatar')) {
			$avataroptions['gravatar']='<span style="margin:2px 0; display:inline-block;">'.
				qa_get_gravatar_html($useraccount['email'], 32).' '.sprintf(qa_html(_('Show my %sGravatar%s')),
					'<a href="http://www.gravatar.com/" target="_blank">',
					'</a>'
				).'</span>';

			if ($useraccount['flags'] & QA_USER_FLAGS_SHOW_GRAVATAR)
				$avatarvalue=$avataroptions['gravatar'];
		}

		if (qa_has_gd_image() && qa_opt('avatar_allow_upload')) {
			$avataroptions['uploaded']='<input name="file" type="file">';

			if (isset($useraccount['avatarblobid']))
				$avataroptions['uploaded']='<span style="margin:2px 0; display:inline-block;">'.
					qa_get_avatar_blob_html($useraccount['avatarblobid'], $useraccount['avatarwidth'], $useraccount['avatarheight'], 32).
					'</span>'.$avataroptions['uploaded'];

			if ($useraccount['flags'] & QA_USER_FLAGS_SHOW_AVATAR)
				$avatarvalue=$avataroptions['uploaded'];
		}
		
		$qa_content['form_profile']['fields']['avatar']=array(
			'type' => 'select-radio',
			'label' => qa_html(_('Avatar:')),
			'tags' => 'name="avatar"',
			'options' => $avataroptions,
			'value' => $avatarvalue,
			'error' => qa_html(@$errors['avatar']),
		);
		
	} else
		unset($qa_content['form_profile']['fields']['avatar']);


//	Other profile fields

	foreach ($userfields as $userfield) {
		$value=@$inprofile[$userfield['fieldid']];
		if (!isset($value))
			$value=@$userprofile[$userfield['title']];
			
		$label=trim(qa_user_userfield_label($userfield), ':');
		if (strlen($label))
			$label.=':';
			
		$qa_content['form_profile']['fields'][$userfield['title']]=array(
			'label' => qa_html($label),
			'tags' => 'name="field_'.$userfield['fieldid'].'"',
			'value' => qa_html($value),
			'error' => qa_html(@$errors[$userfield['fieldid']]),
			'rows' => ($userfield['flags'] & QA_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
		);
	}
	
	
//	Raw information for plugin layers to access

	$qa_content['raw']['account']=$useraccount;
	$qa_content['raw']['profile']=$userprofile;
	$qa_content['raw']['points']=$userpoints;
	

//	Change password form

	$qa_content['form_password']=array(
		'tags' => 'method="post" action="'.qa_self_html().'"',
		
		'style' => 'wide',
		
		'title' => qa_html(_('Change Password')),
		
		'fields' => array(
			'old' => array(
				'label' => qa_html(_('Old password:')),
				'tags' => 'name="oldpassword"',
				'value' => qa_html(@$inoldpassword),
				'type' => 'password',
				'error' => qa_html(@$errors['oldpassword']),
			),
		
			'new_1' => array(
				'label' => qa_html(_('New password:')),
				'tags' => 'name="newpassword1"',
				'type' => 'password',
				'error' => qa_html(@$errors['password']),
			),

			'new_2' => array(
				'label' => qa_html(_('Retype new password:')),
				'tags' => 'name="newpassword2"',
				'type' => 'password',
				'error' => qa_html(@$errors['newpassword2']),
			),
		),
		
		'buttons' => array(
			'change' => array(
				'label' => qa_html(_('Change Password')),
			),
		),
		
		'hidden' => array(
			'dochangepassword' => '1',
			'code' => qa_get_form_security_code('password'),
		),
	);
	
	if (!$haspassword) {
		$qa_content['form_password']['fields']['old']['type']='static';
		$qa_content['form_password']['fields']['old']['value']=qa_html(_('None. To log in directly, set a password below.'));
	}
	
	if (qa_get_state()=='password-changed')
		$qa_content['form_profile']['ok']=qa_html(_('Password changed'));
		

	$qa_content['navigation']['sub']=qa_account_sub_navigation();
		
		
	return $qa_content;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/
