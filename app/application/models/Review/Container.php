<?php

/**
 * One doubt for a person (#103): what row it is about, why, and how it was settled.
 */
#[\AllowDynamicProperties]
class Model_Review_Container
{
    public $id;
    public $entityType;
    public $entityId;
    public $reason;
    /** @var array what the doubt is: suggestions, the sources' values, a note from the batch */
    public $detail;
    public $created;
    public $resolved;
    public $resolvedBy;
    public $resolution;
    public $note;

    public function __construct(array $row)
    {
        $this->id = (int) $row['id'];
        $this->entityType = $row['entity_type'];
        $this->entityId = (int) $row['entity_id'];
        $this->reason = $row['reason'];
        $detail = isset($row['detail']) ? json_decode((string) $row['detail'], true) : null;
        $this->detail = is_array($detail) ? $detail : array();
        $this->created = $row['created'];
        $this->resolved = isset($row['resolved']) ? $row['resolved'] : null;
        $this->resolvedBy = isset($row['resolved_by']) ? (int) $row['resolved_by'] : null;
        $this->resolution = isset($row['resolution']) ? $row['resolution'] : null;
        $this->note = isset($row['note']) ? $row['note'] : null;
    }

    /** The reason as the panel names it */
    public function getLabel()
    {
        return isset(Model_Review_Api::REASONS[$this->reason]) ? Model_Review_Api::REASONS[$this->reason]['label'] : $this->reason;
    }

    /** @return string[] the actions that settle this item */
    public function getActions()
    {
        return isset(Model_Review_Api::REASONS[$this->reason]) ? Model_Review_Api::REASONS[$this->reason]['actions'] : array();
    }

    /** @return int[] the artists a namesake may be the same as */
    public function getSuggestions()
    {
        return isset($this->detail['suggestions']) ? array_map('intval', (array) $this->detail['suggestions']) : array();
    }

    /** @return string[] the values the sources gave, for a disputed date or type */
    public function getValues()
    {
        return isset($this->detail['values']) ? array_map('strval', (array) $this->detail['values']) : array();
    }

    /** What the batch said about it, if anything */
    public function getText()
    {
        return isset($this->detail['text']) ? (string) $this->detail['text'] : '';
    }
}
