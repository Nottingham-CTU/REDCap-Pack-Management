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


if ( ! $canConfigure && ! in_array( $roleName, $infoCategory['roles_add'] ) )
{
	exit;
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

<p style="font-size:1.3em">
 <?php echo $module->tt('add_packs'), ' &#8211; ', $module->escape( $infoCategory['id'] ), "\n"; ?>
</p>

<div id="addpackstabs" style="width:97%">
 <ul>
  <li><a href="#singlepack"><?php echo $module->tt('add_single_pack'); ?></a></li>
  <li><a href="#multiplepacks"><?php echo $module->tt('add_multiple_packs'); ?></a></li>
 </ul>
 <div id="singlepack">
  <form method="post">
   <table class="mod-packmgmt-formtable">
    <tr>
     <td><?php echo $module->tt('packfield_id'); ?> *</td>
     <td><input type="text" name="id" required></td>
    </tr>
    <tr>
<?php
if ( $infoCategory['trigger'] == 'M' || $infoCategory['valuefield'] != '' )
{
?>
     <td><?php echo $module->tt( 'packfield_value' .
                                 ( $infoCategory['trigger'] == 'M' ? '_minim' : '' ) ); ?> *</td>
     <td><input type="text" name="value" required></td>
<?php
}
else
{
?>
     <td><?php echo $module->tt( 'packfield_value' ); ?></td>
     <td><input type="text" name="value"></td>
<?php
}
?>
    </tr>
<?php
if ( $infoCategory['blocks'] )
{
?>
    <tr>
     <td><?php echo $module->tt('packfield_block_id'); ?> *</td>
     <td><input type="text" name="block_id" required></td>
    </tr>
<?php
}
?>
<?php
if ( $infoCategory['expire'] )
{
?>
    <tr>
     <td><?php echo $module->tt('packfield_expiry'); ?> *</td>
     <td><input type="datetime-local" name="expiry" required></td>
    </tr>
<?php
}
foreach ( $infoCategory['extrafields'] as $extraFieldName => $infoExtraField )
{
	$extraFieldLabel = $module->escape( $infoExtraField['label'] );
	$extraFieldType = '"text"';
	if ( $infoExtraField['type'] == 'date' )
	{
		$extraFieldType = '"date"';
	}
	elseif ( $infoExtraField['type'] == 'datetime' )
	{
		$extraFieldType = '"datetime-local"';
	}
	elseif ( $infoExtraField['type'] == 'integer' )
	{
		$extraFieldType = '"number"';
	}
	elseif ( $infoExtraField['type'] == 'time' )
	{
		$extraFieldType = '"time"';
	}
	if ( $infoExtraField['required'] )
	{
		$extraFieldLabel .= ' *';
		$extraFieldType .= ' required';
	}
?>
    <tr>
     <td><?php echo $extraFieldLabel; ?></td>
     <td><input type=<?php echo $extraFieldType; ?>
                name="<?php echo $module->escape( $extraFieldName ); ?>"></td>
    </tr>
<?php
}
?>
    <tr>
     <td></td>
     <td><input type="submit" value="<?php echo $module->tt('save'); ?>"></td>
    </tr>
   </table>
  </form>
 </div>
 <div id="multiplepacks">
  <p><?php echo $module->tt('add_multiple_packs_info'); ?></p>
  <table class="mod-packmgmt-listtable">
   <tr>
    <th><?php echo $module->tt('field'); ?></th>
    <th><?php echo $module->tt('description'); ?></th>
    <th><?php echo $module->tt('type'); ?></th>
    <th><?php echo $module->tt('required'); ?></th>
   </tr>
   <tr>
    <td>id</td>
    <td><?php echo $module->tt('packfield_id'); ?></td>
    <td><?php echo $module->getPackFieldTypes('text'); ?></td>
    <td><?php echo $module->tt('opt_yes'); ?></td>
   </tr>
   <tr>
    <td>value</td>
    <td><?php echo $module->tt( 'packfield_value' .
                                ( $infoCategory['trigger'] == 'M' ? '_minim' : '' ) ); ?></td>
    <td><?php echo $module->getPackFieldTypes('text'); ?></td>
    <td><?php echo $module->tt( $infoCategory['trigger'] == 'M' || $infoCategory['valuefield'] != ''
                                ? 'opt_yes' : 'opt_no' ); ?></td>
   </tr>
<?php
if ( $infoCategory['blocks'] )
{
?>
   <tr>
    <td>block_id</td>
    <td><?php echo $module->tt('packfield_block_id'); ?></td>
    <td><?php echo $module->getPackFieldTypes('text'); ?></td>
    <td><?php echo $module->tt('opt_yes'); ?></td>
   </tr>
<?php
}
if ( $infoCategory['expire'] )
{
?>
   <tr>
    <td>expiry</td>
    <td><?php echo $module->tt('packfield_expiry'); ?></td>
    <td><?php echo $module->getPackFieldTypes('datetime'); ?></td>
    <td><?php echo $module->tt('opt_yes'); ?></td>
   </tr>
<?php
}
foreach ( $infoCategory['extrafields'] as $extraFieldName => $infoExtraField )
{
?>
   <tr>
    <td><?php echo $module->escape( $extraFieldName ); ?></td>
    <td><?php echo $module->escape( $infoExtraField['label'] ); ?></td>
    <td><?php echo $module->getPackFieldTypes( $infoExtraField['type'] ); ?></td>
    <td><?php echo $module->tt( $infoExtraField['required'] ? 'opt_yes' : 'opt_no' ); ?></td>
   </tr>
<?php
}
?>
  </table>
  <p>&nbsp;</p>
  <form method="post" enctype="multipart/form-data">
   <p><b><?php echo $module->tt('add_multiple_packs_upload'); ?></b></p>
   <p>
    <input type="file" name="packs_upload">
    &nbsp;&nbsp; <?php echo $module->tt('csv_delimiter'); ?>:
    <select name="packs_upload_delimiter">
     <option value=","><?php echo $module->tt('csv_comma'); ?></option>
     <option value="&#09;"><?php echo $module->tt('csv_tab'); ?></option>
     <option value=";"><?php echo $module->tt('csv_semicolon'); ?></option>
    </select>
    <br><input type="submit" value="<?php echo $module->tt('upload'); ?>" style="margin-top:2px">
   </p>
  </form>
 </div>
</div>
<script type="text/javascript">
 $('#addpackstabs').tabs()
 $('head').append('<style type="text/css">.ui-tabs .ui-tabs-nav li.ui-tabs-active .ui-tabs-anchor' +
                  '{cursor:default;font-weight:bold;color:#000}</style>')
</script>

<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';