<?php

/**
 * One row of import_provenance: a field of a catalog row, and a source that supplied it.
 */
#[\AllowDynamicProperties]
class Model_Provenance_Container
{
    public $entityType;
    public $entityId;
    public $field;
    public $source;
    public $sourceRef;
    public $licence;
    public $fetched;
    public $runId;
    public $added;

    public function __construct($params)
    {
        $this->entityType = $params['entity_type'];
        $this->entityId = (int) $params['entity_id'];
        $this->field = $params['field'];
        $this->source = $params['source'];
        $this->sourceRef = $params['source_ref'];
        $this->licence = isset($params['licence']) ? $params['licence'] : null;
        $this->fetched = $params['fetched'];
        $this->runId = (int) $params['run_id'];
        $this->added = isset($params['added']) ? $params['added'] : null;
    }
}
