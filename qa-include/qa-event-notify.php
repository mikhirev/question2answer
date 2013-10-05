<?php

/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-event-notify.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Event module for sending notification emails


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


	class qa_event_notify {

		function process_event($event, $userid, $handle, $cookieid, $params)
		{
			require_once QA_INCLUDE_DIR.'qa-app-emails.php';
			require_once QA_INCLUDE_DIR.'qa-app-format.php';
			require_once QA_INCLUDE_DIR.'qa-util-string.php';

			
			switch ($event) {
				case 'q_post':
					$followanswer=@$params['followanswer'];
					$sendhandle=isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] : _('anonymous'));
					
					if (isset($followanswer['notify']) && !qa_post_is_by_user($followanswer, $userid, $cookieid)) {
						$blockwordspreg=qa_get_block_words_preg();
						$sendtext=qa_viewer_text($followanswer['content'], $followanswer['format'], array('blockwordspreg' => $blockwordspreg));
						
						qa_send_notification($followanswer['userid'], $followanswer['notify'], @$followanswer['handle'], _('Your ^site_title answer has a related question'), _("Your answer on ^site_title has a new related question by ^q_handle:\n\n^open^q_title^close\n\nYour answer was:\n\n^open^a_content^close\n\nClick below to answer the new question:\n\n^url\n\nThank you,\n\n^site_title"), array(
							'^q_handle' => $sendhandle,
							'^q_title' => qa_block_words_replace($params['title'], $blockwordspreg),
							'^a_content' => $sendtext,
							'^url' => qa_q_path($params['postid'], $params['title'], true),
						));
					}
					
					if (qa_opt('notify_admin_q_post'))
						qa_send_notification(null, qa_opt('feedback_email'), null, _('^site_title has a new question'), _("A new question has been asked by ^q_handle:\n\n^open^q_title\n\n^q_content^close\n\nClick below to see the question:\n\n^url\n\nThank you,\n\n^site_title"), array(
							'^q_handle' => $sendhandle,
							'^q_title' => $params['title'], // don't censor title or content here since we want the admin to see bad words
							'^q_content' => $params['text'],
							'^url' => qa_q_path($params['postid'], $params['title'], true),
						));

					break;

					
				case 'a_post':
					$question=$params['parent'];
					
					if (isset($question['notify']) && !qa_post_is_by_user($question, $userid, $cookieid))
						qa_send_notification($question['userid'], $question['notify'], @$question['handle'], _('Your ^site_title question was answered'), _("Your question on ^site_title has been answered by ^a_handle:\n\n^open^a_content^close\n\nYour question was:\n\n^open^q_title^close\n\nIf you like this answer, you may select it as the best:\n\n^url\n\nThank you,\n\n^site_title"), array(
							'^a_handle' => isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] : _('anonymous')),
							'^q_title' => $question['title'],
							'^a_content' => qa_block_words_replace($params['text'], qa_get_block_words_preg()),
							'^url' => qa_q_path($question['postid'], $question['title'], true, 'A', $params['postid']),
						));
					break;

					
				case 'c_post':
					$parent=$params['parent'];
					$question=$params['question'];
					
					$senttoemail=array(); // to ensure each user or email gets only one notification about an added comment
					$senttouserid=array();
					
					switch ($parent['basetype']) {
						case 'Q':
							$subject=_('Your ^site_title question has a new comment');
							$body=_("Your question on ^site_title has a new comment by ^c_handle:\n\n^open^c_content^close\n\nYour question was:\n\n^open^c_context^close\n\nYou may respond by adding your own comment:\n\n^url\n\nThank you,\n\n^site_title");
							$context=$parent['title'];
							break;
							
						case 'A':
							$subject=_('Your ^site_title answer has a new comment');
							$body=_("Your answer on ^site_title has a new comment by ^c_handle:\n\n^open^c_content^close\n\nYour answer was:\n\n^open^c_context^close\n\nYou may respond by adding your own comment:\n\n^url\n\nThank you,\n\n^site_title");
							$context=qa_viewer_text($parent['content'], $parent['format']);
							break;
					}
					
					$blockwordspreg=qa_get_block_words_preg();
					$sendhandle=isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] : _('anonymous'));
					$sendcontext=qa_block_words_replace($context, $blockwordspreg);
					$sendtext=qa_block_words_replace($params['text'], $blockwordspreg);
					$sendurl=qa_q_path($question['postid'], $question['title'], true, $parent['basetype'], $parent['postid']);
						
					if (isset($parent['notify']) && !qa_post_is_by_user($parent, $userid, $cookieid)) {
						$senduserid=$parent['userid'];
						$sendemail=@$parent['notify'];
						
						if (qa_email_validate($sendemail))
							$senttoemail[$sendemail]=true;
						elseif (isset($senduserid))
							$senttouserid[$senduserid]=true;
			
						qa_send_notification($senduserid, $sendemail, @$parent['handle'], $subject, $body, array(
							'^c_handle' => $sendhandle,
							'^c_context' => $sendcontext,
							'^c_content' => $sendtext,
							'^url' => $sendurl,
						));
					}
					
					foreach ($params['thread'] as $comment)
						if (isset($comment['notify']) && !qa_post_is_by_user($comment, $userid, $cookieid)) {
							$senduserid=$comment['userid'];
							$sendemail=@$comment['notify'];
							
							if (qa_email_validate($sendemail)) {
								if (@$senttoemail[$sendemail])
									continue;
									
								$senttoemail[$sendemail]=true;
								
							} elseif (isset($senduserid)) {
								if (@$senttouserid[$senduserid])
									continue;
									
								$senttouserid[$senduserid]=true;
							}
		
							qa_send_notification($senduserid, $sendemail, @$comment['handle'], _('Your ^site_title comment has been added to'), _("A new comment by ^c_handle has been added after your comment on ^site_title:\n\n^open^c_content^close\n\nThe discussion is following:\n\n^open^c_context^close\n\nYou may respond by adding another comment:\n\n^url\n\nThank you,\n\n^site_title"), array(
								'^c_handle' => $sendhandle,
								'^c_context' => $sendcontext,
								'^c_content' => $sendtext,
								'^url' => $sendurl,
							));
						}
					break;

					
				case 'q_queue':
				case 'q_requeue':
					if (qa_opt('moderate_notify_admin'))
						qa_send_notification(null, qa_opt('feedback_email'), null,
							($event=='q_requeue') ? _('^site_title moderation') : _('^site_title moderation'),
							($event=='q_requeue') ? _("An edited post by ^p_handle requires your reapproval:\n\n^open^p_context^close\n\nClick below to approve or hide the edited post:\n\n^url\n\n\nClick below to review all queued posts:\n\n^a_url\n\n\nThank you,\n\n^site_title") : _("A post by ^p_handle requires your approval:\n\n^open^p_context^close\n\nClick below to approve or reject the post:\n\n^url\n\n\nClick below to review all queued posts:\n\n^a_url\n\n\nThank you,\n\n^site_title"),
							array(
								'^p_handle' => isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] :
									(strlen(@$oldquestion['name']) ? $oldquestion['name'] : _('anonymous'))),
								'^p_context' => trim(@$params['title']."\n\n".$params['text']),
								'^url' => qa_q_path($params['postid'], $params['title'], true),
								'^a_url' => qa_path_absolute('admin/moderate'),
							)
						);
					break;
					

				case 'a_queue':
				case 'a_requeue':
					if (qa_opt('moderate_notify_admin'))
						qa_send_notification(null, qa_opt('feedback_email'), null,
							($event=='a_requeue') ? _('^site_title moderation') : _('^site_title moderation'),
							($event=='a_requeue') ? _("An edited post by ^p_handle requires your reapproval:\n\n^open^p_context^close\n\nClick below to approve or hide the edited post:\n\n^url\n\n\nClick below to review all queued posts:\n\n^a_url\n\n\nThank you,\n\n^site_title") : _("A post by ^p_handle requires your approval:\n\n^open^p_context^close\n\nClick below to approve or reject the post:\n\n^url\n\n\nClick below to review all queued posts:\n\n^a_url\n\n\nThank you,\n\n^site_title"),
							array(
								'^p_handle' => isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] :
									(strlen(@$oldanswer['name']) ? $oldanswer['name'] : _('anonymous'))),
								'^p_context' => $params['text'],
								'^url' => qa_q_path($params['parentid'], $params['parent']['title'], true, 'A', $params['postid']),
								'^a_url' => qa_path_absolute('admin/moderate'),
							)
						);
					break;
					

				case 'c_queue':
				case 'c_requeue':
					if (qa_opt('moderate_notify_admin'))
						qa_send_notification(null, qa_opt('feedback_email'), null,
							($event=='c_requeue') ? _('^site_title moderation') : _('^site_title moderation'),
							($event=='c_requeue') ? _("An edited post by ^p_handle requires your reapproval:\n\n^open^p_context^close\n\nClick below to approve or hide the edited post:\n\n^url\n\n\nClick below to review all queued posts:\n\n^a_url\n\n\nThank you,\n\n^site_title") : _("A post by ^p_handle requires your approval:\n\n^open^p_context^close\n\nClick below to approve or reject the post:\n\n^url\n\n\nClick below to review all queued posts:\n\n^a_url\n\n\nThank you,\n\n^site_title"),
							array(
								'^p_handle' => isset($handle) ? $handle : (strlen($params['name']) ? $params['name'] :
									(strlen(@$oldcomment['name']) ? $oldcomment['name'] : // could also be after answer converted to comment
									(strlen(@$oldanswer['name']) ? $oldanswer['name'] : _('anonymous')))),
								'^p_context' => $params['text'],
								'^url' => qa_q_path($params['questionid'], $params['question']['title'], true, 'C', $params['postid']),
								'^a_url' => qa_path_absolute('admin/moderate'),
							)
						);
					break;

					
				case 'q_flag':
				case 'a_flag':
				case 'c_flag':
					$flagcount=$params['flagcount'];
					$oldpost=$params['oldpost'];
					$notifycount=$flagcount-qa_opt('flagging_notify_first');
					
					if ( ($notifycount>=0) && (($notifycount % qa_opt('flagging_notify_every'))==0) )
						qa_send_notification(null, qa_opt('feedback_email'), null, _('^site_title has a flagged post'), _("A post by ^p_handle has received ^flags:\n\n^open^p_context^close\n\nClick below to see the post:\n\n^url\n\n\nClick below to review all flagged posts:\n\n^a_url\n\n\nThank you,\n\n^site_title"), array(
							'^p_handle' => isset($oldpost['handle']) ? $oldpost['handle'] :
								(strlen($oldpost['name']) ? $oldpost['name'] : _('anonymous')),
							'^flags' => sprintf(ngettext('%d flag', '%d flags', $flagcount), $flagcount),
							'^p_context' => trim(@$oldpost['title']."\n\n".qa_viewer_text($oldpost['content'], $oldpost['format'])),
							'^url' => qa_q_path($params['questionid'], $params['question']['title'], true, $oldpost['basetype'], $oldpost['postid']),
							'^a_url' => qa_path_absolute('admin/flagged'),
						));
					break;
		
		
				case 'a_select':
					$answer=$params['answer'];
								
					if (isset($answer['notify']) && !qa_post_is_by_user($answer, $userid, $cookieid)) {
						$blockwordspreg=qa_get_block_words_preg();
						$sendcontent=qa_viewer_text($answer['content'], $answer['format'], array('blockwordspreg' => $blockwordspreg));
		
						qa_send_notification($answer['userid'], $answer['notify'], @$answer['handle'], _('Your ^site_title answer has been selected!'), _("Congratulations! Your answer on ^site_title has been selected as the best by ^s_handle:\n\n^open^a_content^close\n\nThe question was:\n\n^open^q_title^close\n\nClick below to see your answer:\n\n^url\n\nThank you,\n\n^site_title"), array(
							'^s_handle' => isset($handle) ? $handle : _('anonymous'),
							'^q_title' => qa_block_words_replace($params['parent']['title'], $blockwordspreg),
							'^a_content' => $sendcontent,
							'^url' => qa_q_path($params['parentid'], $params['parent']['title'], true, 'A', $params['postid']),
						));
					}
					break;
				
				case 'u_register':
					if (qa_opt('register_notify_admin'))
						qa_send_notification(null, qa_opt('feedback_email'), null, _('^site_title has a new registered user'),
							qa_opt('moderate_users') ? _("A new user has registered as ^u_handle.\n\nClick below to approve the user:\n\n^url\n\nClick below to review all users waiting for approval:\n\n^a_url\n\nThank you,\n\n^site_title") : _("A new user has registered as ^u_handle.\n\nClick below to view the user profile:\n\n^url\n\nThank you,\n\n^site_title"), array(
							'^u_handle' => $handle,
							'^url' => qa_path_absolute('user/'.$handle),
							'^a_url' => qa_path_absolute('admin/approve'),
						));
					break;
					
				case 'u_level':
					if ( ($params['level']>=QA_USER_LEVEL_APPROVED) && ($params['oldlevel']<QA_USER_LEVEL_APPROVED) )
						qa_send_notification($params['userid'], null, $params['handle'], _("Your ^site_title user has been approved"), _("You can see your new user profile here:\n\n^url\n\nThank you,\n\n^site_title"), array(
							'^url' => qa_path_absolute('user/'.$params['handle']),
						));
					break;
				
				case 'u_wall_post':
					if ($userid!=$params['userid'])
						qa_send_notification($params['userid'], null, $params['handle'], _('Post on your ^site_title wall'), _("^f_handle has posted on your user wall at ^site_title:\n\n^open^post^close\n\nYou may respond to the post here:\n\n^url\n\nThank you,\n\n^site_title"), array(
							'^f_handle' => isset($handle) ? $handle : _('anonymous'),
							'^post' => $params['text'],
							'^url' => qa_path_absolute('user/'.$params['handle'], null, 'wall'),
						));
					break;
			}
		}
	
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/
