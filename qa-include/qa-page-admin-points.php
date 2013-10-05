<?php
	
/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-page-admin-points.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for admin page for settings about user points


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

	require_once QA_INCLUDE_DIR.'qa-db-recalc.php';
	require_once QA_INCLUDE_DIR.'qa-db-points.php';
	require_once QA_INCLUDE_DIR.'qa-app-options.php';
	require_once QA_INCLUDE_DIR.'qa-app-admin.php';
	require_once QA_INCLUDE_DIR.'qa-util-sort.php';
	
	
//	Check admin privileges

	if (!qa_admin_check_privileges($qa_content))
		return $qa_content;


//	Process user actions
	
	$securityexpired=false;
	$recalculate=false;
	$optionnames=qa_db_points_option_names();

	if (qa_clicked('doshowdefaults')) {
		$options=array();
		
		foreach ($optionnames as $optionname)
			$options[$optionname]=qa_default_option($optionname);
		
	} else {
		if (qa_clicked('docancel'))
			;

		elseif (qa_clicked('dosaverecalc')) {
			if (!qa_check_form_security_code('admin/points', qa_post_text('code')))
				$securityexpired=true;
		
			else {
				foreach ($optionnames as $optionname)
					qa_set_option($optionname, (int)qa_post_text('option_'.$optionname));
					
				if (!qa_post_text('has_js'))
					qa_redirect('admin/recalc', array('dorecalcpoints' => 1));
				else
					$recalculate=true;
			}
		}
	
		$options=qa_get_options($optionnames);
	}
	
	
//	Prepare content for theme

	$qa_content=qa_content_prepare();

	$qa_content['title']=qa_html(_('Administration center')).' - '.qa_html(_('Points'));
	$qa_content['error']=$securityexpired ? qa_html(_('Form security code expired - please try again')) : qa_admin_page_error();

	$qa_content['form']=array(
		'tags' => 'method="post" action="'.qa_self_html().'" name="points_form" onsubmit="document.forms.points_form.has_js.value=1; return true;"',
		
		'style' => 'wide',
		
		'buttons' => array(
			'saverecalc' => array(
				'tags' => 'id="dosaverecalc"',
				'label' => qa_html(_('Save and Recalculate')),
			),
		),
		
		'hidden' => array(
			'dosaverecalc' => '1',
			'has_js' => '0',
			'code' => qa_get_form_security_code('admin/points'),
		),
	);

	
	if (qa_clicked('doshowdefaults')) {
		$qa_content['form']['ok']=qa_html(_('Defaults shown below but NOT YET APPLIED:'));
	
		$qa_content['form']['buttons']['cancel']=array(
			'tags' => 'name="docancel"',
			'label' => qa_html(_('Cancel')),
		);

	} else {
		if ($recalculate) {
			$qa_content['form']['ok']='<span id="recalc_ok"></span>';
			$qa_content['form']['hidden']['code_recalc']=qa_get_form_security_code('admin/recalc');
			
			$qa_content['script_rel'][]='qa-content/qa-admin.js?'.QA_VERSION;
			$qa_content['script_var']['qa_warning_recalc']=_('A database clean-up operation is running. If you close this page now, the operation will be interrupted.');
			
			$qa_content['script_onloads'][]=array(
				"qa_recalc_click('dorecalcpoints', document.getElementById('dosaverecalc'), null, 'recalc_ok');"
			);
		}
		
		$qa_content['form']['buttons']['showdefaults']=array(
			'tags' => 'name="doshowdefaults"',
			'label' => qa_html(_('Show Defaults')),
		);
	}

	$optionstrings=array(
		'points_a_selected' => _('Having your answer selected as the best:'),
		'points_a_voted_max_gain' => _('Limit from up votes on each answer:'),
		'points_a_voted_max_loss' => _('Limit from down votes on each answer:'),
		'points_base' => _('Add for all users:'),
		'points_multiple' => _('Multiply all points:'),
		'points_per_a_voted_down' => _('Per down vote on your answer:'),
		'points_per_a_voted_up' => _('Per up vote on your answer:'),
		'points_per_q_voted_down' => _('Per down vote on your question:'),
		'points_per_q_voted_up' => _('Per up vote on your question:'),
		'points_post_a' => _('Posting an answer:'),
		'points_post_q' => _('Posting a question:'),
		'points_q_voted_max_gain' => _('Limit from up votes on each question:'),
		'points_q_voted_max_loss' => _('Limit from down votes on each question:'),
		'points_select_a' => _('Selecting an answer for your question:'),
		'points_vote_down_a' => _('Voting down an answer:'),
		'points_vote_down_q' => _('Voting down a question:'),
		'points_vote_up_a' => _('Voting up an answer:'),
		'points_vote_up_q' => _('Voting up a question:'),
	);
	
	foreach ($optionnames as $optionname) {
		$optionfield=array(
			'label' => qa_html($optionstrings[$optionname]),
			'tags' => 'name="option_'.$optionname.'"',
			'value' => qa_html($options[$optionname]),
			'type' => 'number',
			'note' => qa_html(_('points')),
		);
		
		switch ($optionname) {
			case 'points_multiple':
				$prefix='&#215;';
				unset($optionfield['note']);
				break;
				
			case 'points_per_q_voted_up':
			case 'points_per_a_voted_up':
			case 'points_q_voted_max_gain':
			case 'points_a_voted_max_gain':
				$prefix='+';
				break;
			
			case 'points_per_q_voted_down':
			case 'points_per_a_voted_down':
			case 'points_q_voted_max_loss':
			case 'points_a_voted_max_loss':
				$prefix='&ndash;';
				break;
				
			case 'points_base':
				$prefix='+';
				break;
				
			default:
				$prefix='<span style="visibility:hidden;">+</span>'; // for even alignment
				break;
		}
		
		$optionfield['prefix']='<span style="width:1em; display:inline-block; display:-moz-inline-stack;">'.$prefix.'</span>';
		
		$qa_content['form']['fields'][$optionname]=$optionfield;
	}
	
	qa_array_insert($qa_content['form']['fields'], 'points_post_a', array('blank0' => array('type' => 'blank')));
	qa_array_insert($qa_content['form']['fields'], 'points_vote_up_q', array('blank1' => array('type' => 'blank')));
	qa_array_insert($qa_content['form']['fields'], 'points_multiple', array('blank2' => array('type' => 'blank')));
	
	
	$qa_content['navigation']['sub']=qa_admin_sub_navigation();

	
	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/
