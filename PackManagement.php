<?php

namespace Nottingham\PackManagement;


class PackManagement extends \ExternalModules\AbstractExternalModule
{


	// Determine whether link to module configuration is shown.
	public function redcap_module_link_check_display( $project_id, $link )
	{
		if ( $this->canConfigure() )
		{
			return $link;
		}
		if ( $link['tt_name'] == 'module_link_config' )
		{
			return null;
		}
		if ( $this->canSeePacks() )
		{
			return $link;
		}
		return null;
	}


	// Always hide the button for the default REDCap module configuration interface.
	public function redcap_module_configure_button_display()
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



	// Check if the current user can see (some of) the packs for the project.
	public function canSeePacks()
	{
		$user = $this->getUser();
		if ( ! is_object( $user ) )
		{
			return false;
		}
		if ( $this->canConfigure() )
		{
			return true;
		}
		$userRights = $user->getRights();
		if ( $userRights === null || $userRights['role_id'] === null )
		{
			return false;
		}
		$roleName = $userRights['role_name'];
		$moduleName = $this->getModuleDirectoryBaseName();
		$queryRoles = $this->query( 'SELECT jtbl.role FROM redcap_external_module_settings ems ' .
		                            'JOIN redcap_external_modules em ON ems.external_module_id = ' .
		                            'em.external_module_id JOIN JSON_TABLE( JSON_EXTRACT( ' .
		                            'ems.value, \'$.roles_view[*]\', \'$.roles_dags[*]\', ' .
		                            '\'$.roles_invalid[*]\', \'$.roles_assign[*]\', ' .
		                            '\'$.roles_add[*]\', \'$.roles_edit\' ), \'$[*]\' ' .
		                            'COLUMNS ( `role` TEXT PATH \'$\' ) ) jtbl ' .
		                            'WHERE em.directory_prefix = ? AND ems.key LIKE ?',
		                            [ $moduleName, 'p' . $this->getProjectId() . '-packcat-%' ] );
		while ( $infoRole = $queryRoles->fetch_assoc() )
		{
			if ( $infoRole['role'] == $roleName )
			{
				return true;
			}
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



	// Get the list of pack field types, or the description of a specified type.
	public function getPackFieldTypes( $type = null )
	{
		$listFieldTypes =
			[ 'text' => preg_replace( '/\\(.*?\\)/', '', $GLOBALS['lang']['design_634'] ),
			  'date' => $GLOBALS['lang']['global_18'], 'datetime' => $GLOBALS['lang']['global_55'],
			  'time' => $GLOBALS['lang']['global_13'], 'integer' => $GLOBALS['lang']['design_86'] ];
		if ( $type !== null )
		{
			if ( isset( $listFieldTypes[ $type ] ) )
			{
				return $listFieldTypes[ $type ];
			}
			return null;
		}
		return $listFieldTypes;
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



	// Make the SQL query clause to get a packlist table.
	public function makePacklistSQL( $source )
	{
		return ' JSON_TABLE(' . $source . ',\'$[*]\' ' .
		       'COLUMNS( id TEXT PATH \'$.id\', ' .
		                'value TEXT PATH \'$.value\' DEFAULT \'""\' ON EMPTY, ' .
		                'block_id TEXT PATH \'$.block_id\' DEFAULT \'""\' ON EMPTY, ' .
		                'expiry DATETIME PATH \'$.expiry\', ' .
		                'extrafields JSON PATH \'$.extrafields\' DEFAULT \'[]\' ON EMPTY, ' .
		                'dag INT PATH \'$.dag\', ' .
		                'dag_rcpt TINYINT PATH \'$.dag_rcpt\' DEFAULT \'true\' ON EMPTY, ' .
		                'assigned TINYINT PATH \'$.assigned\' DEFAULT \'false\' ON EMPTY, ' .
		                'invalid TINYINT PATH \'$.invallid\' DEFAULT \'false\' ON EMPTY, ' .
		                'invalid_desc TEXT PATH \'$.invalid_desc\' DEFAULT \'""\' ON EMPTY ' .
		       ') ) AS packlist ';
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
			.mod-packmgmt-okmsg
			{
				max-width: 800px;
				color: #0a3622;
				background-color: #d1e7dd;
				border: solid 1px #a3cfbb;
				border-radius: 0.375rem;
				padding: 1rem;
			}
			.mod-packmgmt-errmsg
			{
				max-width: 800px;
				color: #58151c;
				background-color: #f8d7da;
				border: solid 1px #f1aeb5;
				border-radius: 0.375rem;
				padding: 1rem;
			}
			';
		echo '<script type="text/javascript">',
			 '(function (){var el = document.createElement(\'style\');',
			 'el.setAttribute(\'type\',\'text/css\');',
			 'el.innerText = \'', addslashes( preg_replace( "/[\t\r\n ]+/", ' ', $style ) ), '\';',
			 'document.getElementsByTagName(\'head\')[0].appendChild(el)})()</script>';
	}

}
