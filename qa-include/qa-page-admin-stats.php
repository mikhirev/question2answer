<?php
	
/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-page-admin-stats.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for admin page showing usage statistics and clean-up buttons


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
	require_once QA_INCLUDE_DIR.'qa-app-admin.php';
	require_once QA_INCLUDE_DIR.'qa-db-admin.php';

	
//	Check admin privileges (do late to allow one DB query)

	if (!qa_admin_check_privileges($qa_content))
		return $qa_content;


//	Get the information to display

	$qcount=(int)qa_opt('cache_qcount');
	$qcount_anon=qa_db_count_posts('Q', false);

	$acount=(int)qa_opt('cache_acount');
	$acount_anon=qa_db_count_posts('A', false);

	$ccount=(int)qa_opt('cache_ccount');
	$ccount_anon=qa_db_count_posts('C', false);
	

//	Prepare content for theme

	$qa_content=qa_content_prepare();

	$qa_content['title']=qa_html(_('Administration center').' - '._('Stats'));
	
	$qa_content['error']=qa_admin_page_error();

	$qa_content['form']=array(
		'style' => 'wide',
		
		'fields' => array(
			'q2a_version' => array(
				'label' => qa_html(_('Question2Answer version:')),
				'value' => qa_html(QA_VERSION),
			),
			
			'q2a_date' => array(
				'label' => qa_html(_('Build date:')),
				'value' => qa_html(QA_BUILD_DATE),
			),
			
			'q2a_latest' => array(
				'label' => qa_html(_('Latest version:')),
				'type' => 'custom',
				'html' => '<iframe src="http://www.question2answer.org/question2answer-latest.php?version='.urlencode(QA_VERSION).'&language='.urlencode(qa_opt('site_language')).
					'" width="100" height="16" style="vertical-align:middle; border:0; background:transparent;" allowTransparency="true" scrolling="no" frameborder="0"></iframe>',
			),
			
			'break0' => array(
				'type' => 'blank',
			),
			
			'db_version' => array(
				'label' => qa_html(_('Q2A database version:')),
				'value' => qa_html(qa_opt('db_version')),
			),
			
			'db_size' => array(
				'label' => qa_html(_('Database size:')),
				'value' => qa_html(number_format(qa_db_table_size()/1048576, 1).' MB'),
			),
			
			'break1' => array(
				'type' => 'blank',
			),
			
			'php_version' => array(
				'label' => qa_html(_('PHP version:')),
				'value' => qa_html(phpversion()),
			),
			
			'mysql_version' => array(
				'label' => qa_html(_('MySQL version:')),
				'value' => qa_html(qa_db_mysql_version()),
			),
			
			'break2' => array(
				'type' => 'blank',
			),
	
			'qcount' => array(
				'label' => qa_html(_('Total questions:')),
				'value' => qa_html(number_format($qcount)),
			),
			
			'qcount_users' => array(
				'label' => qa_html(_('From users:')),
				'value' => qa_html(number_format($qcount-$qcount_anon)),
			),
	
			'qcount_anon' => array(
				'label' => qa_html(_('From anonymous:')),
				'value' => qa_html(number_format($qcount_anon)),
			),
			
			'break3' => array(
				'type' => 'blank',
			),
	
			'acount' => array(
				'label' => qa_html(_('Total answers:')),
				'value' => qa_html(number_format($acount)),
			),
	
			'acount_users' => array(
				'label' => qa_html(_('From users:')),
				'value' => qa_html(number_format($acount-$acount_anon)),
			),
	
			'acount_anon' => array(
				'label' => qa_html(_('From anonymous:')),
				'value' => qa_html(number_format($acount_anon)),
			),
			
			'break4' => array(
				'type' => 'blank',
			),
			
			'ccount' => array(
				'label' => qa_html(_('Total comments:')),
				'value' => qa_html(number_format($ccount)),
			),
	
			'ccount_users' => array(
				'label' => qa_html(_('From users:')),
				'value' => qa_html(number_format($ccount-$ccount_anon)),
			),
	
			'ccount_anon' => array(
				'label' => qa_html(_('From anonymous:')),
				'value' => qa_html(number_format($ccount_anon)),
			),
			
			'break5' => array(
				'type' => 'blank',
			),
			
			'users' => array(
				'label' => qa_html(_('Registered users:')),
				'value' => QA_FINAL_EXTERNAL_USERS ? '' : qa_html(number_format(qa_db_count_users())),
			),
	
			'users_active' => array(
				'label' => qa_html(_('Active users:')),
				'value' => qa_html(number_format((int)qa_opt('cache_userpointscount'))),
			),
			
			'users_posted' => array(
				'label' => qa_html(_('Users who posted:')),
				'value' => qa_html(number_format(qa_db_count_active_users('posts'))),
			),
	
			'users_voted' => array(
				'label' => qa_html(_('Users who voted:')),
				'value' => qa_html(number_format(qa_db_count_active_users('uservotes'))),
			),
		),
	);
	
	if (QA_FINAL_EXTERNAL_USERS)
		unset($qa_content['form']['fields']['users']);
	else
		unset($qa_content['form']['fields']['users_active']);

	foreach ($qa_content['form']['fields'] as $index => $field)
		if (empty($field['type']))
			$qa_content['form']['fields'][$index]['type']='static';
	
	$qa_content['form_2']=array(
		'tags' => 'method="post" action="'.qa_path_html('admin/recalc').'"',
		
		'title' => qa_html(_('Database clean-up operations')),
		
		'style' => 'basic',
		
		'buttons' => array(
			'recount_posts' => array(
				'label' => qa_html(_('Recount posts')),
				'tags' => 'name="dorecountposts" onclick="return qa_recalc_click(this.name, this, '.qa_js(_('Stop recounting')).', \'recount_posts_note\');"',
				'note' => '<span id="recount_posts_note">'.qa_html(_(' - the number of answers, votes, flags and hotness for each post')).'</span>',
			),
	
			'reindex_content' => array(
				'label' => qa_html(_('Reindex content')),
				'tags' => 'name="doreindexcontent" onclick="return qa_recalc_click(this.name, this, '.qa_js(_('Stop reindexing')).', \'reindex_content_note\');"',
				'note' => '<span id="reindex_content_note">'.qa_html(_(' - for searching and related question suggestions')).'</span>',
			),
			
			'recalc_points' => array(
				'label' => qa_html(_('Recalculate user points')),
				'tags' => 'name="dorecalcpoints" onclick="return qa_recalc_click(this.name, this, '.qa_js(_('Stop recalculating')).', \'recalc_points_note\');"',
				'note' => '<span id="recalc_points_note">'.qa_html(_(' - for user ranking and points displays')).'</span>',
			),
			
			'refill_events' => array(
				'label' => qa_html(_('Refill event streams')),
				'tags' => 'name="dorefillevents" onclick="return qa_recalc_click(this.name, this, '.qa_js(_('Stop recalculating')).', \'refill_events_note\');"',
				'note' => '<span id="refill_events_note">'.qa_html(_(' - for each user\'s list of updates')).'</span>',
			),
			
			'recalc_categories' => array(
				'label' => qa_html(_('Recalculate categories')),
				'tags' => 'name="dorecalccategories" onclick="return qa_recalc_click(this.name, this, '.qa_js(_('Stop recalculating')).', \'recalc_categories_note\');"',
				'note' => '<span id="recalc_categories_note">'.qa_html(_(' - for post categories and category counts')).'</span>',
			),
			
			'delete_hidden' => array(
				'label' => qa_html(_('Delete hidden posts')),
				'tags' => 'name="dodeletehidden" onclick="return qa_recalc_click(this.name, this, '.qa_js(_('Stop deleting')).', \'delete_hidden_note\');"',
				'note' => '<span id="delete_hidden_note">'.qa_html(_(' - all hidden questions, answer and comments without dependents')).'</span>',
			),
		),
		
		'hidden' => array(
			'code' => qa_get_form_security_code('admin/recalc'),
		),
	);
	
	if (!qa_using_categories())
		unset($qa_content['form_2']['buttons']['recalc_categories']);
	
	if (defined('QA_BLOBS_DIRECTORY')) {
		if (qa_db_has_blobs_in_db())
			$qa_content['form_2']['buttons']['blobs_to_disk']=array(
				'label' => qa_html(_('Blobs to disk')),
				'tags' => 'name="doblobstodisk" onclick="return qa_recalc_click(this.name, this, '.qa_js(_('Stop migrating')).', \'blobs_to_disk_note\');"',
				'note' => '<span id="blobs_to_disk_note">'.qa_html(_('- migrate all uploaded images and documents from the database to disk files')).'</span>',
			);
		
		if (qa_db_has_blobs_on_disk())
			$qa_content['form_2']['buttons']['blobs_to_db']=array(
				'label' => qa_html(_('Blobs to database')),
				'tags' => 'name="doblobstodb" onclick="return qa_recalc_click(this.name, this, '.qa_js(_('Stop migrating')).', \'blobs_to_db_note\');"',
				'note' => '<span id="blobs_to_db_note">'.qa_html(_('- migrate all uploaded images and documents from disk files to the database')).'</span>',
			);
	}

	
	$qa_content['script_rel'][]='qa-content/qa-admin.js?'.QA_VERSION;
	$qa_content['script_var']['qa_warning_recalc']=_('A database clean-up operation is running. If you close this page now, the operation will be interrupted.');

	$qa_content['navigation']['sub']=qa_admin_sub_navigation();

	
	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/
