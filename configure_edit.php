<?php

namespace Nottingham\PackManagement;


if ( ! $module->canConfigure() )
{
	exit;
}


// Set default values for category configuration, or load from an existing entry / form submission.
$new = ( ( $_POST['action'] ?? '' ) == 'new' );
$hasError = false;
if ( $new )
{
	if ( $module->getSystemSetting( 'p' . $module->getProjectId() . '-packcat-' .
	                                $_POST['cat_id'] ) !== null )
	{
		header( 'Location: ' . $module->getUrl( 'configure.php' ) );
		exit;
	}
	$infoCategory = [ 'id' => $_POST['cat_id'] ?? '', 'enabled' => true, 'trigger' => '',
	                  'form' => '', 'logic' => '', 'nominim' => '', 'dags' => false,
	                  'blocks' => false, 'expire' => true, 'packfield' => '', 'datefield' => '',
	                  'countfield' => '', 'valuefield' => '', 'extrafields' => [],
	                  'roles_view' => [], 'roles_dags' => [], 'roles_invalid' => [],
	                  'roles_assign' => [], 'roles_add' => [], 'roles_edit' => [] ];
}
else
{
	if ( ! isset( $_GET['cat_id'] ) )
	{
		header( 'Location: ' . $module->getUrl( 'configure.php' ) );
		exit;
	}
	if ( empty( $_POST ) )
	{
		// Not a form submission, populate the category from the saved entry if it exists.
		$infoCategory = json_decode( $module->getSystemSetting( 'p' . $module->getProjectId() .
		                                                    '-packcat-' . $_GET['cat_id'] ), true );
		if ( $infoCategory === null )
		{
			header( 'Location: ' . $module->getUrl( 'configure.php' ) );
			exit;
		}
	}
	else
	{
		// Build the category object from the form submission, begin with the standard options.
		$infoCategory = [ 'id' => $_GET['cat_id'] ];
		foreach ( [ 'enabled', 'trigger', 'form', 'logic', 'nominim', 'dags', 'blocks', 'expire',
		            'packfield', 'datefield', 'countfield', 'valuefield' ] as $fieldName )
		{
			if ( in_array( $fieldName, [ 'enabled', 'dags', 'blocks', 'expire' ] ) )
			{
				$infoCategory[ $fieldName ] = ( $_POST[ $fieldName ] == '1' );
			}
			else
			{
				$infoCategory[ $fieldName ] = trim( str_replace( "\r\n", "\n",
				                                                 $_POST[ $fieldName ] ) );
			}
		}
		// Add the additional pack fields.
		$infoCategory['extrafields'] = [];
		$extraFieldCount = 1;
		while ( isset( $_POST[ 'f' . $extraFieldCount . '_name' ] ) )
		{
			if ( $_POST[ 'f' . $extraFieldCount . '_name' ] != '' &&
			     preg_match( '/^[a-z]([a-z0-9_-]*[a-z0-9])?$/',
			                 $_POST[ 'f' . $extraFieldCount . '_name' ] ) &&
			     ( $_POST[ 'f' . $extraFieldCount . '_label' ] ?? '' ) != '' &&
			     ( $_POST[ 'f' . $extraFieldCount . '_type' ] ?? '' ) != '' )
			{
				$infoCategory['extrafields'][ $_POST[ 'f' . $extraFieldCount . '_name' ] ] =
					[ 'label' => $_POST[ 'f' . $extraFieldCount . '_label' ],
					  'type' => $_POST[ 'f' . $extraFieldCount . '_type' ],
					  'required' => false, 'protected' => true,
					  'field' => ( $_POST[ 'f' . $extraFieldCount . '_field' ] ?? '' ) ];
			}
			$extraFieldCount++;
		}
		ksort( $infoCategory['extrafields'] );
		// Parse the role lists.
		foreach ( [ 'roles_view', 'roles_dags', 'roles_invalid', 'roles_assign',
		            'roles_add', 'roles_edit' ] as $fieldName )
		{
			$_POST[ $fieldName ] = trim( str_replace( "\r\n", "\n", $_POST[ $fieldName ] ) );
			$_POST[ $fieldName ] = preg_replace( "/\n\n+/", "\n", $_POST[ $fieldName ] );
			$infoCategory[ $fieldName ] = ( $_POST[ $fieldName ] == '' ? []
			                                : explode( "\n", $_POST[ $fieldName ] ) );
		}
		// Check that required fields have not been left blank.
		if ( ( $infoCategory['trigger'] == 'A' && $infoCategory['logic'] == '' ) ||
		     ( $infoCategory['trigger'] == 'F' && $infoCategory['form'] == '' ) ||
		     ( $infoCategory['trigger'] == 'M' &&
		       ! in_array( $infoCategory['nominim'], ['S', 'P'] ) ) ||
		     $infoCategory['packfield'] == '' ||
		     ! in_array( $infoCategory['trigger'], ['A', 'F', 'M'] ) )
		{
			$hasError = true;
		}
		$module->dbGetLock();
		// Check that there is not another minimization pack category already enabled for this
		// project. Multiple non-enabled minimization pack categories can co-exist but only one
		// can be enabled at any time.
		if ( $module->query( 'SELECT 1 FROM redcap_external_module_settings ems JOIN ' .
		                     'redcap_external_modules em ON ems.external_module_id = ' .
		                     'em.external_module_id WHERE em.directory_prefix = ? AND ems.`key` ' .
		                     'LIKE ? AND json_contains( ems.`value`, \'true\', \'$.enabled\' ) ' .
		                     'AND json_contains( ems.`value`, \'"M"\', \'$.trigger\' ) ' .
		                     'AND NOT json_contains( ems.`value`, ?, \'$.id\' )',
		                     [ $module->getModuleDirectoryBaseName(),
		                       'p' . $module->getProjectId() . '-packcat-%',
		                       '"' . $infoCategory['id'] . '"' ] )->fetch_assoc() )
		{
			$hasError = true;
		}
		// Submit the pack category if there are no errors.
		if ( $hasError === false )
		{
			$module->setSystemSetting( 'p' . $module->getProjectId() . '-packcat-' .
			                           $infoCategory['id'], json_encode( $infoCategory ) );
			if ( $module->getSystemSetting( 'p' . $module->getProjectId() . '-packlist-' .
			                                $infoCategory['id'] ) === null )
			{
				$module->setSystemSetting( 'p' . $module->getProjectId() . '-packlist-' .
				                           $infoCategory['id'], '[]' );
			}
			if ( $module->getSystemSetting( 'p' . $module->getProjectId() . '-packlog-' .
			                                $infoCategory['id'] ) === null )
			{
				$module->setSystemSetting( 'p' . $module->getProjectId() . '-packlog-' .
				                           $infoCategory['id'], '[]' );
			}
			$module->dbReleaseLock();
			header( 'Location: ' . $module->getUrl( 'configure.php' ) );
			exit;
		}
		$module->dbReleaseLock();
	}
}


// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();


?>
<div class="projhdr">
 <i class="fas fa-boxes-stacked"></i> <?php echo $module->tt('module_name'), "\n"; ?>
</div>
<form method="post" id="catform" action="<?php
echo $_GET['cat_id'] == '' ? $module->getUrl('configure_edit.php?cat_id=' . $_POST['cat_id']) : '';
?>">
 <table class="mod-packmgmt-formtable">
  <tbody>
   <tr><th colspan="2"><?php echo $module->tt('pack_category'); ?></th></tr>
   <tr>
    <td style="width:275px"><?php echo $module->tt('uniq_cat_name'); ?></td>
    <td>
     <?php echo $module->escape( $infoCategory['id'] ), "\n"; ?>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('enabled'); ?>*</td>
    <td>
     <select name="enabled" required>
      <option value="1"<?php echo $infoCategory['enabled'] ? ' selected' : ''; ?>><?php
                                                    echo $module->tt('opt_yes'); ?></option>
      <option value="0"<?php echo ! $infoCategory['enabled'] ? ' selected' : ''; ?>><?php
                                                    echo $module->tt('opt_no'); ?></option>
     </select>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('trigger_label'); ?>*</td>
    <td>
     <select name="trigger" required>
      <option value=""><?php echo $module->tt('select'); ?></option>
<?php
foreach ( [ 'A' => 'trigger_auto', 'F' => 'trigger_form', 'M' => 'trigger_minim' ] as $k => $v )
{
?>
      <option value="<?php echo $k; ?>"<?php echo $infoCategory['trigger'] == $k
                                    ? ' selected' : ''; ?>><?php echo $module->tt( $v ); ?></option>
<?php
}
?>
     </select>
    </td>
   </tr>
   <tr data-trigger-form="1">
    <td><?php echo $module->tt('form'); ?>*</td>
    <td>
     <select name="form" required>
      <option value=""><?php echo $module->tt('select'); ?></option>
<?php
foreach ( \REDCap::getInstrumentNames() as $k => $v )
{
?>
      <option value="<?php echo $module->escape( $k ); ?>"<?php echo $infoCategory['form'] == $k
                                ? ' selected' : ''; ?>><?php echo $module->escape( $v ); ?></option>
<?php
}
?>
     </select>
    </td>
   </tr>
   <tr data-trigger-auto="1" data-trigger-form="1" data-logic="1">
    <td><?php echo $module->tt('trig_logic'); ?><span>*</span></td>
    <td>
     <textarea name="logic" required><?php echo $module->escape( $infoCategory['logic'] ); ?></textarea>
    </td>
   </tr>
   <tr data-trigger-minim="1">
    <td><?php echo $module->tt('no_pack_for_minim'); ?>*</td>
    <td>
     <select name="nominim" required>
      <option value=""><?php echo $module->tt('select'); ?></option>
<?php
foreach ( [ 'S' => 'no_pack_for_minim_skip', 'P' => 'no_pack_for_minim_stop' ] as $k => $v )
{
?>
      <option value="<?php echo $k; ?>"<?php echo $infoCategory['nominim'] == $k
                                    ? ' selected' : ''; ?>><?php echo $module->tt( $v ); ?></option>
<?php
}
?>
     </select>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('packs_issue_dags'); ?>*</td>
    <td>
     <select name="dags" required>
      <option value="1"<?php echo $infoCategory['dags'] ? ' selected' : ''; ?>><?php
                                                    echo $module->tt('opt_yes'); ?></option>
      <option value="0"<?php echo ! $infoCategory['dags'] ? ' selected' : ''; ?>><?php
                                                    echo $module->tt('opt_no'); ?></option>
     </select>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('packs_group_blocks'); ?>*</td>
    <td>
     <select name="blocks" required>
      <option value="1"<?php echo $infoCategory['blocks'] ? ' selected' : ''; ?>><?php
                                                    echo $module->tt('opt_yes'); ?></option>
      <option value="0"<?php echo ! $infoCategory['blocks'] ? ' selected' : ''; ?>><?php
                                                    echo $module->tt('opt_no'); ?></option>
     </select>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('packs_have_expiry'); ?>*</td>
    <td>
     <select name="expire" required>
      <option value="1"<?php echo $infoCategory['expire'] ? ' selected' : ''; ?>><?php
                                                    echo $module->tt('opt_yes'); ?></option>
      <option value="0"<?php echo ! $infoCategory['expire'] ? ' selected' : ''; ?>><?php
                                                    echo $module->tt('opt_no'); ?></option>
     </select>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('pack_id_proj_field'); ?>*</td>
    <td>
     <?php echo $module->getProjectFieldsSelect( 'packfield', $infoCategory['packfield'],
                                                 'required' ), "\n"; ?>
    </td>
   </tr>
   <tr data-trigger-auto="1" data-trigger-form="1">
    <td><?php echo $module->tt('pack_date_proj_field'); ?></td>
    <td>
     <?php echo $module->getProjectFieldsSelect( 'datefield', $infoCategory['datefield'],
                                                 '', 'datetime' ), "\n"; ?>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('pack_count_proj_field'); ?></td>
    <td>
     <?php echo $module->getProjectFieldsSelect( 'countfield', $infoCategory['countfield'],
                                                 '', 'integer' ), "\n"; ?>
    </td>
   </tr>
   <tr data-trigger-auto="1" data-trigger-form="1">
    <td><?php echo $module->tt('pack_value_proj_field'); ?></td>
    <td>
     <?php echo $module->getProjectFieldsSelect( 'valuefield', $infoCategory['valuefield'],
                                                 '' ), "\n"; ?>
    </td>
   </tr>
  </tbody>
  <tbody data-section="additional-fields">
   <tr><td colspan="2">&nbsp;</td></tr>
   <tr data-additional-field="1">
    <td><?php echo $module->tt('pack_extra_field_name', '1'); ?><span></span></td>
    <td>
     <input type="text" name="f1_name" pattern="[a-z0-9_\-]+"
            value="<?php echo $module->escape( $infoCategory['f1_name'] ); ?>">
    </td>
   </tr>
   <tr data-additional-field="1">
    <td><?php echo $module->tt('pack_extra_field_label', '1'); ?><span></span></td>
    <td>
     <input type="text" name="f1_label"
            value="<?php echo $module->escape( $infoCategory['f1_label'] ); ?>">
    </td>
   </tr>
   <tr data-additional-field="1">
    <td><?php echo $module->tt('pack_extra_field_type', '1'); ?><span></span></td>
    <td>
     <select name="f1_type">
      <option value=""><?php echo $module->tt('select'); ?></option>
<?php
foreach ( $module->getPackFieldTypes() as $typeCode => $typeLabel )
{
?>
      <option value="<?php echo $typeCode; ?>"><?php echo $typeLabel; ?></option>
<?php
}
?>
     </select>
    </td>
   </tr>
   <tr data-additional-field="1">
    <td><?php echo $module->tt('pack_extra_field_field', '1'); ?></td>
    <td>
     <?php echo $module->getProjectFieldsSelect( 'f1_field', $infoCategory['f1_field'],
                                                 '' ), "\n"; ?>
    </td>
   </tr>
  </tbody>
  <tbody>
   <tr>
    <td>&nbsp;</td>
    <td>
     <input type="button" value="<?php echo $module->tt('add'); ?>"
            id="addextrafield" style="width:unset">
    </td>
   </tr>
   <tr><td colspan="2">&nbsp;</td></tr>
   <tr>
    <td><?php echo $module->tt('roles_view_packs'); ?></td>
    <td>
     <textarea name="roles_view" style="height:50px"><?php
	echo implode( '&#10;', $module->escape( $infoCategory['roles_view'] ) ); ?></textarea>
    </td>
   </tr>
   <tr data-assign-dags="1">
    <td><?php echo $module->tt('roles_issue_packs'); ?></td>
    <td>
     <textarea name="roles_dags" style="height:50px"><?php
	echo implode( '&#10;', $module->escape( $infoCategory['roles_dags'] ) ); ?></textarea>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('roles_mark_packs_invalid'); ?></td>
    <td>
     <textarea name="roles_invalid" style="height:50px"><?php
	echo implode( '&#10;', $module->escape( $infoCategory['roles_invalid'] ) ); ?></textarea>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('roles_assign_packs'); ?></td>
    <td>
     <textarea name="roles_assign" style="height:50px"><?php
	echo implode( '&#10;', $module->escape( $infoCategory['roles_assign'] ) ); ?></textarea>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('roles_add_packs'); ?></td>
    <td>
     <textarea name="roles_add" style="height:50px"><?php
	echo implode( '&#10;', $module->escape( $infoCategory['roles_add'] ) ); ?></textarea>
    </td>
   </tr>
   <tr>
    <td><?php echo $module->tt('roles_edit_delete_packs'); ?></td>
    <td>
     <textarea name="roles_edit" style="height:50px"><?php
	echo implode( '&#10;', $module->escape( $infoCategory['roles_edit'] ) ); ?></textarea>
    </td>
   </tr>
   <tr>
    <td></td>
    <td><span class="field-desc"><?php echo $module->tt('roles_setting_note'); ?></span></td>
   </tr>
  </tbody>
  <tbody>
   <tr><td colspan="2">&nbsp;</td></tr>
   <tr>
    <td></td>
    <td>
     <input type="submit" value="<?php echo $module->tt('save'); ?>">
    </td>
   </tr>
  </tbody>
 </table>
</form>
<script type="text/javascript">
 $('[name="trigger"]').change( function()
 {
   var vVal = $(this).val()
   $('[data-trigger-auto]').css('display', vVal == 'A' ? '' : 'none')
   $('[data-trigger-auto] [' + ( vVal == 'A' ? 'data-' : '' ) + 'required]')
     .attr('data-required', ( vVal == 'A' ? null : '1' )).prop('required', vVal == 'A')
   var vElems = '[data-trigger-form]'
   if ( vVal == 'A' )
   {
     vElems += ':not([data-trigger-auto])'
   }
   $(vElems).css('display', vVal == 'F' ? '' : 'none')
   $(vElems + ' [' + ( vVal == 'F' ? 'data-' : '' ) + 'required]')
     .attr('data-required', ( vVal == 'F' ? null : '1' )).prop('required', vVal == 'F')
   $('[data-trigger-minim]').css('display', vVal == 'M' ? '' : 'none')
   $('[data-trigger-minim] [' + ( vVal == 'M' ? 'data-' : '' ) + 'required]')
     .attr('data-required', ( vVal == 'M' ? null : '1' )).prop('required', vVal == 'M')
   $('[data-logic] td:first-child span').text( vVal == 'A' ? '*' : '' )
   if ( vVal != 'A' )
   {
     $('[data-logic] textarea').attr('data-required', '1').prop('required', false)
   }
 } )
 $('[name="trigger"]').change()
 $('[name="dags"]').change( function()
 {
   var vVal = $(this).val()
   $('[data-assign-dags]').css('display', vVal == '1' ? '' : 'none')
 } )
 $('[name="dags"]').change()
 var vFuncAP = function()
 {
   var vFieldNum = $(this).closest('tr').attr('data-additional-field')
   if ( $('[name="f' + vFieldNum + '_name"]').val() + $('[name="f' + vFieldNum + '_label"]').val() +
        $('[name="f' + vFieldNum + '_type"]').val() +
        $('[name="f' + vFieldNum + '_field"]').val() == '' )
   {
     $('[name="f' + vFieldNum + '_name"], [name="f' + vFieldNum + '_label"], ' +
       '[name="f' + vFieldNum + '_type"]').prop('required', false)
     $('[data-additional-field="' + vFieldNum + '"]').slice(0, 3)
       .find('td span:nth-of-type(2)').text('')
   }
   else
   {
     $('[name="f' + vFieldNum + '_name"], [name="f' + vFieldNum + '_label"], ' +
       '[name="f' + vFieldNum + '_type"]').prop('required', true)
     $('[data-additional-field="' + vFieldNum + '"]').slice(0, 3)
       .find('td span:nth-of-type(2)').text('*')
   }
 }
 $('[name="f1_name"],[name="f1_label"],[name="f1_type"],[name="f1_field"]').change(vFuncAP)
 $('#addextrafield').click( function()
 {
   var vLastFieldNum = $('[data-additional-field]').last().attr('data-additional-field')
   var vThisFieldNum = vLastFieldNum - 0 + 1
   var vNewRows = $('[data-additional-field="' + vLastFieldNum + '"]').clone()
   vNewRows.attr('data-additional-field', vThisFieldNum)
   vNewRows.find('td:first-child span:nth-of-type(1)').text(vThisFieldNum)
   vNewRows.find('td:first-child span:nth-of-type(2)').text('')
   vNewRows.find('input, select').each( function()
   {
     $(this).attr('name',$(this).attr('name').replace( 'f' + vLastFieldNum + '_',
                                                       'f' + vThisFieldNum + '_') )
     $(this).val('')
     $(this).change(vFuncAP)
   } )
   vNewRows.appendTo('[data-section="additional-fields"]')
 } )
 var vExtraFields = $('<div></div>').html('<?php
echo $module->escape( json_encode( $infoCategory['extrafields'] ) ); ?>').text()
 vExtraFields = JSON.parse( vExtraFields )
 var vExtraFieldNames = Object.keys(vExtraFields)
 for ( var i = 0; i < vExtraFieldNames.length; i++ )
 {
   if ( i > 0 )
   {
     $('#addextrafield').click()
   }
   var vExtraField = vExtraFields[ vExtraFieldNames[i] ]
   $('[name="f' + (i + 1) + '_name"]').val( vExtraFieldNames[i] )
   $('[name="f' + (i + 1) + '_label"]').val( vExtraField.label )
   $('[name="f' + (i + 1) + '_type"]').val( vExtraField.type )
   $('[name="f' + (i + 1) + '_field"]').val( vExtraField.field )
   $('[name="f' + (i + 1) + '_name"]').change()
 }
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';