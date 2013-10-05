<?php
	
/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-page-admin-categories.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for admin page for editing categories


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
	require_once QA_INCLUDE_DIR.'qa-db-admin.php';

	
//	Get relevant list of categories

	$editcategoryid=qa_post_text('edit');
	if (!isset($editcategoryid))
		$editcategoryid=qa_get('edit');
	if (!isset($editcategoryid))
		$editcategoryid=qa_get('addsub');
		
	$categories=qa_db_select_with_pending(qa_db_category_nav_selectspec($editcategoryid, true, false, true));
	

//	Check admin privileges (do late to allow one DB query)

	if (!qa_admin_check_privileges($qa_content))
		return $qa_content;
		
		
//	Work out the appropriate state for the page
	
	$editcategory=@$categories[$editcategoryid];
	
	if (isset($editcategory)) {
		$parentid=qa_get('addsub');
		if (isset($parentid))
			$editcategory=array('parentid' => $parentid);
	
	} else {
		if (qa_clicked('doaddcategory'))
			$editcategory=array();
		
		elseif (qa_clicked('dosavecategory')) {
			$parentid=qa_post_text('parent');
			$editcategory=array('parentid' => strlen($parentid) ? $parentid : null);
		}
	}
	
	$setmissing=qa_post_text('missing') || qa_get('missing');
	
	$setparent=(!$setmissing) && (qa_post_text('setparent') || qa_get('setparent')) && isset($editcategory['categoryid']);
	
	$hassubcategory=false;
	foreach ($categories as $category)
		if (!strcmp($category['parentid'], $editcategoryid))
			$hassubcategory=true;


//	Process saving options

	$savedoptions=false;
	$securityexpired=false;
	
	if (qa_clicked('dosaveoptions')) {
		if (!qa_check_form_security_code('admin/categories', qa_post_text('code')))
			$securityexpired=true;

		else {
			qa_set_option('allow_no_category', (int)qa_post_text('option_allow_no_category'));
			qa_set_option('allow_no_sub_category', (int)qa_post_text('option_allow_no_sub_category'));
			$savedoptions=true;
		}
	}


//	Process saving an old or new category

	if (qa_clicked('docancel')) {
		if ($setmissing || $setparent)
			qa_redirect(qa_request(), array('edit' => $editcategory['categoryid']));
		elseif (isset($editcategory['categoryid']))
			qa_redirect(qa_request());
		else
			qa_redirect(qa_request(), array('edit' => @$editcategory['parentid']));
		
	} elseif (qa_clicked('dosetmissing')) {
		if (!qa_check_form_security_code('admin/categories', qa_post_text('code')))
			$securityexpired=true;
			
		else {
			$inreassign=qa_get_category_field_value('reassign');
			qa_db_category_reassign($editcategory['categoryid'], $inreassign);
			qa_redirect(qa_request(), array('recalc' => 1, 'edit' => $editcategory['categoryid']));
		}
	
	} elseif (qa_clicked('dosavecategory')) {
		if (!qa_check_form_security_code('admin/categories', qa_post_text('code')))
			$securityexpired=true;
			
		elseif (qa_post_text('dodelete')) {

			if (!$hassubcategory) {
				$inreassign=qa_get_category_field_value('reassign');
				qa_db_category_reassign($editcategory['categoryid'], $inreassign);
				qa_db_category_delete($editcategory['categoryid']);
				qa_redirect(qa_request(), array('recalc' => 1, 'edit' => $editcategory['parentid']));
			}
		
		} else {
			require_once QA_INCLUDE_DIR.'qa-util-string.php';
			
			$inname=qa_post_text('name');
			$incontent=qa_post_text('content');
			$inparentid=$setparent ? qa_get_category_field_value('parent') : $editcategory['parentid'];
			$inposition=qa_post_text('position');
			$errors=array();
	
		//	Check the parent ID
		
			$incategories=qa_db_select_with_pending(qa_db_category_nav_selectspec($inparentid, true));
		
		//	Verify the name is legitimate for that parent ID
		
			if (empty($inname))
				$errors['name']=_('Please enter something in this field');
			elseif (qa_strlen($inname)>QA_DB_MAX_CAT_PAGE_TITLE_LENGTH)
				$errors['name']=sprintf(_('Maximum length is %d characters'), QA_DB_MAX_CAT_PAGE_TITLE_LENGTH);
			else {
				foreach ($incategories as $category)
					if (
						(!strcmp($category['parentid'], $inparentid)) &&
						strcmp($category['categoryid'], @$editcategory['categoryid']) &&
						qa_strtolower($category['title']) == qa_strtolower($inname)
					)
						$errors['name']=_('This is already being used by a category');
			}
			
		//	Verify the slug is legitimate for that parent ID
		
			for ($attempt=0; $attempt<100; $attempt++) {
				switch ($attempt) {
					case 0:
						$inslug=qa_post_text('slug');
						if (!isset($inslug))
							$inslug=implode('-', qa_string_to_words($inname));
						break;
						
					case 1:
						$inslug=sprintf(_('category-%s'), $inslug);
						break;
						
					default:
						$inslug=sprintf(_('category-%s'), $attempt-1);
						break;
				}
				
				$matchcategoryid=qa_db_category_slug_to_id($inparentid, $inslug); // query against DB since MySQL ignores accents, etc...
				
				if (!isset($inparentid))
					$matchpage=qa_db_single_select(qa_db_page_full_selectspec($inslug, false));
				else
					$matchpage=null;
				
				if (empty($inslug))
					$errors['slug']=_('Please enter something in this field');
				elseif (qa_strlen($inslug)>QA_DB_MAX_CAT_PAGE_TAGS_LENGTH)
					$errors['slug']=sprintf(_('Maximum length is %d characters'), QA_DB_MAX_CAT_PAGE_TAGS_LENGTH);
				elseif (preg_match('/[\\+\\/]/', $inslug))
					$errors['slug']=sprintf(_('The slug may not contain these characters: %s'), '+ /');
				elseif ( (!isset($inparentid)) && qa_admin_is_slug_reserved($inslug)) // only top level is a problem
					$errors['slug']=_('This slug is reserved for use by another page');
				elseif (isset($matchcategoryid) && strcmp($matchcategoryid, @$editcategory['categoryid']))
					$errors['slug']=_('This is already being used by a category');
				elseif (isset($matchpage))
					$errors['slug']=_('This is already being used by a page');
				else
					unset($errors['slug']);
				
				if (isset($editcategory['categoryid']) || !isset($errors['slug'])) // don't try other options if editing existing category
					break;
			}

		//	Perform appropriate database action

			if (empty($errors)) {
				if (isset($editcategory['categoryid'])) { // changing existing category
					qa_db_category_rename($editcategory['categoryid'], $inname, $inslug);
					
					$recalc=false;
					
					if ($setparent) {
						qa_db_category_set_parent($editcategory['categoryid'], $inparentid);
						$recalc=true;
					} else {
						qa_db_category_set_content($editcategory['categoryid'], $incontent);
						qa_db_category_set_position($editcategory['categoryid'], $inposition);
						$recalc=($hassubcategory && ($inslug !== $editcategory['tags']));
					}
					
					qa_redirect(qa_request(), array('edit' => $editcategory['categoryid'], 'saved' => true, 'recalc' => (int)$recalc));
					
				} else { // creating a new one
					$categoryid=qa_db_category_create($inparentid, $inname, $inslug);
					
					qa_db_category_set_content($categoryid, $incontent);
					
					if (isset($inposition))
						qa_db_category_set_position($categoryid, $inposition);
					
					qa_redirect(qa_request(), array('edit' => $inparentid, 'added' => true));
				}
			}
		}
	}
		
	
//	Prepare content for theme
	
	$qa_content=qa_content_prepare();

	$qa_content['title']=qa_html(_('Administration center')).' - '.qa_html(_('Categories'));
	$qa_content['error']=$securityexpired ? qa_html(_('Form security code expired - please try again')) : qa_admin_page_error();
	
	if ($setmissing) {
		$qa_content['form']=array(
			'tags' => 'method="post" action="'.qa_path_html(qa_request()).'"',
			
			'style' => 'tall',
			
			'fields' => array(
				'reassign' => array(
					'label' => isset($editcategory)
						? qa_html(sprintf(_('Move questions in %s with no sub-category to:'), $editcategory['title']))
						: qa_html(_('Move questions with no category to:')),
					'loose' => true,
				),
			),
			
			'buttons' => array(
				'save' => array(
					'tags' => 'id="dosaveoptions"', // just used for qa_recalc_click()
					'label' => qa_html(_('Save Changes')),
				),
				
				'cancel' => array(
					'tags' => 'name="docancel"',
					'label' => qa_html(_('Cancel')),
				),
			),
			
			'hidden' => array(
				'dosetmissing' => '1', // for IE
				'edit' => @$editcategory['categoryid'],
				'missing' => '1',
				'code' => qa_get_form_security_code('admin/categories'),
			),
		);

		qa_set_up_category_field($qa_content, $qa_content['form']['fields']['reassign'], 'reassign',
			$categories, @$editcategory['categoryid'], qa_opt('allow_no_category'), qa_opt('allow_no_sub_category'));
			
	
	} elseif (isset($editcategory)) {

		$qa_content['form']=array(
			'tags' => 'method="post" action="'.qa_path_html(qa_request()).'"',
			
			'style' => 'tall',

			'ok' => qa_get('saved') ? qa_html(_('Category saved')) : (qa_get('added') ? qa_html(_('Category added')) : null),
			
			'fields' => array(
				'name' => array(
					'id' => 'name_display',
					'tags' => 'name="name" id="name"',
					'label' => qa_html(count($categories) ? _('Category name:') : _('Name of first category:')),
					'value' => qa_html(isset($inname) ? $inname : @$editcategory['title']),
					'error' => qa_html(@$errors['name']),
				),
				
				'questions' => array(),
				
				'delete' => array(),
				
				'reassign' => array(),
				
				'slug' => array(
					'id' => 'slug_display',
					'tags' => 'name="slug"',
					'label' => qa_html(_('Category slug - URL fragment:')),
					'value' => qa_html(isset($inslug) ? $inslug : @$editcategory['tags']),
					'error' => qa_html(@$errors['slug']),
				),
				
				'content' => array(
					'id' => 'content_display',
					'tags' => 'name="content"',
					'label' => qa_html(_('Optional category description:')),
					'value' => qa_html(isset($incontent) ? $incontent : @$editcategory['content']),
					'error' => qa_html(@$errors['content']),
					'rows' => 2,
				),
			),
			
			'buttons' => array(
				'save' => array(
					'tags' => 'id="dosaveoptions"', // just used for qa_recalc_click
					'label' => qa_html(isset($editcategory['categoryid']) ? _('Save Changes') : _('Add Category')),
				),
				
				'cancel' => array(
					'tags' => 'name="docancel"',
					'label' => qa_html(_('Cancel')),
				),
			),
			
			'hidden' => array(
				'dosavecategory' => '1', // for IE
				'edit' => @$editcategory['categoryid'],
				'parent' =>  @$editcategory['parentid'],
				'setparent' => (int)$setparent,
				'code' => qa_get_form_security_code('admin/categories'),
			),
		);
		
		
		if ($setparent) {
			unset($qa_content['form']['fields']['delete']);
			unset($qa_content['form']['fields']['reassign']);
			unset($qa_content['form']['fields']['questions']);
			unset($qa_content['form']['fields']['content']);
			
			$qa_content['form']['fields']['parent']=array(
				'label' => qa_html(_('Parent category:')),
			);
				
			$childdepth=qa_db_category_child_depth($editcategory['categoryid']);
	
			qa_set_up_category_field($qa_content, $qa_content['form']['fields']['parent'], 'parent',
				isset($incategories) ? $incategories : $categories, isset($inparentid) ? $inparentid : @$editcategory['parentid'],
				true, true, QA_CATEGORY_DEPTH-1-$childdepth, @$editcategory['categoryid']);
				
			$qa_content['form']['fields']['parent']['options']['']=qa_html(_('No parent (top level)'));
			
			@$qa_content['form']['fields']['parent']['note'].=qa_html(sprintf(_('Some options may be hidden to prevent a category going deeper than %d levels.'), QA_CATEGORY_DEPTH));

		} elseif (isset($editcategory['categoryid'])) { // existing category
			if ($hassubcategory) {
				$qa_content['form']['fields']['name']['note']=qa_html(_('This category cannot be deleted because it has a sub-category.'));
				unset($qa_content['form']['fields']['delete']);
				unset($qa_content['form']['fields']['reassign']);

			} else {
				$qa_content['form']['fields']['delete']=array(
					'tags' => 'name="dodelete" id="dodelete"',
					'label' =>
						'<span id="reassign_shown">'.qa_html(_('Delete this category and reassign its questions to:')).'</span>'.
						'<span id="reassign_hidden" style="display:none;">'.qa_html(_('Delete this category')).'</span>',
					'value' => 0,
					'type' => 'checkbox',
				);
			
				$qa_content['form']['fields']['reassign']=array(
					'id' => 'reassign_display',
					'tags' => 'name="reassign"',
				);
				
				qa_set_up_category_field($qa_content, $qa_content['form']['fields']['reassign'], 'reassign',
					$categories, $editcategory['parentid'], true, true, null, $editcategory['categoryid']);
			}
			
			$qa_content['form']['fields']['questions']=array(
				'label' => qa_html(_('Total questions:')),
				'type' => 'static',
				'value' => '<a href="'.qa_path_html('questions/'.qa_category_path_request($categories, $editcategory['categoryid'])).'">'.
								sprintf(ngettext('%s question', '%s questions', $editcategory['qcount']), number_format($editcategory['qcount']))
								.'</a>',
			);

			if ($hassubcategory && !qa_opt('allow_no_sub_category')) {
				$nosubcount=qa_db_count_categoryid_qs($editcategory['categoryid']);
				
				if ($nosubcount)
					$qa_content['form']['fields']['questions']['error']=
						sprintf(qa_html(ngettext('%s question in this category have no sub-category - %sset sub-category%s', '%s questions in this category have no sub-category - %sset sub-category%s', $nosubcount)),
							number_format($nosubcount),
							'<a href="'.qa_path_html(qa_request(), array('edit' => $editcategory['categoryid'], 'missing' => 1)).'">',
							'</a>'
						);
			}
			
			qa_set_display_rules($qa_content, array(
				'position_display' => '!dodelete',
				'slug_display' => '!dodelete',
				'content_display' => '!dodelete',
				'parent_display' => '!dodelete',
				'children_display' => '!dodelete',
				'reassign_display' => 'dodelete',
				'reassign_shown' => 'dodelete',
				'reassign_hidden' => '!dodelete',
			));

		} else { // new category
			unset($qa_content['form']['fields']['delete']);
			unset($qa_content['form']['fields']['reassign']);
			unset($qa_content['form']['fields']['slug']);
			unset($qa_content['form']['fields']['questions']);
		
			$qa_content['focusid']='name';
		}
		
		if (!$setparent) {
			$pathhtml=qa_category_path_html($categories, @$editcategory['parentid']);
			
			if (count($categories)) {
				$qa_content['form']['fields']['parent']=array(
					'id' => 'parent_display',
					'label' => qa_html(_('Parent category:')),
					'type' => 'static',
					'value' => (strlen($pathhtml) ? $pathhtml : qa_html(_('No parent (top level)'))),
				);
				
				$qa_content['form']['fields']['parent']['value']=
					'<a href="'.qa_path_html(qa_request(), array('edit' => @$editcategory['parentid'])).'">'.
					$qa_content['form']['fields']['parent']['value'].'</a>';
				
				if (isset($editcategory['categoryid']))
					$qa_content['form']['fields']['parent']['value'].=' - '.
						'<a href="'.qa_path_html(qa_request(), array('edit' => $editcategory['categoryid'], 'setparent' => 1)).
						'" style="white-space: nowrap;">'.qa_html(_('move to different parent')).'</a>';
			}

			$positionoptions=array();
			
			$previous=null;
			$passedself=false;
			
			foreach ($categories as $key => $category)
				if (!strcmp($category['parentid'], @$editcategory['parentid'])) {
					if (isset($previous))
						$positionhtml=qa_html(sprintf(_('After "%s"'), ($passedself ? $category['title'] : $previous['title'])));
					else
						$positionhtml=qa_html(_('First'));
		
					$positionoptions[$category['position']]=$positionhtml;
		
					if (!strcmp($category['categoryid'], @$editcategory['categoryid']))
						$passedself=true;
						
					$previous=$category;
				}
			
			if (isset($editcategory['position']))
				$positionvalue=$positionoptions[$editcategory['position']];
	
			else {
				$positionvalue=isset($previous) ? qa_html(sprintf(_('After "%s"'), $previous['title'])) : qa_html(_('First'));
				$positionoptions[1+@max(array_keys($positionoptions))]=$positionvalue;
			}
	
			$qa_content['form']['fields']['position']=array(
				'id' => 'position_display',
				'tags' => 'name="position"',
				'label' => qa_html(_('Position:')),
				'type' => 'select',
				'options' => $positionoptions,
				'value' => $positionvalue,
			);
			
			if (isset($editcategory['categoryid'])) {
				$catdepth=count(qa_category_path($categories, $editcategory['categoryid']));
					
				if ($catdepth<QA_CATEGORY_DEPTH) {
					$childrenhtml='';
					
					foreach ($categories as $category)
						if (!strcmp($category['parentid'], $editcategory['categoryid']))
							$childrenhtml.=(strlen($childrenhtml) ? ', ' : '').
								'<a href="'.qa_path_html(qa_request(), array('edit' => $category['categoryid'])).'">'.qa_html($category['title']).'</a>'.
								' ('.$category['qcount'].')';
					
					if (!strlen($childrenhtml))
						$childrenhtml=qa_html(_('None'));
					
					$childrenhtml.=' - <a href="'.qa_path_html(qa_request(), array('addsub' => $editcategory['categoryid'])).
						'" style="white-space: nowrap;"><b>'.qa_html(_('add sub-category')).'</b></a>';
					
					$qa_content['form']['fields']['children']=array(
						'id' => 'children_display',
						'label' => qa_html(_('Sub-categories:')),
						'type' => 'static',
						'value' => $childrenhtml,
					);
				} else {
					$qa_content['form']['fields']['name']['note']=qa_html(sprintf(_('This category cannot have sub-categories because it is already %d levels down.'), QA_CATEGORY_DEPTH));
				}
				
			}
		}
			
	} else {
		$qa_content['form']=array(
			'tags' => 'method="post" action="'.qa_path_html(qa_request()).'"',
			
			'ok' => $savedoptions ? qa_html(_('Options saved')) : null,
			
			'style' => 'tall',
			
			'fields' => array(
				'intro' => array(
					'label' => qa_html(_('To get started with categories, click the	\'Add Category\' button.')),
					'type' => 'static',
				),
			),
			
			'buttons' => array(
				'save' => array(
					'tags' => 'name="dosaveoptions" id="dosaveoptions"',
					'label' => qa_html(_('Save Changes')),
				),
				
				'add' => array(
					'tags' => 'name="doaddcategory"',
					'label' => qa_html(_('Add Category')),
				),			
			),
			
			'hidden' => array(
				'code' => qa_get_form_security_code('admin/categories'),
			),	
		);

		if (count($categories)) {
			unset($qa_content['form']['fields']['intro']);
			
			$navcategoryhtml='';

			foreach ($categories as $category)
				if (!isset($category['parentid']))
					$navcategoryhtml.='<a href="'.qa_path_html('admin/categories', array('edit' => $category['categoryid'])).'">'.
						qa_html($category['title']).'</a> - '.qa_html(sprintf(ngettext('%d question', '%d questions', $category['qcount']), $category['qcount'])).'<br/>';

			$qa_content['form']['fields']['nav']=array(
				'label' => qa_html(_('Top level categories:')),
				'type' => 'static',
				'value' => $navcategoryhtml,
			);
				
			$qa_content['form']['fields']['allow_no_category']=array(
				'label' => qa_html(_('Allow questions with no category')),
				'tags' => 'name="option_allow_no_category"',
				'type' => 'checkbox',
				'value' => qa_opt('allow_no_category'),
			);
			
			if (!qa_opt('allow_no_category')) {
				$nocatcount=qa_db_count_categoryid_qs(null);
				
				if ($nocatcount)
					$qa_content['form']['fields']['allow_no_category']['error']=
						sprintf(qa_html(ngettext('%s question currently have no category - %sset category%s', '%s questions currently have no category - %sset category%s', $nocatcount)),
							number_format($nocatcount),
							'<a href="'.qa_path_html(qa_request(), array('missing' => 1)).'">',
							'</a>'
						);
			}
			
			$qa_content['form']['fields']['allow_no_sub_category']=array(
				'label' => qa_html(_('Allow questions with a category but no sub-category')),
				'tags' => 'name="option_allow_no_sub_category"',
				'type' => 'checkbox',
				'value' => qa_opt('allow_no_sub_category'),
			);

		} else
			unset($qa_content['form']['buttons']['save']);
	}

	if (qa_get('recalc')) {
		$qa_content['form']['ok']='<span id="recalc_ok">'.qa_html(_('Recalculate categories')).'</span>';
		$qa_content['form']['hidden']['code_recalc']=qa_get_form_security_code('admin/recalc');
		
		$qa_content['script_rel'][]='qa-content/qa-admin.js?'.QA_VERSION;
		$qa_content['script_var']['qa_warning_recalc']=_('A database clean-up operation is running. If you close this page now, the operation will be interrupted.');
		
		$qa_content['script_onloads'][]=array(
			"qa_recalc_click('dorecalccategories', document.getElementById('dosaveoptions'), null, 'recalc_ok');"
		);
	}
	
	$qa_content['navigation']['sub']=qa_admin_sub_navigation();

	
	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/
