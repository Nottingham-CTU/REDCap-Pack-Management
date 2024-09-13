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


// Get the full list of packs in the category.
$queryPacks = $module->query( 'SELECT id, block_id, expiry, dag, dag_rcpt, assigned, invalid ' .
                              'FROM redcap_external_module_settings ems, redcap_external_modules ' .
                              'em, ' . $module->makePacklistSQL('ems.value') .
                              'WHERE em.external_module_id = ems.external_module_id ' .
                              'AND em.directory_prefix = ? AND ems.key = ?',
                             [ $module->getModuleDirectoryBaseName(),
                              'p' . $module->getProjectId() . '-packlist-' . $infoCategory['id'] ]);
$listPacks = [];
while ( $infoPack = $queryPacks->fetch_assoc() )
{
	// TODO: If packs are assigned to DAGs and user is in a DAG, show only packs in that DAG.
	$listPacks[] = $infoPack;
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
             title="<?php echo $module->tt('tooltip_chkbx_shift'); ?>">
  </td>
  <td><?php echo $module->escape( $infoPack['id'] ); ?></td>
<?php
	if ( $infoCategory['blocks'] )
	{
?>
  <td><?php echo $module->escape( $infoPack['block_id'] ); ?></td>
<?php
	}
	if ( $infoCategory['expiry'] )
	{
?>
  <td><?php echo $module->escape( $infoPack['expiry'] ); ?></td>
<?php
	}
?>
 </tr>
<?php
}
?>
</table>

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
  })
})
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';