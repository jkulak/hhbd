<?php

/**
 * The sentence of facts under an album's or an artist's heading (#219), from the containers
 * the page shows; Jkl_Summary builds it. A view prints $this->Summary()->album($this->album),
 * escaped.
 */
class Zend_View_Helper_Summary extends Zend_View_Helper_Abstract
{
    public function Summary()
    {
        return $this;
    }

    public function album($album)
    {
        return $this->view->escape(Jkl_Summary::album(array(
            'title'        => $album->title,
            'artists'      => $album->artistNames,
            'type'         => $album->releaseType,
            'announced'    => $album->isAnnounced(),
            'date'         => $album->releaseDate,
            'precision'    => $album->releaseDatePrecision,
            'label'        => !empty($album->label) ? $album->label->name : null,
            'selfReleased' => $album->selfReleased,
            'tracks'       => !empty($album->tracklist->items) ? count($album->tracklist->items) : 0,
            'duration'     => $album->duration,
        )));
    }

    public function artist($artist)
    {
        $years = array();
        foreach (!empty($artist->albums->items) ? $artist->albums->items : array() as $album) {
            if ((int) $album->year > 0) {
                $years[] = (int) $album->year;
            }
        }
        return $this->view->escape(Jkl_Summary::artist(array(
            'name'       => $artist->name,
            'band'       => $artist->isBand(),
            'otherNames' => !empty($artist->alsoKnownAs->items) ? $artist->alsoKnownAs->items : array(),
            'albums'     => !empty($artist->albums->items) ? count($artist->albums->items) : 0,
            'from'       => empty($years) ? null : min($years),
            'to'         => empty($years) ? null : max($years),
        )));
    }
}
