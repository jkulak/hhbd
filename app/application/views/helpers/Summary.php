<?php

/**
 * The sentence of facts under an album's, an artist's, a song's or a label's heading (#219,
 * #287), from the containers the page shows; Jkl_Summary builds it. A view prints $this->Summary()->album($this->album),
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

    public function song($song)
    {
        $names = function ($list) {
            return array_map(function ($artist) {
                return $artist->name;
            }, !empty($list->items) ? $list->items : array());
        };
        $artists = $names($song->artist);
        if (empty($artists) && !empty($song->albumArtist)) {
            $artists = array($song->albumArtist->name);
        }
        $albums = array();
        foreach (!empty($song->featured->items) ? $song->featured->items : array() as $album) {
            $albums[] = array('title' => $album->title, 'year' => $album->year, 'label' => !empty($album->label) ? $album->label->name : null);
        }
        $featuring = array();
        foreach (!empty($song->featuring->items) ? $song->featuring->items : array() as $guest) {
            $featuring[] = $guest->name . (!empty($guest->featType) ? ' (' . $guest->featType . ')' : '');
        }
        return $this->view->escape(Jkl_Summary::song(array(
            'title'     => $song->title,
            'artists'   => $artists,
            'albums'    => $albums,
            'music'     => $names($song->music),
            'featuring' => $featuring,
            'scratch'   => $names($song->scratch),
        )));
    }

    public function label($label)
    {
        $years = array();
        $artists = array();
        foreach (!empty($label->releases->items) ? $label->releases->items : array() as $album) {
            if ((int) $album->year > 0) {
                $years[] = (int) $album->year;
            }
            // V/A stands for a compilation's various artists, no one to name
            $name = '' !== (string) $album->artistNames ? $album->artistNames : (!empty($album->artist) ? $album->artist->name : '');
            if ('' !== $name && 'V/A' !== $name) {
                $artists[$name] = (isset($artists[$name]) ? $artists[$name] : 0) + 1;
            }
        }
        // The one with most albums first, and among as many the one the list shows first
        $order = array_flip(array_keys($artists));
        uksort($artists, function ($a, $b) use ($artists, $order) {
            return $artists[$b] <=> $artists[$a] ?: $order[$a] <=> $order[$b];
        });
        return $this->view->escape(Jkl_Summary::label(array(
            'name'    => $label->name,
            'albums'  => !empty($label->releases->items) ? count($label->releases->items) : 0,
            'from'    => empty($years) ? null : min($years),
            'to'      => empty($years) ? null : max($years),
            'artists' => array_keys($artists),
        )));
    }
}
