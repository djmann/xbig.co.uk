<?php
/**
 * For the moment, this is a fairly simple extension of the mysqli class, which is intended to provide methods to 
 * abstract the details for some of the more common operations.
 * This way of working has more in common with PDO than mysqli - maybe we should swap over?
 *
 * @author David Mann <david.mann@djmann.co.uk>
 * @copyright David Mann 2012
 */

/**
 * Object to handle database connectivity for the XBLIG system
 *
 */
class Xblig_DbConnection extends mysqli
{
    /**
     * Executes the given query
     *
     * @param string  $qu            The query to be executed
     * @param boolean $flagNoChanges If set to TRUE, the system will throw an exception if no rows were affected
     * @return integer 
     */
    public function execute ($qu, $flagNoChanges = FALSE)
    {
        $recordId = -1;

        if ($this->query($qu) == FALSE) {
            throw new Xblig_Exception ("Unable to process [$qu]: {$this->conn->error}");
        }

        if ($this->affected_rows == 0 && $flagNoChanges == TRUE) {
            throw new Xblig_Exception ("Successfully executed [$qu] but no rows were affected");
        }

        // Note that this will be zero for queries which don't involve an INSERT or
        // an UPDATE on a table with an auto-increment column
        $recordId = $this->insert_id;

        return $recordId;
    }

    /**
     * Returns the resultset for a given query.  
     * NOTE: if $expectOneResult is set, this method will return the first row rather than the entire resultset
     *
     * @param string  $qu              The query to be ran
     * @param boolean $expectOneResult TRUE: return the first row OR throw an exception if multiple rows found
     * @param boolean $flagNoRecords   TRUE: throw an exception if no records found
     * @return array 
     */
    public function getResultSets ($qu, $expectOneResult = FALSE, $flagNoRecords = FALSE)
    {
        // For reasons best known to the mysqli developers, you can only have one active cursor/statement open at a time.
        // Therefore, to avoid issues, we have to reset() each statement and close cursors after use.  Fortunately, the 
        // resultset from a cursor isn't affected when the cursor is closed (and similar applies to bound variables after 
        // a statement reset)!
        // Note that this approach does have memory/processing overheads and may be problematic for larger resultsets...

        $resultCount = 0;
        $all_rs = array();
        $rs = FALSE;

        $cursor = $this->query ($qu);
        if ($cursor == FALSE) {
            throw new Xblig_Exception ("Unable to process [$qu]: {$this->conn->error}");
        }

        $resultCount = $cursor->num_rows;
        if ($resultCount == 0 && $flagNoRecords == TRUE) {
            throw new Xblig_Exception ("Zero results returned for [$qu]: one or more records were expected");
        } else if ($resultCount != 1 && $expectOneResult == TRUE) {
            throw new Xblig_Exception ("$resultCount results returned for [$qu]: one record was expected");
        }

        while ($rs = $cursor->fetch_assoc()) {
            $all_rs[] = $rs;
        }

        if ($expectOneResult == TRUE)
            return $all_rs[0];
        else
            return $all_rs;
    }
}
