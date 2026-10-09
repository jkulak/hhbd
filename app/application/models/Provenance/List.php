<?php

/**
 * Where the imported fields of one catalog row came from, as Model_Provenance_Api returns it.
 * A view asks it about a field instead of walking the rows.
 */
class Model_Provenance_List extends Jkl_List
{
    /**
     * Whether a source supplied the field: cameFrom('cover', 'discogs').
     */
    public function cameFrom($field, $source)
    {
        foreach ($this->items as $item) {
            if ($item->field === $field && $item->source === $source) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the page has to say "Data provided by Discogs." (#62): Discogs's API terms ask for
     * it next to anything taken through the API. Data from the monthly dump is CC0 and asks for
     * nothing, so the importer records its licence as CC0; any other Discogs row came through
     * the API.
     */
    public function requiresDiscogsCredit()
    {
        foreach ($this->items as $item) {
            if ('discogs' === $item->source && 'CC0' !== $item->licence) {
                return true;
            }
        }
        return false;
    }

    /**
     * Every source that supplied the field, in alphabetical order; empty for a field nothing
     * imported, such as one a person typed in.
     *
     * @return Model_Provenance_Container[]
     */
    public function getForField($field)
    {
        return array_values(array_filter($this->items, function ($item) use ($field) {
            return $item->field === $field;
        }));
    }
}
