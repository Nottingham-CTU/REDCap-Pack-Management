<?php

namespace Nottingham\PackManagement;


// Get the pack category.
$queryCategory = $module->query( 'SELECT ems.`value` FROM redcap_external_module_settings ems ' .
                                 'JOIN redcap_external_modules em ON ems.external_module_id = ' .
                                 'em.external_module_id JOIN JSON_TABLE( ems.`value`, \'$\' ' .
                                 'COLUMNS( id TEXT PATH \'$.id\' ) ) jtbl WHERE ' .
                                 'em.directory_prefix = ? AND ems.`key` LIKE ? AND jtbl.id = ?',
                                 [ $module->getModuleDirectoryBaseName(),
                                  'p' . $module->getProjectId() . '-packcat-%', $_GET['cat_id'] ] );
$infoCategory = $queryCategory->fetch_assoc();
if ( $infoCategory == null )
{
	exit;
}
$infoCategory = json_decode( $infoCategory['value'], true );


// Get the user role.
$canConfigure = $module->canConfigure();
$roleName = null;
$userRights = $module->getUser()->getRights();
if ( $userRights !== null && $userRights['role_id'] !== null )
{
	$roleName = $userRights['role_name'];
}


// Check that the user has the rights to view this page (they must be able to do at least 1 of:
// view packs, issue to DAGs, mark as invalid, manually (re)assign, edit/delete).
if ( ! $canConfigure && ! in_array( $roleName, $infoCategory['roles_view'] ) &&
     ! in_array( $roleName, $infoCategory['roles_dags'] ) &&
     ! in_array( $roleName, $infoCategory['roles_invalid'] ) &&
     ! in_array( $roleName, $infoCategory['roles_assign'] ) &&
     ! in_array( $roleName, $infoCategory['roles_edit'] ) )
{
	exit;
}


// Get the project DAGs.
$listDAGs = \REDCap::getGroupNames();


// Get the full list of packs in the category.
$queryPacks = $module->query( 'SELECT id, block_id, expiry, dag, dag_rcpt, assigned, invalid ' .
                              'FROM redcap_external_module_settings ems, redcap_external_modules ' .
                              'em, ' . $module->makePacklistSQL('ems.value') .
                              'WHERE em.external_module_id = ems.external_module_id ' .
                              'AND em.directory_prefix = ? AND ems.key = ?',
                             [ $module->getModuleDirectoryBaseName(),
                              'p' . $module->getProjectId() . '-packlist-' . $infoCategory['id'] ]);
$listPacks = [];
$totalPacks = 0;
$availablePacks = 0;
$assignedPacks = 0;
$invalidPacks = 0;
$expiredPacks = 0;
while ( $infoPack = $queryPacks->fetch_assoc() )
{
	// If packs are assigned to DAGs and user is in a DAG, only show packs in that DAG.
	if ( $infoCategory['dags'] && $userRights['group_id'] != '' &&
	     $userRights['group_id'] != $infoPack['dag'] )
	{
		continue;
	}
	$listPacks[] = $infoPack;
	$totalPacks++;
	if ( $infoPack['assigned'] )
	{
		$assignedPacks++;
	}
	elseif ( $infoPack['invalid'] )
	{
		$invalidPacks++;
	}
	elseif ( $infoPack['expiry'] != '' && $infoPack['expiry'] < date('Y-m-d H:i:s') )
	{
		$expiredPacks++;
	}
	else
	{
		$availablePacks++;
	}
}


// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();


?>
<div class="projhdr">
 <i class="fas fa-boxes-stacked"></i> <?php echo $module->tt('module_name'), "\n"; ?>
</div>

<p>
 <a href="<?php echo $module->getUrl('packs.php'); ?>">
  <i class="far fa-circle-left"></i> <?php echo $module->tt('back'), "\n"; ?>
 </a>
</p>

<p>&nbsp;</p>

<?php
if ( ! empty( $_POST ) )
{
	if ( empty( $listErrors ) )
	{
		// TODO: Add message text.
		echo '<div class="mod-packmgmt-okmsg"><p>', '', '</p></div>';
	}
	else
	{
		// TODO: Add message text.
		echo '<div class="mod-packmgmt-errmsg"><p>', '', '</p><ul>';
		foreach ( $listErrors as $infoError )
		{
			echo '<li>', $module->tt( ...$infoError ), '</li>';
		}
		echo '</ul></div>';
	}
}
?>

<p style="font-size:1.3em">
 <?php echo $module->tt('view_packs'), ' &#8211; ', $module->escape( $infoCategory['id'] ), "\n"; ?>
</p>

<p>
 <?php echo $module->tt('total_packs'), ' ', $totalPacks, '<br>',
            $module->tt('available_packs'), ' ', $availablePacks, '<br>',
            $module->tt('assigned_packs'), ' ', $assignedPacks,
            $invalidPacks == 0 ? '' : ( '<br>' . $module->tt('invalid_packs') . $invalidPacks ),
            $expiredPacks == 0 ? '' : ( '<br>' . $module->tt('expired_packs') . $expiredPacks ); ?>

</p>

<table class="mod-packmgmt-listtable">
 <tr>
  <th style="width:45px"></th>
  <th><?php echo $module->tt('packfield_id'); ?></th>
<?php
if ( $infoCategory['blocks'] )
{
?>
  <th><?php echo $module->tt('packfield_block_id'); ?></th>
<?php
}
if ( $infoCategory['dags'] && $userRights['group_id'] == '' )
{
?>
  <th><?php echo $module->tt('dag'); ?></th>
<?php
}
if ( $infoCategory['expire'] )
{
?>
  <th><?php echo $module->tt('packfield_expiry'); ?></th>
<?php
}
?>
 </tr>
<?php
$row = 0;
foreach ( $listPacks as $infoPack )
{
?>
 <tr>
  <td style="text-align:center">
   <input type="checkbox" name="pack_id" data-pack-chkbx="<?php echo ++$row; ?>"
             value="<?php echo $module->escape( $infoPack['id'] ); ?>"
             data-block-id="<?php echo $module->escape( $infoPack['block_id'] ); ?>"
             data-assigned="<?php echo $infoPack['assigned'] ? 'true' : 'false'; ?>"
             data-invalid="<?php echo $infoPack['invalid'] ? 'true' : 'false'; ?>"
             data-dag="<?php echo $infoPack['dag'] ?? ''; ?>"
             title="<?php echo $module->tt('tooltip_chkbx_shift'); ?>">
  </td>
  <td>
   <?php echo $module->escape( $infoPack['id'] ), "\n"; ?>
   <span style="float:right">
    <?php echo $infoPack['assigned'] ? ( '<i class="far fa-square-check" title="' .
                                         $module->tt('tooltip_pack_assigned') . '"></i>' ) : ''; ?>
    <?php echo $infoPack['invalid'] ? ( '<i class="fas fa-ban" title="' .
                                        $module->escape( $infoPack['invalid_desc'] ) .
                                        '"></i>&nbsp;' ) : '', "\n"; ?>
   </span>
  </td>
<?php
	if ( $infoCategory['blocks'] )
	{
?>
  <td><?php echo $module->escape( $infoPack['block_id'] ); ?></td>
<?php
	}
	if ( $infoCategory['dags'] && $userRights['group_id'] == '' )
	{
?>
  <td><?php echo $infoPack['dag'] == '' ? '&#8212;'
                                        : $module->escape( $listDAGs[ $infoPack['dag'] ] ),
                 $infoPack['dag_rcpt'] ? '' : ' <i class="fas fa-truck"></i>'; ?></td>
<?php
	}
	if ( $infoCategory['expire'] )
	{
?>
  <td><?php echo $module->escape( \DateTimeRC::format_ts_from_ymd( $infoPack['expiry'] ) ); ?></td>
<?php
	}
?>
 </tr>
<?php
}
?>
</table>

<p>&nbsp;</p>

<?php
if ( $canConfigure || ( in_array( $roleName, $infoCategory['roles_dags'] ) &&
                        $infoCategory['dags'] && $userRights['group_id'] == '' ) ||
                      in_array( $roleName, $infoCategory['roles_invalid'] ) ||
                      in_array( $roleName, $infoCategory['roles_assign'] ) )
{
?>
<p style="font-size:1.3em">
 <?php echo $module->tt('with_selected_packs'), "\n"; ?>
</p>
<?php
	if ( $canConfigure || ( in_array( $roleName, $infoCategory['roles_dags'] ) &&
	                        $infoCategory['dags'] && $userRights['group_id'] == '' ) )
	{
?>
<form method="post" class="packmgmt-packissue">
 <table class="mod-packmgmt-formtable" style="margin-bottom:5px">
  <tr>
   <th colspan="2"><?php echo $module->tt('issue_unissue_packs'); ?></th>
  </tr>
  <tr>
   <td colspan="2" class="errmsg" style="color:#58151c;display:none;text-align:left">
    <?php echo $module->tt('issue_unissue_packs_error'), "\n"; ?>
   </td>
  </tr>
  <tr>
   <td><?php echo $module->tt('dag'); ?></td>
   <td>
    <select name="">
     <option value=""><?php echo $module->tt('opt_none'); ?></option>
<?php
		foreach ( $listDAGs as $dagID => $dagName )
		{
?>
     <option value="<?php echo $dagID; ?>"><?php echo $module->escape( $dagName ); ?></option>
<?php
		}
?>
    </select>
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="hidden" name="action" value="issue">
    <input type="submit" value="<?php echo $module->tt('save'); ?>">
   </td>
  </tr>
 </table>
</form>
<?php
	}
	if ( $canConfigure || in_array( $roleName, $infoCategory['roles_invalid'] ) )
	{
?>
<form method="post" class="packmgmt-packinvalid">
 <table class="mod-packmgmt-formtable" style="margin-bottom:5px">
  <tr>
   <th colspan="2"><?php echo $module->tt('mark_unmark_packs_invalid'); ?></th>
  </tr>
 </table>
</form>
<?php
	}
	if ( $canConfigure || in_array( $roleName, $infoCategory['roles_assign'] ) )
	{
?>
<form method="post" class="packmgmt-packassign">
 <table class="mod-packmgmt-formtable" style="margin-bottom:5px">
  <tr>
   <th colspan="2"><?php echo $module->tt('assign_reassign_packs'); ?></th>
  </tr>
 </table>
</form>
<?php
	}
}
?>

<script type="text/javascript">
$(function()
{
  var vLastChecked = 0
  var vMultiCheck = false
  $('[data-pack-chkbx]').click(function( event )
  {
    var vCB = $(this)
    vCB.closest('tr').css( 'background-color', ( vCB.prop('checked') ? '#ccffcc' : '' ) )
    if ( ! vMultiCheck && event.shiftKey && vLastChecked > 0 )
    {
      vMultiCheck = true
      vCBNum = vCB.attr('data-pack-chkbx') - 0
      $('[data-pack-chkbx]').each( function()
      {
        var vIndex = $(this).attr('data-pack-chkbx') - 0
        if ( ( ( vIndex < vLastChecked && vIndex > vCBNum ) ||
               ( vIndex > vLastChecked && vIndex < vCBNum ) ) &&
             $(this).prop('checked') != vCB.prop('checked') )
        {
          $(this).click()
        }
      })
      vMultiCheck = false
      vLastChecked = vCB.attr('data-pack-chkbx') - 0
    }
    else if ( ! vMultiCheck )
    {
      vLastChecked = event.shiftKey ? ( vCB.attr('data-pack-chkbx') - 0 ) : 0
    }
    if ( $('.packmgmt-packissue').length > 0 )
    {
      if ( $('[data-pack-chkbx]:checked').map(function(){return $(this).attr('data-block-id')})
           .filter($('[data-pack-chkbx]:not(:checked)').map(function(){return $(this)
           .attr('data-block-id')})).get().length == 0 &&
           [...new Set($('[data-pack-chkbx]').map(function(){return $(this).attr('data-dag')})
           .get())].length == 1 )
      {
        $('.packmgmt-packissue .errmsg').css('display','none')
        $('.packmgmt-packissue input, .packmgmt-packissue select').prop('disabled',false)
      }
      else
      {
        $('.packmgmt-packissue .errmsg').css('display','')
        $('.packmgmt-packissue input, .packmgmt-packissue select').prop('disabled',true)
      }
    }
    if ( $('.packmgmt-packinvalid').length > 0 )
    {
      //
    }
    if ( $('.packmgmt-packassign').length > 0 )
    {
      //
    }
  })
})
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';