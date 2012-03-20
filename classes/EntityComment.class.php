<?php
/**
 * Represents a comment in the XBLIG system
 *
 * @author    David Mann <david.mann@djmann.co.uk>
 * @copyright David Mann
 * @version   1.0
 */

/**
 * Represents a comment in the XBLIG system
 */
class Xblig_EntityComment extends Xblig_EntityTemplate
{
    /**
     * Implementation of the abstract function from Xblig_EntityTemplate
     * Performs entity-specific initialisation
     *
     * @return void
     */
    public function initialiseSelf ()
    {
    }

    /**
     * Implementation of the abstract function from Xblig_EntityTemplate
     * Reads the entity's data from the database
     *
     * @param integer $id The ID for the given entity
     * @return void
     */
    public function readFromDb($id)
    {
    }

    /**
     * Implementation of the abstract function from Xblig_EntityTemplate
     * Writes the entity's data to the database
     *
     * @param string $forceWrite    Indicates that the write should be performed even if no changes have been made
     * @param string $writeChildren Indicates that any "child" entities should also be written to the database
     * @return void
     */
    public function writeToDb($forceWrite = FALSE, $writeChildren = TRUE)
    {
    }

    /**
     * Implementation of the abstract function from Xblig_EntityTemplate
     * Used to perform any entity-specific validation prior to writing to the database
     *
     * @return void
     */
    public function validateBeforeDbWrite()
    {
    }

    /**
     * Implementation of the abstract function from Xblig_EntityTemplate
     *
     * @param boolean $deleteChildren Indicates that any "children" should also be deleted
     * @return void
     */
    public function deleteFromDb ($deleteChildren = TRUE)
    {
    }

    /**
     * Implementation of the abstract function from Xblig_EntityTemplate
     * Renders the entity in the requested format
     *
     * @param string $renderType The format to render the entity in
     * @return string
     */
    public function render($renderType = self::RENDER_TEXT)
    {
    }
}
