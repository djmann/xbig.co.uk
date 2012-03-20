<?php
/**
 * Abstract class which is used as the base for all entity classes
 *
 * @author    David Mann <david.mann@djmann.co.uk>
 * @copyright David Mann
 * @version   1.0
 */

/**
 * Abstract class which is used as the base for all entity classes
 */
abstract class Xblig_EntityTemplate
{
    /**
     * Constant used to manage output rendering
     *
     * @var string
     */
    const RENDER_TEXT   = 'TEXT';

    /**
     * Constant used to manage output rendering
     *
     * @var string
     */
    const RENDER_XML    = 'XML';

    /**
     * Constant used to manage output rendering
     *
     * @var string
     */
    const RENDER_HTML   = 'HTML';

    /**
     * Used to hold entity configuration data - config[key] = value
     *
     * @var array
     */
    protected $config = null;

    /**
     * Used to hold entity metadata - metadata[category][key] = value
     *
     * @var array
     */
    protected $metadata = null;

    /**
     * Used to hold child entities - childEntities[type][id] = object
     *
     * @var array
     */
    protected $childEntities = null;

    /**
     * The database connection used for reading/writing entity data
     *
     * @var Xblig_DbConnection
     */
    protected $dbconn = null;

    /**
     * Performs entity-specific initialisation (prior to reading from the DB)
     *
     * @return void
     */
    abstract public function initialiseSelf();

    /**
     * Reads the entity's data from the database
     *
     * @param integer $id The ID for the given entity
     * @return void
     */
    abstract public function readFromDb($id);

    /**
     * Writes the entity's data to the database
     *
     * @param string $forceWrite    Indicates that the write should be performed even if no changes have been made
     * @param string $writeChildren Indicates that any "child" entities should also be written to the database
     * @return void
     */
    abstract public function writeEntityToDb($forceWrite = FALSE, $writeChildren = TRUE);

    /**
     * Used to perform any entity-specific validation prior to writing to the database
     *
     * @return void
     */
    abstract public function validateBeforeDbWrite();

    /**
     * Deletes the entity's data from the database
     *
     * @param boolean $deleteChildren Indicates that any "children" should also be deleted
     * @return void
     */
    abstract public function deleteFromDb ($deleteChildren = TRUE);

    /**
     * Renders the entity in the requested format
     *
     * @param string $renderType The format to render the entity in
     * @return string
     */
    abstract public function render($renderType = self::RENDER_TEXT);

    /**
     * Performs basic initialisation of the entity
     *
     * @return void
     */
    final public function __construct($dbconn, $id = FALSE, $userObj = FALSE)
    {
        $this->config = array();
        $this->metadata = array();
        $this->childEntities = array();

        $this->initialiseDb($dbconn);

        $this->initialiseSelf();

        if ($id != FALSE)
            $this->readFromDb($id);

        // Note that there will only be a user object when the editor is being used
        if ($userObj != FALSE)
            $this->setCurrentUser($userObj);

        $this->updated = FALSE;
    }

    /**
     * Gets a database connection for the entity.  Note that this connection may
     * be shared among multiple entities!
     * NOTE: need to make sure transactional integrity is maintained
     *
     * @return void
     */
    protected function initialiseDb()
    {
        if ($this->dbconn == FALSE) {
            $this->dbconn = Xblig_DbFactory::getConnection();
        }

        // We don't attempt to autorecover, to prevent infinite loops...
        $this->checkDb (FALSE);
    }

    /**
     * Checks the object's database connection to confirm it's active prior to
     * performing any database activities
     *
     * @param boolean $autoRecover TRUE: get a database connection if one is not active.  FALSE: throw an exception
     * @return void
     */
    protected function checkDb($autoRecover = TRUE)
    {
        $dbc = $this->dbconn;

        if (!$dbc || ($dbc instanceof Xblig_DbConnection) == false || $dbc->connect_errno > 0) {
            if ($autoRecover == TRUE) {
                error_log("Database connection does not exist: attempting to recover");
                $this->dbconn = FALSE;
                $this->initialiseDb();
            } else {
                throw new Xblig_Exception("database connection not initialised");
            }
        }
    }

    /**
     * Used to link a user to an entity (e.g. the review writer)
     *
     * @param Xblig_User $userObj The object representing the user
     * @return void
     */
    public function setCurrentUser($userObj)
    {
        if ($userObj != null && ($userObj instanceof Xblig_User) == FALSE) {
            throw new Xblig_Exception("userObj is not valid");
        }
        $this->setConfigValue('currentUser', $userObj);
    }

    /**
     * Returns the user associated with the given entity
     *
     * @return Xblig_User
     */
    public function getCurrentUser()
    {
        return $this->getConfigValue('currentUser');
    }

    /**
     * Used to indicate that changes have been made to an entity
     *
     * @param boolean $updated TRUE: the entity has been updated.  FALSE: the entity has not been updated
     * @return void
     */
    public function setUpdated($updated)
    {
        $this->setConfigValue('updated', $userObj);
    }

    /**
     * Returns the updated flag
     *
     * @return boolean
     */
    public function getUpdated()
    {
        return $this->getConfigValue('updated');
    }

    /**
     * Sets a configuration value for the entity
     *
     * @param string $key   The key associated with the given value
     * @param mixed  $value The value associated with the given key
     * @return void
     */
    public function setConfigValue ($key, $value)
    {
        $this->config[$key] = $value;
    }

    /**
     * Sets a configuration value for the entity
     *
     * @param string $key          The key associated with the given value
     * @param mixed  $defaultValue The default value to return if the config value does not exist: if not defined, an exception will be thrown
     * @return string
     */
    public function getConfigValue ($key, $defaultValue = null)
    {
        
        $keyExists = array_key_exists($key, $this->config);

        if ($keyExists === FALSE && $defaultValue === null) {
            throw new Xblig_Exception("config item [$key] not found");
        }

        $value = ($keyExists ? $this->config[$key] : $defaultValue);

        return $value;
    }

    /**
     * Sets a metadata value for the entity.  Metadata values have a primary and secondary
     * key associated with them
     *
     * @param string $priKey The primary key associated with the given metadata value
     * @param string $secKey The secondary key associated with the given metadata value
     * @param mixed  $value  The value associated with the given keys
     * @return void
     */
    public function setMetadataValue($priKey, $secKey, $value)
    {
        if (array_key_exists ($priKey, $this->metadata) === FALSE) {
            $this->metadata[$priKey] = array();
        }

        $this->metadata[$priKey][$secKey] = $value;
    }

    /**
     * Gets a metadata value for the entity
     *
     * @param string $priKey       The primary key associated with the given metadata value
     * @param string $secKey       The secondary key associated with the given metadata value
     * @param mixed  $defaultValue The default value associated with the given keys - if not defined, an exception will be thrown if the value does not exist
     * @return mixed
     */
    public function getMetadataValue($priKey, $secKey, $defaultValue = null)
    {
        $priKeyExists = array_key_exists($priKey, $this->metadata);
        if ($priKeyExists === FALSE) {
            throw new Xblig_Exception("metadata primary key [$priKey] not found");
        }

        $secKeyExists = array_key_exists($secKey, $this->metadata[$priKey]);
        if ($secKeyExists === FALSE && $defaultValue === null) {
            throw new Xblig_Exception("metadata key-pair [$priKey][$secKey] not found");
        }

        $value = ($secKeyExists ? $this->metadata[$priKey][$secKey] : $defaultValue);

        return $value;
    }

    /*
     * Updates the "global" timestamp used by the caching algorithms to determine if the caches 
     * are still valid.  Note that it does *NOT* update the timestamp for the entity itself
     *
     * @return void
     */
    protected function updateGlobalTimestamp()
    {
        $this->checkDb();
        $qu = "update global_config set config_value=now() where name='last_updated'";
        $this->dbconn->execute($qu);
    }

    /*
     * Writes the entity's data to the database and updates the global timestamp for the system caches
     *
     * @param boolean $updateChildren TRUE: the child entities will also be updated
     * @return void
     */
    public function writeToDb($updateChildren = TRUE)
    {
        $this->checkDb();

        // We don't throw an error if the object hasn't been updated, but instead "fail" silently.
        if ($this->updated == TRUE) {
            $this->validateBeforeDbWrite();
            $this->writeEntityToDb();
            $this->updateGlobalTimestamp ();
        }

        if ($updateChildren == TRUE) {
            foreach ($this->childEntities as $type => $childList) {
                foreach ($childList as $id => $childEntity) {
                    $childEntity->writeToDb($updateChildren);
                }
            }
        }
    }

    /*
     * 'Boilerplate' magic methods...
     */
    public function __sleep ()
    {
        $this->dbconn   = FALSE;

        // __sleep is meant to return a list of the "serialisable" attributes the object contains
        // Child classes may therefore need to override this method if they add additional data
        // structures
        return array ('type', 'config');
    }

    public function __wake ()
    {
        $this->initialiseDb ();
    }

    public function __toString ()
    {
        return $this->render (self::RENDER_TEXT);
    }
}
