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



	// Run whenever a record is saved. This will trigger pack assignments where the assignment
	// mode is Automatic, or Form submission for the current form.
	public function redcap_save_record( $projectID, $recordID, $instrument, $eventID, $groupID,
	                                    $surveyHash, $responseID, $repeatInstance )
	{
		$this->dbGetLock();
		// Get the pack categories which are relevant to this submission.
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) AS id, ' .
		                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.logic\')) AS logic, ' .
		                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) ' .
		                          'AS packfield, JSON_UNQUOTE(JSON_EXTRACT(`value`,' .
		                          '\'$.valuefield\')) AS valuefield ' .
		                          'FROM redcap_external_module_settings ems ' .
		                          'JOIN redcap_external_modules em ' .
		                          'ON ems.external_module_id = em.external_module_id ' .
		                          'WHERE em.directory_prefix = ? AND ems.`key` LIKE ? ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\') ' .
		                          'AND ( JSON_CONTAINS(`value`,\'"A"\',\'$.trigger\') ' .
		                            'OR ( JSON_CONTAINS(`value`,\'"F"\',\'$.trigger\') ' .
		                              'AND JSON_CONTAINS(`value`,?,\'$.form\') ) )',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%',
		                            json_encode( $instrument ) ] );
		$listCat = [];
		while ( $infoCat = $queryCat->fetch_assoc() )
		{
			// Check that the pack field on the record/event/instance is empty and that the
			// logic (if specified) evaluates as true.
			if ( $this->getValues( $projectID, $recordID, $eventID, $repeatInstance,
			                       $infoCat['packfield'] )[ $infoCat['packfield'] ] == '' &&
			     ( $infoCat['logic'] == '' ||
			       \REDCap::evaluateLogic( $infoCat['logic'], $projectID, $recordID, $eventID,
			                               $repeatInstance, $instrument, $instrument ) ) )
			{
				$listCat[ $infoCat['id'] ] = $infoCat['valuefield'];
			}
		}
		foreach ( $listCat as $catID => $valueField )
		{
			// Get and assign pack for the pack category.
			$packValue = null;
			if ( $valueField != '' )
			{
				$packValue = $this->getValues( $projectID, $recordID, $eventID,
				                               $repeatInstance, $valueField )[ $valueField ];
			}
			$infoData = $this->choosePack( $catID, $recordID, $packValue );
			// Save the data to the record.
			if ( $infoData !== false )
			{
				$this->updateValues( $projectID, $recordID, $eventID, $repeatInstance, $infoData );
			}
		}
		$this->dbReleaseLock();
	}



	// Run on schedule to trigger pack assignments automatically where required.
	public function autoAssignmentCron( $infoCron )
	{
		$startTimestamp = time();
		$activeProjects = implode( ',', $this->getProjectsWithModuleEnabled() );
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) AS id, ' .
		                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.logic\')) AS logic, ' .
		                          'JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) ' .
		                          'AS packfield, JSON_UNQUOTE(JSON_EXTRACT(`value`,' .
		                          '\'$.valuefield\')) AS valuefield, ' .
		                          'REGEXP_SUBSTR(`key`,\'[0-9]+\') project_id, ' .
		                          'IFNULL((SELECT `value` FROM redcap_external_module_settings ' .
		                          'ems1 WHERE ems1.`key` = REGEXP_REPLACE(ems.`key`,' .
		                          '\'^p([0-9]+)-packcat-\',\'p$1-packcatats-\')), \'0\') ts ' .
		                          'FROM redcap_external_module_settings ems ' .
		                          'JOIN redcap_external_modules em ' .
		                          'ON ems.external_module_id = em.external_module_id ' .
		                          'WHERE em.directory_prefix = ? AND ems.`key` LIKE ? ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\') ' .
		                          'AND JSON_CONTAINS(`value`,\'"A"\',\'$.trigger\') ' .
		                          'HAVING project_id IN (' . $activeProjects . ') ' .
		                          'ORDER BY CAST(ts AS decimal)',
		                          [ $this->getModuleDirectoryBaseName(), 'p%-packcat-%' ] );
		while ( $infoCat = $queryCat->fetch_assoc() )
		{
			if ( time() - $startTimestamp > 300 )
			{
				break;
			}
			// Get the project ID and set the project context.
			$projectID = $infoCat['project_id'];
			$_GET['pid'] = $projectID;
			$GLOBALS['Proj'] = new Project( $projectID );
			// Get list of records/events/instances which contain data.
			$queryRecords =
				$this->query( 'SELECT record, event_id, max(ifnull(instance,1)) max_instance ' .
				              'FROM ' . $this->getDataTable( $projectID ) . ' ' .
				              'WHERE project_id = ? GROUP BY record, event_id',
				              [ $projectID ] );
			// For each record/event.
			while ( $infoRecord = $queryRecords->fetch_assoc() )
			{
				$recordID = $infoRecord['record'];
				$eventID = $infoRecord['event_id'];
				$maxInstance = $infoRecord['max_instance'];
				// For each instance of this record/event.
				for ( $instanceNum = 1; $instanceNum <= $maxInstance; $instanceNum++ )
				{
					$this->dbGetLock();
					// Check that the pack field on the record/event/instance is empty and that the
					// logic (if specified) evaluates as true.
					if ( ! ( $this->getValues( $projectID, $recordID, $eventID, $instanceNum,
					                       $infoCat['packfield'] )[ $infoCat['packfield'] ] == '' &&
					         ( $infoCat['logic'] == '' ||
					           \REDCap::evaluateLogic( $infoCat['logic'], $projectID, $recordID,
					                                   $eventID, $instanceNum ) ) ) )
					{
						continue;
					}
					// Get and assign pack for the pack category.
					$packValue = null;
					if ( $infoCat['valuefield'] != '' )
					{
						$packValue = $this->getValues( $projectID, $recordID, $eventID,
						                               $instanceNum, $infoCat['valuefield'] )
						                             [ $infoCat['valuefield'] ];
					}
					$infoData = $this->choosePack( $catID, $recordID, $packValue );
					// Save the data to the record.
					if ( $infoData !== false )
					{
						$this->updateValues( $projectID, $recordID, $eventID,
						                     $instanceNum, $infoData );
					}
					$this->dbReleaseLock();
				}
			}
			// Update last-run timestamp for this pack category.
			$this->setSystemSetting( 'p' . $projectID . '-packcatats-' . $infoCat['id'], time() );
		}
	}



	// Assign a minimization pack as requested by the Minimization module.
	public function assignMinimPack( $recordID, $listMinimCodes, $minimField = '' )
	{
		$this->dbGetLock();
		// Get pack category for minimization field.
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.id\')) AS ' .
		                          'id, JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) AS ' .
		                          'packfield, JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.nominim\')) ' .
		                          'AS nominim FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` LIKE ? AND ' .
		                          'JSON_CONTAINS(`value`,\'"M"\',\'$.trigger\') ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')' .
		                          'AND JSON_CONTAINS(`value`,?,\'$.valuefield\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%',
		                            json_encode( $minimField ) ] );
		$infoCat = $queryCat->fetch_assoc();
		if ( empty( $infoCat ) )
		{
			$this->dbReleaseLock();
			return false;
		}
		// Assign pack
		$infoPack = false;
		$chosenCode = null;
		foreach ( $listMinimCodes as $minimCode )
		{
			$infoPack = $this->choosePack( $infoCat['id'], $recordID, $minimCode );
			$chosenCode = $minimCode;
			if ( $infoPack !== false || $infoCat['nominim'] != 'S' )
			{
				break;
			}
		}
		$this->dbReleaseLock();
		if ( $infoPack === false )
		{
			return false;
		}
		// Return the data to the Minimization module.
		$infoRando = [ 'packID' => $infoPack[ $infoCat['packfield'] ], 'randoCode' => $chosenCode ];
		unset( $infoPack[ $infoCat['packfield'] ] );
		$infoRando['extraData'] = $infoPack;
		return $infoRando;
	}



	// Choose a pack from the specified pack category and mark it as assigned.
	public function choosePack( $catID, $recordID, $value = null )
	{
		$this->dbGetLock();
		// Get the pack category details.
		$queryCat = $this->query( 'SELECT ems.`value` AS category ' .
		                          'FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` = ? AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-' . $catID ] );
		$infoCat = $queryCat->fetch_assoc();
		if ( count( $infoCat ) != 1 )
		{
			$this->dbReleaseLock();
			return false;
		}
		$infoCat = json_decode( $infoCat['category'], true );
		$paramsPacks = [ $this->getModuleDirectoryBaseName(),
		                 'p' . $this->getProjectId() . '-packlist-' . $catID ];
		// If packs are assigned to DAGs, prepare to filter the list of packs by DAG.
		$sqlPacks = '';
		if ( $infoCat['dags'] )
		{
			// Get the record DAG.
			$queryDAG = $this->query( 'SELECT `value` AS dag FROM ' .
			                          $this->getDataTable( $this->getProjectId() ) .
			                          ' WHERE project_id = ? AND record = ? AND field_name = ?',
			                          [ $this->getProjectId(), $recordID, '__GROUPID__' ] );
			$infoDAG = $queryDAG->fetch_assoc();
			if ( empty( $infoDAG ) )
			{
				// Record not in a DAG, cannot assign pack.
				$this->dbReleaseLock();
				return false;
			}
			$sqlPacks .= ' AND packlist.dag = ? AND packlist.dag_rcpt = 1';
			$paramsPacks[] = $infoDAG['dag'];
		}
		// If packs expire, prepare to exclude expired packs.
		if ( $infoCat['expire'] )
		{
			$now = date( 'Y-m-d H:i:s', time() + 600 ); // Must be more than 10min until expiry.
			$sqlPacks .= ' AND packlist.expiry > ?';
			$paramsPacks[] = $now;
		}
		// If the pack must match a value, prepare to filter by it.
		if ( $value !== null )
		{
			$sqlPacks2 .= ' AND value = ?';
			$paramsPacks[] = $value;
		}
		// Get the available packs, filtered as required.  Up to 4 packs are returned,
		// those closest to expiry and from partially used blocks are preferred.
		$queryPacks = $this->query( 'WITH packs AS (' .
		                            'SELECT id, block_id, packlist.value, expiry, ' .
		                            'extrafields, assigned ' .
		                            'FROM redcap_external_module_settings ems, ' .
		                            'redcap_external_modules em, ' .
		                            $this->makePacklistSQL('ems.value') .
		                            'WHERE em.external_module_id = ems.external_module_id ' .
		                            'AND em.directory_prefix = ? AND ems.key = ? ' .
		                            'AND packlist.invalid = 0' . $sqlPacks . ') ' .
		                            'SELECT id, extrafields, (SELECT count(*) FROM packs p ' .
		                            'WHERE p.assigned = 0) count FROM packs WHERE assigned = 0 ' .
		                            $sqlPacks2 . ' ORDER BY expiry, if((SELECT count(*) ' .
		                            'FROM packs p WHERE packs.block_id = p.block_id ' .
		                            'AND p.assigned = 1)>0,0,1), (SELECT count(*) ' .
		                            'FROM packs p WHERE packs.block_id = p.block_id ' .
		                            'AND p.assigned = 0) LIMIT 4',
		                            $paramsPacks );
		// Pick a pack.
		$infoPack = null;
		while ( $nextPack = $queryPacks->fetch_assoc() )
		{
			// Use the first pack returned 50% of the time, second pack 25% etc.
			$infoPack = $nextPack;
			if ( random_int( 0, 1 ) == 0 )
			{
				break;
			}
		}
		if ( $infoPack === null )
		{
			// No pack available.
			$this->dbReleaseLock();
			return false;
		}
		// Set the pack as assigned and write the pack assignment to the log.
		$this->updatePackProperty( $this->getProjectId(), $catID,
		                           $infoPack['id'], 'assigned', true );
		$this->updatePackLog( $this->getProjectId(), $infoCat['id'], 'PACK_ASSIGN',
		                      [ 'id' => $infoPack['id'], 'record' => $recordID ] );
		$this->dbReleaseLock();
		// Return the fields/values to be updated on the record.
		$infoValues = [ $infoCat['packfield'] => $infoPack['id'] ];
		if ( $infoCat['datefield'] != '' )
		{
			$infoValues[ $infoCat['datefield'] ] = date( 'Y-m-d H:i:s' );
		}
		if ( $infoCat['countfield'] != '' )
		{
			$infoValues[ $infoCat['countfield'] ] = $infoPack['count'] - 1;
		}
		$listPackExtraFields = json_decode( $infoPack['extrafields'], true );
		foreach ( $infoCat['extrafields'] as $extraFieldName => $infoCatExtraField )
		{
			if ( $infoCatExtraField['field'] != '' )
			{
				$infoValues[ $infoCatExtraField['field'] ] =
						$listPackExtraFields[ $extraFieldName ];
			}
		}
		return $infoValues;
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
		                            '\'$.roles_add[*]\', \'$.roles_edit[*]\' ), \'$[*]\' ' .
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



	// Get a project's redcap_data table.
	public function getDataTable( $projectID )
	{
		return method_exists( '\REDCap', 'getDataTable' )
		       ? \REDCap::getDataTable( $projectID ) : 'redcap_data';
	}



	// Get the minimization pack project field.
	public function getMinimPackField( $minimField = '' )
	{
		$queryCat = $this->query( 'SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`,\'$.packfield\')) AS ' .
		                          'packfield FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` LIKE ? AND ' .
		                          'JSON_CONTAINS(`value`,\'"M"\',\'$.trigger\') ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')' .
		                          'AND JSON_CONTAINS(`value`,?,\'$.valuefield\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%',
		                            json_encode( $minimField ) ] );
		$infoCat = $queryCat->fetch_assoc();
		return $infoCat ? $infoCat['packfield'] : null;
	}



	// Get module directory name, without version number.
	public function getModuleDirectoryBaseName()
	{
		return preg_replace( '/_v[0-9.]+$/', '', $this->getModuleDirectoryName() );
	}



	// Get the record, event ID and instance number for an assigned pack.
	public function getPackAssignedRecord( $catID, $packID )
	{
		// Get the pack category details.
		$queryCat = $this->query( 'SELECT ems.`value` AS category ' .
		                          'FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? ' .
		                          'AND ems.`key` = ? ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-' . $catID ] );
		$infoCat = $queryCat->fetch_assoc();
		if ( count( $infoCat ) != 1 )
		{
			return false;
		}
		$infoCat = json_decode( $infoCat['category'], true );
		$packField = $infoCat['packfield'];
		$queryRecord = $this->query( 'SELECT record, event_id event, ifnull(instance,1) instance ' .
		                             'FROM ' . $this->getDataTable( $this->getProjectId() ) . ' ' .
		                             'WHERE project_id = ? AND field_name = ? AND value = ?',
		                             [ $this->getProjectId(), $packField, $packID ] );
		$infoRecord = $queryRecord->fetch_assoc();
		return $infoRecord ? $infoRecord : false;
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



	// Get values for a specific record/event/instance.
	public function getValues( $projectID, $recordID, $eventID, $instanceNum, $fieldNames )
	{
		if ( ! is_array( $fieldNames ) )
		{
			$fieldNames = [ $fieldNames ];
		}
		$listRepeatingFields = true;
		$listRepeatingForms = $this->getRepeatingForms( $eventID, $projectID );
		if ( empty( $listRepeatingForms ) )
		{
			$listRepeatingFields = [];
		}
		elseif ( $listRepeatingForms[0] != '' )
		{
			$listRepeatingFields = [];
			foreach ( $listRepeatingForms as $repeatingForm )
			{
				foreach ( $this->getProject( $projectID )->getForm( $repeatingForm )->getFieldNames()
				          as $fieldName )
				{
					$listRepeatingFields[ $fieldName ] = $repeatingForm;
				}
			}
		}
		$recordData = \REDCap::getData( $projectID, 'array', $recordID, $fieldNames,
		                                $eventID, null, true );
		$listValues = [];
		foreach ( $fieldNames as $fieldName )
		{
			if ( $listRepeatingFields === true || isset( $listRepeatingFields[ $fieldName ] ) )
			{
				$repeatingFormName = $listRepeatingFields === true
				                     ? '' : $listRepeatingFields[ $fieldName ];
				$listValues[ $fieldName ] = $recordData[ $recordID ]['repeat_instances'][ $eventID ]
				                            [ $repeatingFormName ][ $instanceNum ][ $fieldName ];
			}
			else
			{
				$listValues[ $fieldName ] = $recordData[ $recordID ][ $eventID ][ $fieldName ];
			}
		}
		return $listValues;
	}



	// Check if a minimization pack category exists.
	public function hasMinimPackCategory()
	{
		$queryCat = $this->query( 'SELECT 1 FROM redcap_external_module_settings ems JOIN ' .
		                          'redcap_external_modules em ON ems.external_module_id = ' .
		                          'em.external_module_id WHERE em.directory_prefix = ? AND ' .
		                          'ems.`key` LIKE ? AND ' .
		                          'JSON_CONTAINS(`value`,\'"M"\',\'$.trigger\') ' .
		                          'AND JSON_CONTAINS(`value`,\'true\',\'$.enabled\')',
		                          [ $this->getModuleDirectoryBaseName(),
		                            'p' . $this->getProjectId() . '-packcat-%' ] );
		return ( $queryCat->fetch_assoc() ) ? true : false;
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
		                'invalid TINYINT PATH \'$.invalid\' DEFAULT \'false\' ON EMPTY, ' .
		                'invalid_desc TEXT PATH \'$.invalid_desc\' DEFAULT \'""\' ON EMPTY ' .
		       ') ) AS packlist ';
	}



	// Update the log for a pack category with a new entry.
	public function updatePackLog( $projectID, $catID, $logEvent, $logData )
	{
		$logItem = json_encode( [ 'event' => $logEvent,
		                          'user' => ( defined('USERID') ? USERID : '' ),
		                          'time' => date('Y-m-d H:i:s'), 'data' => $logData ] );
		$sql = 'UPDATE redcap_external_module_settings SET `value` = JSON_MERGE_PRESERVE(' .
		       '`value`,?) WHERE external_module_id = (SELECT external_module_id FROM ' .
		       'redcap_external_modules WHERE directory_prefix = ?) AND `key` = ?';
		$this->query( $sql, [ $logItem, $this->getModuleDirectoryBaseName(),
		                      'p' . $projectID . '-packlog-' . $catID ] );
	}



	// Update one or more properties on a pack.
	public function updatePackProperty( $projectID, $catID, $packID, $property, $value )
	{
		if ( ! is_array( $property ) )
		{
			$property = [ $property ];
		}
		if ( ! is_array( $value ) )
		{
			$value = [ $value ];
		}
		$sql = 'UPDATE redcap_external_module_settings ems SET ems.value = JSON_SET(ems.value';
		$sqlParams = [];
		for ( $i = 0; $i < count( $property ) && $i < count( $value ); $i++ )
		{
			$sql .= ',REPLACE(JSON_UNQUOTE(JSON_SEARCH(ems.value,\'one\',?,NULL,' .
			        '\'$[*].id\')),\'.id\',?),CAST(? AS JSON)';
			$sqlParams[] = $packID;
			$sqlParams[] = '.' . $property[$i];
			$sqlParams[] = json_encode( $value[$i] );
		}
		$sql .= ') WHERE ems.external_module_id = (SELECT em.external_module_id FROM ' .
		        'redcap_external_modules em WHERE em.directory_prefix = ? LIMIT 1) ' .
		        'AND ems.key = ? AND JSON_CONTAINS( JSON_EXTRACT( ems.value, \'$[*].id\' ), ? )';
		$sqlParams[] = $this->getModuleDirectoryBaseName();
		$sqlParams[] = 'p' . $projectID . '-packlist-' . $catID;
		$sqlParams[] = json_encode( $packID );
		$this->query( $sql, $sqlParams );
	}



	// Update a record with new data on a specific record/event/instance.
	public function updateValues( $projectID, $recordID, $eventID, $instanceNum, $infoData )
	{
		$listRepeatingFields = true;
		$listRepeatingForms = $this->getRepeatingForms( $eventID, $projectID );
		if ( empty( $listRepeatingForms ) )
		{
			$listRepeatingFields = [];
		}
		elseif ( $listRepeatingForms[0] != '' )
		{
			$listRepeatingFields = [];
			foreach ( $listRepeatingForms as $repeatingForm )
			{
				foreach ( $this->getProject( $projectID )->getForm( $repeatingForm )->getFieldNames()
				          as $fieldName )
				{
					$listRepeatingFields[ $fieldName ] = $repeatingForm;
				}
			}
		}
		$newData = [];
		foreach ( $infoData as $fieldName => $value )
		{
			if ( $listRepeatingFields === true || isset( $listRepeatingFields[ $fieldName ] ) )
			{
				$repeatingFormName = $listRepeatingFields === true
				                     ? '' : $listRepeatingFields[ $fieldName ];
				$newData[ $recordID ]['repeat_instances'][ $eventID ][ $repeatingFormName ]
				        [ $instanceNum ][ $fieldName ] = $value;
			}
			else
			{
				$newData[ $recordID ][ $eventID ][ $fieldName ] = $value;
			}
		}
		$result = \REDCap::saveData( $projectID, 'array', $newData, 'normal', 'YMD' );
		return empty( $result['errors'] );
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
