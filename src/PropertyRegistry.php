<?php

namespace SESP;

use SMW\DataTypeRegistry;
use SMW\DIProperty;
use SMWDataItem as DataItem;

use RuntimeException;
use UnexpectedValueException;

/**
 * @ingroup SESP
 *
 * @licence GNU GPL v2+
 * @since 0.3
 *
 * @author mwjames
 */
class PropertyRegistry {

	/** @var PropertyRegistry */
	protected static $instance = null;

	protected $definitions = null;

	/**
	 * @since 0.3
	 *
	 * @return PropertyRegistry
	 */
	public static function getInstance() {

		if ( self::$instance === null ) {

			$instance = new self();
			$instance->definitions = $instance->acquireDefinitionsFromJsonFile( $instance->getJsonFile() );

			self::$instance = $instance;
		}

		return self::$instance;
	}

	/**
	 * @since 0.3
	 */
	public static function clear() {
		self::$instance = null;
	}

	/**
	 * @since 0.3
	 */
	public function getJsonFile() {
		return __DIR__ . '/' . 'definitions.json';
	}

	/**
	 * @since 0.3
	 *
	 * @param string $path
	 *
	 * @return array
	 * @throws RuntimeException
	 * @throws UnexpectedValueException
	 */
	public function acquireDefinitionsFromJsonFile( $path ) {

		if ( !is_readable( $path ) ) {
			throw new RuntimeException( "Expected a {$path} file" );
		}

		$definitions = json_decode( file_get_contents( $path ), true );

		if ( $definitions !== null && is_array( $definitions ) && json_last_error() === JSON_ERROR_NONE ) {
			return $definitions;
		}

		throw new UnexpectedValueException( 'Expected a JSON compatible format' );
	}

	/**
	 * @since 0.3
	 *
	 * @param string $id
	 *
	 * @return string|null
	 */
	public function getPropertyId( $id ) {
		return $this->lookupWithIdentifier( 'id', $id );
	}

	/**
	 * @since 0.3
	 *
	 * @param string $id
	 *
	 * @return string|null
	 */
	public function getPropertyType( $id ) {
		return $this->lookupWithIdentifier( 'type', $id );
	}

	/**
	 * Only those properties that are customized are also only considered as
	 * possible candidates for a fixed table
	 *
	 * @note Specific exif properties are not considered as fixed table entry
	 *
	 * @since 0.3
	 *
	 * @param array $propertyTableDefinitions
	 * @param array $configuration
	 *
	 * @return boolean
	 */
	public function registerAsFixedTables( &$propertyTableDefinitions, $configuration ) {

		if ( !isset( $configuration['sespUseAsFixedTables'] ) || !$configuration['sespUseAsFixedTables'] ) {
			return true;
		}

		$enabledSpecialProperties = array_flip( $configuration['sespSpecialProperties'] );
		$propertyTypeList = array_keys( $this->definitions );

		foreach( $propertyTypeList as $externalId ) {

			$dataItemType = $this->getPropertyType( $externalId );

			if ( !isset( $enabledSpecialProperties[$externalId] ) || $dataItemType === null ) {
				continue;
			}

			$tableName = 'smw_ftp_sesp' . strtolower( $externalId );

			$propertyTableDefinitions[$tableName] = new \SMW\SQLStore\TableDefinition(
				$dataItemType,
				$tableName,
				$this->getPropertyId( $externalId )
			);
		}

		return true;
	}

	/**
	 * @note If there are an exceedingly amount of possible exif properties, those
	 * should only registered if '_EXIFDATA' is used as configuration parameter
	 *
	 * @since 0.3
	 *
	 * @return boolean
	 */
	public function registerPropertiesAndAliases() {
		$this->registerPropertiesFromList( array_keys( $this->definitions ) );
		$this->registerPropertiesFromList( array_keys( $this->definitions['_EXIF'] ) );

		return true;
	}

	protected function registerPropertiesFromList( array $propertyList ) {

		foreach ( $propertyList as $externalId ) {

			$propertyId = $this->getPropertyId( $externalId );

			if ( $propertyId === null ) {
				continue;
			}

			DIProperty::registerProperty(
				$propertyId,
				$this->getPropertyDataItemTypeId( $externalId ),
				$this->getPropertyLabel( $externalId )
			);

			DIProperty::registerPropertyAlias(
				$propertyId,
				$this->getPropertyAlias( $externalId )
			);
		}
	}

	protected function getPropertyLabel( $id ) {

		$msgkey = $this->lookupWithIdentifier( 'msgkey', $id );

		if ( $msgkey ) {
			return wfMessage( $msgkey )->inContentLanguage()->text();
		}

		return false;
	}

	protected function getPropertyAlias( $id ) {
		return $this->lookupWithIdentifier( 'alias', $id );
	}

	protected function getPropertyDataItemTypeId( $id ) {

		$type = $this->getPropertyType( $id );

		if ( $type ) {
			return DataTypeRegistry::getInstance()->getDefaultDataItemTypeId( $type );
		}

		return null;
	}

	protected function lookupWithIdentifier( $key, $id ) {

		$id = strtoupper( $id );

		if ( isset( $this->definitions[ $id ] ) && isset( $this->definitions[ $id ][ $key ] ) ) {
			return $this->definitions[ $id ][ $key ];
		}

		if ( isset( $this->definitions['_EXIF'][ $id ] ) && isset( $this->definitions['_EXIF'][ $id ][ $key ] ) ) {
			return $this->definitions['_EXIF'][ $id ][ $key ];
		}

		return null;
	}

}