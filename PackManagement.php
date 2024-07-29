<?php

namespace Nottingham\PackManagement;


class PackManagement extends \ExternalModules\AbstractExternalModule
{


	// Determine whether link to module configuration is shown.
	function redcap_module_link_check_display( $project_id, $link )
	{
		if ( $this->canConfigure() )
		{
			return $link;
		}
		return null;
	}


	// Always hide the button for the default REDCap module configuration interface.
	function redcap_module_configure_button_display()
	{
		return ( $this->getProjectId() === null ) ? true : null;
	}



	// Check if the current user can configure the module settings for the project.
	public function canConfigure()
	{
		$user = $this->getUser();
		if ( ! is_object( $user ) )
		{
			return false;
		}
		if ( $user->isSuperUser() )
		{
			return true;
		}
		$userRights = $user->getRights();
		$specificRights = ( $this->getSystemSetting( 'config-require-user-permission' ) == 'true' );
		$moduleName = preg_replace( '/_v[0-9.]+$/', '', $this->getModuleDirectoryName() );
		if ( $specificRights && is_array( $userRights['external_module_config'] ) &&
		     in_array( $moduleName, $userRights['external_module_config'] ) )
		{
			return true;
		}
		if ( ! $specificRights && $userRights['design'] == '1' )
		{
			return true;
		}
		return false;
	}



	// Functions to get/release database lock.
	public function dbGetLock()
	{
		$this->query( 'DO GET_LOCK(?,60)', [ $GLOBALS['db'] . '.pack_management' ] );
	}

	public function dbReleaseLock()
	{
		$this->query( 'DO RELEASE_LOCK(?)', [ $GLOBALS['db'] . '.pack_management' ] );
	}



	// Get module directory name, without version number.
	public function getModuleDirectoryBaseName()
	{
		return preg_replace( '/_v[0-9.]+$/', '', $this->getModuleDirectoryName() );
	}



	// Get the project fields which can be used for storing pack fields into the records.
	public function getProjectFields( $type = '' )
	{
		$listAllFields = \REDCap::getDataDictionary( $this->getProjectId(), 'array' );
		$listFields = [];
		foreach ( $listAllFields as $field )
		{
			if ( ( $field['field_type'] == 'text' || $field['field_type'] == 'notes' ) &&
			     strpos( $field['field_annotation'], '@CALC' ) === false )
			{
				$fieldValidation = $field['text_validation_type_or_show_slider_number'];
				if ( $type == '' || $fieldValidation == '' ||
				     ( $type == 'integer' && ( $fieldValidation == 'integer' ||
				                               strpos( $fieldValidation, 'number' ) !== false ) ) ||
				     ( $type == 'datetime' && strpos( $fieldValidation, 'datetime' ) !== false ) )
				{
					$listFields[ $field['field_name'] ] = strip_tags( $field['field_label'] );
				}
			}
		}
		return $listFields;
	}



	// Get the project fields as a drop-down.
	public function getProjectFieldsSelect( $name, $value = '', $attrs = '', $type = '' )
	{
		$output = '<select name="' . $this->escape( $name ) . '" ' . $attrs . '>';
		$output .= '<option value="">' . $this->tt('select') . '</option>';
		foreach ( $this->getProjectFields( $type ) as $fieldName => $label )
		{
			if ( strlen( $label ) > 50 )
			{
				$label = substr( $label, 0, 35 ) . ' ... ' . substr( $label, -10 );
			}
			$label = $fieldName . ' - ' . $label;
			$output .= '<option value="' . $this->escape( $fieldName ) . '"';
			if ( $fieldName == $value )
			{
				$output .= ' selected';
			}
			$output .= '>';
			$output .= $this->escape( $label ) . '</option>';
		}
		$output .= '</select>';
		return $output;
	}



	// CSS style for Pack Management pages.
	public function writeStyle()
	{
		$style = '
			.mod-packmgmt-formtable
			{
				width: 97%;
				border: solid 1px #000;
			}
			.mod-packmgmt-formtable th
			{
				padding: 5px;
				font-size: 130%;
				font-weight: bold;
			}
			.mod-packmgmt-formtable td
			{
				padding: 5px;
			}
			.mod-packmgmt-formtable td:first-child
			{
				width: 200px;
				padding-top: 7px;
				padding-right: 8px;
				text-align:right;
				vertical-align: top;
			}
			.mod-packmgmt-formtable input:not([type=submit]):not([type=radio]):not([type=checkbox])
			{
				width: 95%;
				max-width: 600px;
			}
			.mod-packmgmt-formtable textarea
			{
				width: 95%;
				max-width: 600px;
				height: 100px;
			}
			.mod-packmgmt-formtable label
			{
				margin-bottom: 0px;
			}
			.mod-packmgmt-formtable span.field-desc
			{
				font-size: 90%;
			}
			.mod-packmgmt-listtable
			{
				width: 97%;
				border: solid 1px #000;
				border-collapse: collapse;
			}
			.mod-packmgmt-listtable th
			{
				padding: 8px 5px;
				font-weight: bold;
				border: solid 1px #000;
			}
			.mod-packmgmt-listtable td
			{
				padding: 3px;
				border: solid 1px #000;
			}
			';
		echo '<script type="text/javascript">',
			 '(function (){var el = document.createElement(\'style\');',
			 'el.setAttribute(\'type\',\'text/css\');',
			 'el.innerText = \'', addslashes( preg_replace( "/[\t\r\n ]+/", ' ', $style ) ), '\';',
			 'document.getElementsByTagName(\'head\')[0].appendChild(el)})()</script>';
	}

}
