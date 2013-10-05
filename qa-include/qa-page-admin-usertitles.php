<?php
	
/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-page-admin-usertitles.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for admin page for editing custom user titles


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

	require_once QA_INCLUDE_DIR.'qa-app-admin.php';
	require_once QA_INCLUDE_DIR.'qa-db-selects.php';

	
//	Get current list of user titles and determine the state of this admin page

	$oldpoints=qa_post_text('edit');
	if (!isset($oldpoints))
		$oldpoints=qa_get('edit');
		
	$pointstitle=qa_get_points_to_titles();


//	Check admin privileges (do late to allow one DB query)

	if (!qa_admin_check_privileges($qa_content))
		return $qa_content;
		
		
//	Process saving an old or new user title

	$securityexpired=false;
	
	if (qa_clicked('docancel'))
		qa_redirect('admin/users');

	elseif (qa_clicked('dosavetitle')) {
		require_once QA_INCLUDE_DIR.'qa-util-string.php';
		
		if (!qa_check_form_security_code('admin/usertitles', qa_post_text('code')))
			$securityexpired=true;
		
		else {
			if (qa_post_text('dodelete')) {
				unset($pointstitle[$oldpoints]);
			
			} else {
				$intitle=qa_post_text('title');
				$inpoints=qa_post_text('points');
		
				$errors=array();
				
			//	Verify the title and points are legitimate
			
				if (!strlen($intitle))
					$errors['title']=_('Please enter something in this field');
					
				if (!is_numeric($inpoints))
					$errors['points']=_('Please enter something in this field');
				else {
					$inpoints=(int)$inpoints;
					
					if (isset($pointstitle[$inpoints]) && ((!strlen(@$oldpoints)) || ($inpoints!=$oldpoints)) )
						$errors['points']=_('This value is already being used by another title');
				}
		
			//	Perform appropriate action
		
				if (isset($pointstitle[$oldpoints])) { // changing existing user title
					$newpoints=isset($errors['points']) ? $oldpoints : $inpoints;
					$newtitle=isset($errors['title']) ? $pointstitle[$oldpoints] : $intitle;
		
					unset($pointstitle[$oldpoints]);
					$pointstitle[$newpoints]=$newtitle;
		
				} elseif (empty($errors)) // creating a new user title
					$pointstitle[$inpoints]=$intitle;
			}
				
		//	Save the new option value
				
			krsort($pointstitle, SORT_NUMERIC);
			
			$option='';
			foreach ($pointstitle as $points => $title)
				$option.=(strlen($option) ? ',' : '').$points.' '.$title;
				
			qa_set_option('points_to_titles', $option); 
	
			if (empty($errors))
				qa_redirect('admin/users');
		}
	}
	
		
//	Prepare content for theme
	
	$qa_content=qa_content_prepare();

	$qa_content['title']=qa_html(_('Administration center')).' - '.qa_html(_('Users'));	
	$qa_content['error']=$securityexpired ? qa_html(_('Form security code expired - please try again')) : qa_admin_page_error();

	$qa_content['form']=array(
		'tags' => 'method="post" action="'.qa_path_html(qa_request()).'"',
		
		'style' => 'tall',
		
		'fields' => array(
			'title' => array(
				'tags' => 'name="title" id="title"',
				'label' => qa_html(_('User title - HTML allowed:')),
				'value' => qa_html(isset($intitle) ? $intitle : @$pointstitle[$oldpoints]),
				'error' => qa_html(@$errors['title']),
			),
			
			'delete' => array(
				'tags' => 'name="dodelete" id="dodelete"',
				'label' => qa_html(_('Delete this title')),
				'value' => 0,
				'type' => 'checkbox',
			),
			
			'points' => array(
				'id' => 'points_display',
				'tags' => 'name="points"',
				'label' => qa_html(_('Points required to receive title:')),
				'type' => 'number',
				'value' => qa_html(isset($inpoints) ? $inpoints : @$oldpoints),
				'error' => qa_html(@$errors['points']),
			),
		),

		'buttons' => array(
			'save' => array(
				'label' => qa_html(isset($pointstitle[$oldpoints]) ? _('Save Changes') : _('Add Title')),
			),
			
			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => qa_html(_('Cancel')),
			),
		),
		
		'hidden' => array(
			'dosavetitle' => '1', // for IE
			'edit' => @$oldpoints,
			'code' => qa_get_form_security_code('admin/usertitles'),
		),
	);
	
	if (isset($pointstitle[$oldpoints]))
		qa_set_display_rules($qa_content, array(
			'points_display' => '!dodelete',
		));
	else
		unset($qa_content['form']['fields']['delete']);

	$qa_content['focusid']='title';

	$qa_content['navigation']['sub']=qa_admin_sub_navigation();

	
	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/
